<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function firstLoginUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'must_change_password' => true,
        'temporary_password_expires_at' => now()->addHour(),
    ], $attributes));

    (new OnboardTenant)->handle($user, [
        'name' => 'First login '.Str::random(8),
        'slug' => 'first-login-'.Str::lower(Str::random(8)),
    ]);

    return $user;
}

it('redirects a first-time user to the password setup screen', function (): void {
    $user = firstLoginUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('first-login-password.edit', absolute: false));
});

it('renders the password setup screen for a first-time user', function (): void {
    $user = firstLoginUser();

    $this->actingAs($user)
        ->get(route('first-login-password.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/first-login-password')
            ->has('passwordRules')
        );
});

it('updates the password and completes the first login state', function (): void {
    $user = firstLoginUser();
    $user->forceFill(['remember_token' => 'old-token'])->saveQuietly();
    $rememberToken = $user->remember_token;
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => 'first-login-password-session',
        'user_id' => $user->getKey(),
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($user)->put(route('first-login-password.update'), [
        'password' => 'New-password-123!',
        'password_confirmation' => 'New-password-123!',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and($user->temporary_password_expires_at)->toBeNull()
        ->and($user->first_login_at)->not->toBeNull();
    expect(Hash::check('New-password-123!', $user->password))->toBeTrue();
    expect($user->remember_token)->not->toBe($rememberToken)
        ->and(DB::table('sessions')->where('id', 'first-login-password-session')->exists())->toBeFalse();
});

it('blocks access when the temporary password has expired', function (): void {
    $user = firstLoginUser([
        'temporary_password_expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
