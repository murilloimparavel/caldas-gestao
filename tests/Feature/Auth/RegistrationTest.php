<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->sole();

        $this->assertSame('test@example.com', $user->email_normalized);

        Notification::assertSentTo($user, WelcomeUser::class);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_welcome_notification_uses_plain_portuguese_branding(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->sole();

        Notification::assertSentTo($user, WelcomeUser::class, function (WelcomeUser $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Bem-vindo ao '.config('branding.name')
                && $mail->greeting === 'Olá, Ana!'
                && $mail->actionText === 'Acessar o sistema';
        });
    }

    public function test_registration_rejects_an_email_that_only_differs_by_case_and_whitespace(): void
    {
        User::factory()->create(['email' => 'Owner@Example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Duplicate User',
            'email' => '  owner@example.COM  ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_registration_rejects_email_addresses_longer_than_320_characters(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Long Email',
            'email' => str_repeat('a', 309).'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, User::query()->count());
    }
}
