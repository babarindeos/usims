<?php

use App\Modules\Audit\Http\Middleware\CaptureAuditContext;
use App\Modules\Audit\Services\AuditContext;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(append: [CaptureAuditContext::class]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // RFC 9457 problem details for every API error; no internals outside debug mode.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            [$status, $title, $detail] = match (true) {
                $e instanceof ValidationException => [422, 'Validation failed', $e->getMessage()],
                $e instanceof AuthenticationException => [401, 'Not signed in', 'Sign in to continue.'],
                $e instanceof AuthorizationException => [403, 'Forbidden', $e->getMessage() ?: 'You do not have permission for this action.'],
                $e instanceof ModelNotFoundException => [404, 'Not found', 'The requested record does not exist.'],
                $e instanceof WorkflowException => [$e->status, $e->status === 403 ? 'Forbidden' : 'Workflow rule', $e->getMessage()],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'Request failed', $e->getMessage() ?: 'Request failed.'],
                default => [500, 'Server error', config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.'],
            };
            if ($status === 500 && config('app.debug') && ! app()->runningUnitTests()) {
                return null; // let the debug page render in local development
            }
            $body = [
                'type' => 'about:blank',
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
                'request_id' => app(AuditContext::class)->requestId,
            ];
            if ($e instanceof ValidationException) {
                $body['errors'] = $e->errors();
            }

            return response()->json($body, $status, ['Content-Type' => 'application/problem+json']);
        });
    })->create();
