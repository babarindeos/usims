<?php

namespace App\Modules\Institution\Services;

use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrgUnitService
{
    public function create(array $data): OrgUnit
    {
        return DB::transaction(function () use ($data) {
            $parent = isset($data['parent_id']) ? OrgUnit::lockForUpdate()->findOrFail($data['parent_id']) : null;
            $unit = new OrgUnit($data + ['effective_from' => now()->toDateString(), 'path' => '/']);
            $unit->save();
            // The path needs the new id, so it is set after the first insert.
            $unit->path = ($parent?->path ?? '/').$unit->id.'/';
            $unit->saveQuietly();

            return $unit->refresh();
        });
    }

    /** Update a unit; moving it re-paths every unit below it so scopes follow the move. */
    public function update(OrgUnit $unit, array $data): OrgUnit
    {
        return DB::transaction(function () use ($unit, $data) {
            $moving = array_key_exists('parent_id', $data) && (int) $data['parent_id'] !== (int) $unit->parent_id;

            if ($moving) {
                $newParent = $data['parent_id'] ? OrgUnit::findOrFail($data['parent_id']) : null;
                if ($newParent && $unit->contains($newParent)) {
                    throw ValidationException::withMessages(['parent_id' => 'A unit cannot be moved under itself or its own sub-unit.']);
                }
                $oldPath = $unit->path;
                $newPath = ($newParent?->path ?? '/').$unit->id.'/';
                DB::update('UPDATE org_units SET path = ? || substr(path, ?) WHERE path LIKE ? AND id <> ?',
                    [$newPath, strlen($oldPath) + 1, $oldPath.'%', $unit->id]);
                $data['path'] = $newPath;
            }

            $unit->update($data);

            return $unit->refresh();
        });
    }
}
