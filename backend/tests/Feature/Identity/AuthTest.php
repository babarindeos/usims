<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_signs_in_and_sees_permissions(): void
    {
        $user = $this->userWith(['users.view']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('permissions', ['users.view']);

        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'successful' => true]);
    }

    public function test_wrong_password_is_refused_and_logged(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, DB::table('login_histories')->where(['user_id' => $user->id, 'successful' => false])->count());
    }

    public function test_suspended_account_cannot_sign_in(): void
    {
        $user = User::factory()->suspended()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(422);
        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'bad']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_protected_routes_need_sign_in(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401)->assertJsonPath('title', 'Not signed in');
    }
}
