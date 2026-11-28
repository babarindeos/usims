<?php

namespace App\Modules\Programmes\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\StudyMode;
use Illuminate\Support\Facades\DB;

class ProgrammeService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string,int|null>|null  $modes  study mode code => duration override
     * @param  int[]|null  $entryLevels
     */
    public function save(Programme $programme, array $attributes, ?array $modes, ?array $entryLevels): Programme
    {
        return DB::transaction(function () use ($programme, $attributes, $modes, $entryLevels) {
            $programme->fill($attributes)->save();

            if ($modes !== null) {
                $before = $programme->studyModes()->pluck('code')->sort()->values()->all();
                $ids = StudyMode::whereIn('code', array_keys($modes))->pluck('id', 'code');
                $programme->studyModes()->sync($ids->mapWithKeys(fn ($id, $code) => [$id => ['duration_years' => $modes[$code]]])->all());
                $after = collect(array_keys($modes))->sort()->values()->all();
                if ($before !== $after) {
                    $this->audit->record('study_modes_changed', $programme, ['study_modes' => $before], ['study_modes' => $after]);
                }
            }

            if ($entryLevels !== null) {
                $before = $programme->entryLevels()->pluck('level')->all();
                $programme->entryLevels()->delete();
                foreach (array_unique($entryLevels) as $level) {
                    $programme->entryLevels()->create(['level' => $level]);
                }
                $after = $programme->entryLevels()->pluck('level')->all();
                if ($before !== $after) {
                    $this->audit->record('entry_levels_changed', $programme, ['entry_levels' => $before], ['entry_levels' => $after]);
                }
            }

            return $programme->refresh();
        });
    }
}
