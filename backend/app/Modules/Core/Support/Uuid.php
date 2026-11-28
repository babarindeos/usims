<?php

namespace App\Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;

/** Public APIs speak UUIDs; this turns a validated UUID back into the internal key. */
final class Uuid
{
    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public static function find(string $class, ?string $uuid): ?Model
    {
        return $uuid ? $class::where('uuid', $uuid)->firstOrFail() : null;
    }
}
