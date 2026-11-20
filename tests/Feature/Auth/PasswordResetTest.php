<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $this->get(route('password.request'))
            ->assertRedirect(route('page', ['modal' => 'forgot-password']));
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);

        $user = User::factory()->create(['login' => 'password-reset-user']);
        $origin = route('page', ['slug' => 'gallery']);
        $this->from($origin)->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect($origin)->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_redirects_to_the_home_modal_and_keeps_the_token_in_session(): void
    {
        Notification::fake();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);

        $user = User::factory()->create(['login' => 'password-reset-user']);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]));

            $response->assertRedirect(route('page', ['modal' => 'reset-password']))
                ->assertSessionHas('password_reset.token', $notification->token)
                ->assertSessionHas('password_reset.email', $user->email);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);

        $user = User::factory()->create(['login' => 'password-reset-user']);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]));
            $resetId = session('password_reset.id');
            $response = $this->post(route('password.store'), [
                'resetId' => $resetId,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertSessionMissing('password_reset')
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
