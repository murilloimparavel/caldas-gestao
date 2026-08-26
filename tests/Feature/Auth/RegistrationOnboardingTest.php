<?php

use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

it('provisions an operational owner workspace during registration', function (): void {
    $this->skipUnlessFortifyHas(Features::registration());

    $response = $this->post(route('register.store'), [
        'name' => 'Test Owner',
        'email' => 'owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::query()->sole();
    $tenant = Tenant::query()->sole();
    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $user->getKey())
        ->sole();
    $ownerRole = Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('key', 'owner')
        ->sole();

    expect($tenant->name)->toBe('Test Owner Workspace')
        ->and($tenant->units()->count())->toBe(1)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($membership->status->value)->toBe('invited')
        ->and(MembershipRole::query()
            ->where('membership_id', $membership->getKey())
            ->where('role_id', $ownerRole->getKey())
            ->whereNull('revoked_at')
            ->exists())->toBeTrue();

    expect(fn () => TenantContext::forUser($user, $tenant->getKey()))
        ->toThrow(AuthorizationException::class);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1($user->email)],
    );

    $this->get($verificationUrl)->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $membership->refresh();
    $permissions = app(AuthorizationService::class)->permissions(
        $user->fresh(),
        TenantContext::forUser($user->fresh(), $tenant->getKey()),
    );

    expect($membership->status->value)->toBe('active');

    expect($permissions)
        ->toContain('calendar.view')
        ->toContain('customer.manage')
        ->toContain('financial.view');
});
