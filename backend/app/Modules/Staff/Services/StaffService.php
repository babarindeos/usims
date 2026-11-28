<?php

namespace App\Modules\Staff\Services;

use App\Modules\Audit\Services\AuditContext;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\IdentifierService;
use App\Modules\Staff\Events\StaffStatusChanged;
use App\Modules\Staff\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * The minimal staff record M2 needs for course allocation. The full HR module (M6) extends it;
 * until then records are entered here or imported, and `source` says which.
 */
class StaffService
{
    public function __construct(private IdentifierService $identifiers, private AuditContext $context) {}

    public function create(Person $person, array $data): Staff
    {
        return DB::transaction(function () use ($person, $data) {
            $staff = Staff::create($data + ['person_id' => $person->id, 'employment_status' => 'active', 'source' => 'manual']);
            $this->identifiers->issue($person, 'STAFF_NO', $staff->staff_no, 'Staff record created');

            return $staff;
        });
    }

    /** Status changes carry a reason and tell other modules, so they can react (for example, reassign courses). */
    public function update(Staff $staff, array $data, ?string $reason): Staff
    {
        return DB::transaction(function () use ($staff, $data, $reason) {
            $from = $staff->employment_status;
            $oldNo = $staff->staff_no;
            $this->context->withReason($reason, fn () => $staff->update($data));

            if ($staff->staff_no !== $oldNo) {
                $this->identifiers->issue($staff->person, 'STAFF_NO', $staff->staff_no, $reason ?? 'Staff number corrected');
            }
            if ($staff->employment_status !== $from) {
                StaffStatusChanged::dispatch($staff, $from, $staff->employment_status);
            }

            return $staff;
        });
    }
}
