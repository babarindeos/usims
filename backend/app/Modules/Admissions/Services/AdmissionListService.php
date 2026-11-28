<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\AdmissionExercise;
use App\Modules\Admissions\Models\AdmissionList;
use App\Modules\Admissions\Models\Application;
use App\Modules\Identity\Models\User;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Recommended candidates are approved in lists through the configured workflow (PRD 5.10). */
class AdmissionListService
{
    public const PROCESS = 'admission_approval';

    public function __construct(private WorkflowEngine $workflows, private OfferService $offers) {}

    public function create(AdmissionExercise $exercise, string $name, User $by): AdmissionList
    {
        return AdmissionList::create(['admission_exercise_id' => $exercise->id, 'name' => $name, 'status' => 'draft', 'created_by' => $by->id]);
    }

    /** @param iterable<Application> $applications */
    public function add(AdmissionList $list, iterable $applications): int
    {
        $this->ensureEditable($list);
        $added = 0;
        DB::transaction(function () use ($list, $applications, &$added) {
            foreach ($applications as $application) {
                if ($application->admission_exercise_id !== $list->admission_exercise_id || $application->status !== 'recommended') {
                    throw ValidationException::withMessages(['applications' => "{$application->application_no} is not a recommended candidate in this exercise."]);
                }
                $elsewhere = DB::table('admission_list_entries')->join('admission_lists', 'admission_lists.id', '=', 'admission_list_entries.admission_list_id')
                    ->where('application_id', $application->id)->where('admission_lists.id', '!=', $list->id)
                    ->whereIn('admission_lists.status', ['draft', 'returned', 'in_review', 'approved'])->exists();
                if ($elsewhere) {
                    throw ValidationException::withMessages(['applications' => "{$application->application_no} is already on another admission list."]);
                }
                $entry = $list->entries()->firstOrCreate(['application_id' => $application->id], ['exercise_programme_id' => $application->exercise_programme_id]);
                $added += $entry->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $added;
    }

    public function remove(AdmissionList $list, Application $application): void
    {
        $this->ensureEditable($list);
        $list->entries()->where('application_id', $application->id)->delete();
    }

    /** Capacity is checked here, per programme, counting offers already made in the exercise. */
    public function submit(AdmissionList $list, User $by, ?string $remarks): WorkflowInstance
    {
        $this->ensureEditable($list);
        $list->load('entries.exerciseProgramme.programme', 'entries.application');
        if ($list->entries->isEmpty()) {
            throw ValidationException::withMessages(['list' => 'Add candidates before submitting the list.']);
        }
        $problems = [];
        foreach ($list->entries->groupBy('exercise_programme_id') as $rows) {
            $choice = $rows->first()->exerciseProgramme;
            $capacity = $choice->capacity ?? $choice->programme->capacity;
            if ($capacity) {
                $offered = DB::table('admission_offers')->join('applications', 'applications.id', '=', 'admission_offers.application_id')
                    ->where('applications.admission_exercise_id', $list->admission_exercise_id)->where('admission_offers.programme_id', $choice->programme_id)
                    ->whereNotIn('admission_offers.status', ['declined', 'expired', 'withdrawn'])->count();
                if ($offered + $rows->count() > $capacity) {
                    $problems[] = "{$choice->programme->code}: {$rows->count()} on this list and {$offered} already offered exceeds the capacity of {$capacity}.";
                }
            }
        }
        foreach ($list->entries as $entry) {
            if ($entry->application->status !== 'recommended') {
                $problems[] = "{$entry->application->application_no} is no longer recommended.";
            }
        }
        if ($problems) {
            throw ValidationException::withMessages(['list' => $problems]);
        }

        $returned = WorkflowInstance::where('subject_type', $list->getMorphClass())->where('subject_id', $list->id)->where('status', WorkflowInstance::RETURNED)->first();

        return $returned
            ? $this->workflows->act($returned, $by, 'resubmit', $remarks)
            : $this->workflows->start(self::PROCESS, $list, $by, ['category' => $list->exercise->category->code], $remarks);
    }

    /** Keeps the list in step with its workflow; approval issues the offers. */
    public function syncFromWorkflow(WorkflowInstance $instance): void
    {
        $list = AdmissionList::find($instance->subject_id);
        if (! $list) {
            return;
        }
        $status = match ($instance->status) {
            WorkflowInstance::IN_PROGRESS => 'in_review',
            WorkflowInstance::RETURNED => 'returned',
            WorkflowInstance::APPROVED => 'approved',
            WorkflowInstance::REJECTED => 'rejected',
            WorkflowInstance::CANCELLED => 'draft',
            default => $list->status,
        };
        if ($status === $list->status) {
            return;
        }
        DB::transaction(function () use ($list, $status) {
            $list->forceFill(['status' => $status, 'approved_at' => $status === 'approved' ? now() : null])->save();
            if ($status === 'approved') {
                foreach ($list->entries()->with('application')->get() as $entry) {
                    $this->offers->issue($entry->application, $list);
                }
            }
        });
    }

    private function ensureEditable(AdmissionList $list): void
    {
        if (! $list->isEditable()) {
            throw ValidationException::withMessages(['list' => "This list is {$list->status} and can no longer be changed."]);
        }
    }
}
