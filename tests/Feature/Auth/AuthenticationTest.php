<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_url_redirects_to_the_shared_modal(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $this->withSession(['status' => 'Password reset complete'])
            ->get(route('login'))
            ->assertRedirect(route('page', ['modal' => 'login']))
            ->assertSessionHas('status', 'Password reset complete');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $user = User::factory()->create(['login' => 'login-user']);
        $destination = route('profile.index');
        $this->get($destination)->assertRedirect(route('login'));
        $this->get(route('login'))->assertRedirect(route('page', ['modal' => 'login']));

        $response = $this->from(route('page', ['modal' => 'login']))->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect($destination);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $user = User::factory()->create(['login' => 'login-user']);

        $origin = route('page', ['modal' => 'login']);
        $this->from($origin)->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect($origin)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $user = User::factory()->create(['login' => 'logout-user']);
        $publicPage = route('page', ['slug' => 'gallery', 'patterns_search' => 'beads']);

        $response = $this->actingAs($user)->from($publicPage)->get(route('logout'));

        $this->assertGuest();
        $response->assertRedirect($publicPage);
    }

    public function test_logout_from_auth_protected_pages_redirects_to_the_gallery(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $user = User::factory()->create(['login' => 'logout-user']);
        $gallery = route('page', ['slug' => 'gallery']);

        foreach ([route('profile.index'), route('editor-draft.edit', ['pattern' => 1])] as $protectedPage) {
            $this->actingAs($user)->from($protectedPage)->get(route('logout'))->assertRedirect($gallery);
            $this->assertGuest();
        }
    }
}
