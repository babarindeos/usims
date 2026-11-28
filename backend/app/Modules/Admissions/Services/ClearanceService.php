<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Events\ClearanceCompleted;
use App\Modules\Admissions\Models\ClearanceItem;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Each unit reviews its own item; every decision keeps the officer, time and remarks (PRD 6.3). */
class ClearanceService
{
    /** Officer actions: the status an item may move to from each status. */
    private const OFFICER = [
        'submitted' => ['under_review', 'approved', 'rejected', 'requires_correction'],
        'under_review' => ['approved', 'rejected', 'requires_correction'],
    ];

    public function __construct(private NotificationService $notifications) {}

    public function review(ClearanceItem $item, User $by, string $to, ?string $remarks): ClearanceItem
    {
        if (! in_array($to, self::OFFICER[$item->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "A {$item->status} item cannot be marked {$to}."]);
        }
        if (in_array($to, ['rejected', 'requires_correction'], true) && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Tell the candidate what is wrong.']);
        }

        DB::transaction(function () use ($item, $by, $to, $remarks) {
            $from = $item->status;
            $item->update(['status' => $to]);
            $item->actions()->create(['from_status' => $from, 'to_status' => $to, 'remarks' => $remarks, 'user_id' => $by->id]);
        });

        $offer = $item->offer;
        if ($to !== 'under_review' && ($user = $offer->application->person->user)) {
            $this->notifications->send('clearance.updated', $user, ['unit' => $item->unit->name, 'status' => str_replace('_', ' ', $to), 'remarks' => $remarks ?? ''], '/applicant');
        }
        if ($to === 'approved' && $offer->clearance()->where('status', '!=', 'approved')->doesntExist()) {
            ClearanceCompleted::dispatch($offer);
        }

        return $item;
    }

    /** After a correction request the candidate fixes the problem and sends the item back. */
    public function resubmit(ClearanceItem $item, User $by, ?string $remarks): ClearanceItem
    {
        if ($item->status !== 'requires_correction') {
            throw ValidationException::withMessages(['status' => 'Only items needing correction can be resubmitted.']);
        }
        DB::transaction(function () use ($item, $by, $remarks) {
            $item->update(['status' => 'submitted']);
            $item->actions()->create(['from_status' => 'requires_correction', 'to_status' => 'submitted', 'remarks' => $remarks, 'user_id' => $by->id]);
        });

        return $item;
    }
}
