<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\PersonIdentifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IdentifierService
{
    /**
     * Next value of a named counter. Locks the counter row, so concurrent callers never
     * receive the same number. Must run inside the caller's transaction.
     */
    public function next(string $key): int
    {
        DB::table('identifier_sequences')->insertOrIgnore([
            'key' => $key, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('identifier_sequences')->where('key', $key)->lockForUpdate()->first();
        $next = $row->last_value + 1;
        DB::table('identifier_sequences')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => now()]);

        return $next;
    }

    /** Issue an identifier; any active value of the same type is superseded, never deleted. */
    public function issue(Person $person, string $type, string $value, ?string $reason = null): PersonIdentifier
    {
        return DB::transaction(function () use ($person, $type, $value, $reason) {
            $person->identifiers()->where('type', $type)->whereNull('superseded_at')
                ->get()->each->update(['superseded_at' => now(), 'reason' => $reason]);

            return $person->identifiers()->create([
                'type' => $type,
                'value' => $value,
                'issued_at' => now(),
                'reason' => $reason,
                'issued_by' => Auth::id(),
            ]);
        });
    }
}
