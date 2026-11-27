<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('audit.view');
        $request->validate([
            'type' => ['nullable', 'string', 'max:150'], 'record_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'], 'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()->with('user:id,name,email')
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', 'like', '%'.$t))
            ->when($request->query('record_id'), fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->orderByDesc('id')
            ->cursorPaginate(min((int) $request->query('per_page', 50), 200));

        return response()->json([
            'data' => collect($logs->items())->map(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'record_type' => class_basename($l->auditable_type),
                'record_id' => $l->auditable_id,
                'before' => $l->before,
                'after' => $l->after,
                'reason' => $l->reason,
                'user' => $l->user ? ['name' => $l->user->name, 'email' => $l->user->email] : null,
                'ip_address' => $l->ip_address,
                'created_at' => $l->created_at->toIso8601String(),
            ]),
            'next_cursor' => $logs->nextCursor()?->encode(),
        ]);
    }
}
