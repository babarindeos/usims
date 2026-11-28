<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleGrant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PersonService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/** Self-service applicant accounts (PRD 5.3). Every applicant is a person in the master record from the start. */
class ApplicantAccountService
{
    public const ROLE = 'applicant';

    public function __construct(private PersonService $persons, private NotificationService $notifications) {}

    public function register(array $data): User
    {
        $email = mb_strtolower($data['email']);
        if (Person::whereRaw('lower(email) = ?', [$email])->whereNull('merged_into_person_id')->whereHas('user')->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already uses this email. Sign in, or reset your password.']);
        }

        $user = DB::transaction(function () use ($data, $email) {
            // An existing person with this email and no account (for example a record Registry created) is reused, not duplicated.
            $person = Person::whereRaw('lower(email) = ?', [$email])->whereNull('merged_into_person_id')->first()
                ?? $this->persons->create([
                    'surname' => $data['surname'], 'first_name' => $data['first_name'], 'other_names' => $data['other_names'] ?? null,
                    'email' => $email, 'phone' => $data['phone'] ?? null,
                ]);

            $user = User::create([
                'person_id' => $person->id, 'name' => trim("{$data['first_name']} {$data['surname']}"), 'email' => $email,
                'password' => $data['password'], 'status' => 'active',
            ]);
            RoleGrant::create([
                'user_id' => $user->id, 'role_id' => Role::where('code', self::ROLE)->value('id'),
                'scope_type' => RoleGrant::SCOPE_GLOBAL, 'starts_at' => now(), 'reason' => 'Applicant self-registration',
            ]);

            return $user;
        });

        $this->sendVerification($user);

        return $user;
    }

    public function sendVerification(User $user): void
    {
        if ($user->email_verified_at) {
            return;
        }
        $link = URL::temporarySignedRoute('applicant.verify', now()->addDay(), ['user' => $user->uuid, 'hash' => sha1($user->email)]);
        $this->notifications->send('applicant.verify_email', $user, ['name' => $user->name, 'link' => $link]);
    }

    public function verify(User $user, string $hash): void
    {
        if (! hash_equals(sha1($user->email), $hash)) {
            throw ValidationException::withMessages(['link' => 'This verification link is not valid.']);
        }
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
