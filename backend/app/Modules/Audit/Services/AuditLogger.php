<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /** Attributes never written to the audit trail. */
    private const REDACT = ['password', 'remember_token', 'oidc_subject'];

    public function __construct(private AuditContext $context) {}

    public function record(string $action, Model|string $subject, ?array $before = null, ?array $after = null, ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => is_string($subject) ? $subject : $subject->getMorphClass(),
            'auditable_id' => is_string($subject) ? null : $subject->getKey(),
            'before' => $this->clean($before),
            'after' => $this->clean($after),
            'reason' => $reason ?? $this->context->reason,
            'ip_address' => $this->context->ip,
            'user_agent' => $this->context->userAgent ? mb_substr($this->context->userAgent, 0, 500) : null,
            'request_id' => $this->context->requestId,
        ]);
    }

    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach (self::REDACT as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }
        unset($values['updated_at']);

        return $values;
    }
}
