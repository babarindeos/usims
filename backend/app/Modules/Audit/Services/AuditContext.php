<?php

namespace App\Modules\Audit\Services;

use Illuminate\Support\Str;

/** Request-scoped details attached to every audit row. */
class AuditContext
{
    public ?string $reason = null;

    public ?string $ip = null;

    public ?string $userAgent = null;

    public string $requestId;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    /** Run a callback with a reason recorded on every audit row it produces. */
    public function withReason(?string $reason, callable $callback): mixed
    {
        $previous = $this->reason;
        $this->reason = $reason;
        try {
            return $callback();
        } finally {
            $this->reason = $previous;
        }
    }
}
