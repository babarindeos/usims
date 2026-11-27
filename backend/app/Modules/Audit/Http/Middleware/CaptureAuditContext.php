<?php

namespace App\Modules\Audit\Http\Middleware;

use App\Modules\Audit\Services\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Copies IP, user agent and the optional X-Audit-Reason header into the audit context. */
class CaptureAuditContext
{
    public function __construct(private AuditContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->ip = $request->ip();
        $this->context->userAgent = $request->userAgent();
        $reason = $request->header('X-Audit-Reason') ?? $request->input('audit_reason');
        $this->context->reason = is_string($reason) ? mb_substr($reason, 0, 500) : null;

        $response = $next($request);
        $response->headers->set('X-Request-Id', $this->context->requestId);

        return $response;
    }
}
