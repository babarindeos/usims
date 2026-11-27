<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\UserRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('users.view');
        $term = trim((string) $request->query('q'));

        $users = User::query()->with('person')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('lower(email) like ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('lower(name) like ?', ['%'.mb_strtolower($term).'%'])))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('users.view');

        return new UserResource($user->load(['person', 'grants' => fn ($q) => $q->effective()->with(['role', 'orgUnit'])]));
    }

    public function store(UserRequest $request, NotificationService $notifications): UserResource
    {
        Gate::authorize('users.manage');
        $person = Person::where('uuid', $request->validated('person_id'))->firstOrFail();
        if ($person->user()->exists()) {
            throw ValidationException::withMessages(['person_id' => 'This person already has a user account.']);
        }

        $user = User::create([
            'person_id' => $person->id,
            'name' => $request->validated('name') ?? trim("{$person->first_name} {$person->surname}"),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'mfa_required' => $request->boolean('mfa_required'),
            'status' => 'active',
        ]);
        $notifications->send('account.created', $user, ['email' => $user->email]);

        return new UserResource($user->load('person'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        Gate::authorize('users.manage');
        if ($user->is($request->user()) && $request->has('status')) {
            throw ValidationException::withMessages(['status' => 'You cannot change the status of your own account.']);
        }
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password_changed_at'] = now();
        }
        $user->forceFill($data)->save();

        return new UserResource($user->load('person'));
    }
}
