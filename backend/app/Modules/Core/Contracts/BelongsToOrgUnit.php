<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Institution\Models\OrgUnit;

/** A record whose access is decided by the org unit that owns it (a student's department, an offering's department ...). */
interface BelongsToOrgUnit
{
    public function owningOrgUnit(): ?OrgUnit;
}
