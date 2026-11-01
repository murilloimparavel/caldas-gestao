<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/login'),
        );
    }

    public function test_admin_login_screen_has_a_distinct_inertia_entry()
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/admin-login')
            ->where('canResetPassword', true),
        );
    }

    public function test_admin_login_does_not_resolve_tenant_context_for_an_authenticated_super_administrator(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $membershipQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$membershipQueries): void {
            if (str_contains(strtolower($query->sql), 'membership')) {
                $membershipQueries[] = $query->sql;
            }
        });

        $response = $this->actingAs($user)->get(route('admin.login'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/admin-login')
            ->where('workspace', null),
        );
        $this->assertSame([], $membershipQueries);
    }

    public function test_admin_login_redirects_super_administrators_to_the_admin_dashboard()
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->get(route('admin.login'));
        $response = $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $response->assertSessionMissing('admin_login_intent');
        $this->assertAuthenticatedAs($user);
    }

    public function test_dedicated_admin_login_route_redirects_super_administrators_without_session_intent(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_login_route_with_remember_checked_keeps_super_administrators_on_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->get(route('admin.login'));
        $this->withSession(['admin_login_intent' => false]);
        $response = $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_login_rejects_regular_users()
    {
        $user = User::factory()->create();

        $this->get(route('admin.login'));
        $response = $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login', absolute: false));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_guests_are_sent_to_the_dedicated_login_entry()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_super_administrators_using_the_regular_login_keep_the_default_redirect(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_with_a_canonically_equivalent_email(): void
    {
        $user = User::factory()->create(['email' => 'Owner@Example.com']);

        $response = $this->post(route('login.store'), [
            'email' => '  OWNER@example.COM  ',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
