<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Str;

/** Adds a public UUID on create and uses it for route binding. */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
