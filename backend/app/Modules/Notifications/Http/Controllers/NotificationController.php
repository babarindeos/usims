<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserNotification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => collect($items->items())->map(fn ($n) => [
                'id' => $n->uuid, 'title' => $n->title, 'body' => $n->body, 'link' => $n->link,
                'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread_count' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage()],
        ]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['status' => 'ok']);
    }

    public function readAll(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
