<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Session sign-in for the SPA (Sanctum cookie). Local passwords serve development and institutions
 * without an identity provider; the Keycloak OIDC flow is added alongside it.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        $failure = match (true) {
            ! $user || ! $user->password || ! Auth::guard('web')->validate($credentials) => 'invalid_credentials',
            ! $user->isActive() => 'account_'.$user->status,
            default => null,
        };

        $this->logAttempt($request, $user, $failure);

        if ($failure) {
            throw ValidationException::withMessages(['email' => $failure === 'invalid_credentials'
                ? 'These credentials do not match our records.'
                : 'This account is not active. Contact the ICT helpdesk.']);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return $this->me($request->setUserResolver(fn () => $user), app(AccessService::class));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['status' => 'signed_out']);
    }

    /** The signed-in user, their grants and permission codes (the SPA builds navigation from these). */
    public function me(Request $request, AccessService $access): JsonResponse
    {
        $user = $request->user()->load(['person', 'grants' => fn ($q) => $q->effective()->with(['role', 'orgUnit'])]);

        return response()->json([
            'data' => (new UserResource($user))->resolve(),
            'permissions' => $access->permissionCodes($user),
        ]);
    }

    private function logAttempt(Request $request, ?User $user, ?string $failure): void
    {
        DB::table('login_histories')->insert([
            'user_id' => $user?->id,
            'email' => mb_substr((string) $request->input('email'), 0, 255),
            'successful' => $failure === null,
            'failure_reason' => $failure,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
