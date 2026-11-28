<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\AdmissionExercise;
use App\Modules\Admissions\Models\AdmissionList;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Services\AdmissionListService;
use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AdmissionListController extends Controller
{
    public function __construct(private AdmissionListService $lists) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admissions.view');
        $rows = AdmissionList::with('exercise')->withCount('entries')
            ->when($request->query('exercise'), fn ($q, $e) => $q->whereHas('exercise', fn ($w) => $w->where('uuid', $e)))
            ->orderByDesc('id')->get();

        return response()->json(['data' => $rows->map(fn ($l) => $this->summary($l) + ['entries_count' => $l->entries_count])]);
    }

    public function show(AdmissionList $list): JsonResponse
    {
        Gate::authorize('admissions.view');

        return response()->json(['data' => $this->detail($list)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('admissions.lists');
        $data = $request->validate(['exercise_id' => ['required', 'string', 'exists:admission_exercises,uuid'], 'name' => ['required', 'string', 'max:255']]);
        $list = $this->lists->create(AdmissionExercise::where('uuid', $data['exercise_id'])->first(), $data['name'], $request->user());

        return response()->json(['data' => $this->detail($list)], 201);
    }

    public function addEntries(Request $request, AdmissionList $list): JsonResponse
    {
        Gate::authorize('admissions.lists');
        $data = $request->validate(['applications' => ['required', 'array', 'min:1', 'max:1000'], 'applications.*' => ['string', 'exists:applications,uuid']]);
        $added = $this->lists->add($list, Application::whereIn('uuid', $data['applications'])->get());

        return response()->json(['data' => $this->detail($list), 'added' => $added]);
    }

    public function removeEntry(AdmissionList $list, Application $application): Response
    {
        Gate::authorize('admissions.lists');
        $this->lists->remove($list, $application);

        return response()->noContent();
    }

    public function submit(Request $request, AdmissionList $list): JsonResponse
    {
        Gate::authorize('admissions.lists');
        $this->lists->submit($list, $request->user(), $request->validate(['remarks' => ['nullable', 'string', 'max:2000']])['remarks'] ?? null);

        return response()->json(['data' => $this->detail($list->refresh())]);
    }

    private function summary(AdmissionList $list): array
    {
        return ['id' => $list->uuid, 'name' => $list->name, 'status' => $list->status, 'exercise' => ['id' => $list->exercise->uuid, 'name' => $list->exercise->name],
            'approved_at' => $list->approved_at?->toIso8601String(), 'created_at' => $list->created_at->toIso8601String()];
    }

    private function detail(AdmissionList $list): array
    {
        $list->load(['exercise', 'entries.application.person', 'entries.application.offer', 'entries.exerciseProgramme.programme']);
        $workflow = WorkflowInstance::where('subject_type', $list->getMorphClass())->where('subject_id', $list->id)->with('currentStage')->latest('id')->first();

        return $this->summary($list) + [
            'editable' => $list->isEditable(),
            'workflow' => $workflow ? ['id' => $workflow->uuid, 'reference' => $workflow->reference, 'status' => $workflow->status, 'current_stage' => $workflow->currentStage?->name] : null,
            'entries' => $list->entries->map(fn ($e) => [
                'application_id' => $e->application->uuid, 'application_no' => $e->application->application_no, 'name' => $e->application->person->fullName(),
                'utme_score' => $e->application->utme_score, 'screening_outcome' => $e->application->screening_outcome,
                'programme' => $e->exerciseProgramme->programme->code, 'entry_level' => $e->exerciseProgramme->entry_level,
                'offer' => $e->application->offer ? ['admission_no' => $e->application->offer->admission_no, 'status' => $e->application->offer->status] : null,
            ])->sortBy('programme')->values(),
        ];
    }
}
