<?php

namespace App\Modules\Audit\Models;

use App\Modules\Audit\Services\AuditLogger;

/** Writes an audit row with before and after values on every create, update and delete. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->record('created', $model, null, $model->attributesToArray());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $before = array_intersect_key($model->getOriginal(), $changes);
            app(AuditLogger::class)->record('updated', $model, $before, $changes);
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->record('deleted', $model, $model->attributesToArray(), null);
        });
    }
}
