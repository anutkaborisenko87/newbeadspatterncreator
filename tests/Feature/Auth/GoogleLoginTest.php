<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        config(['services.google.redirect' => route('google.callback')]);
    }

    public function test_callback_without_a_matching_attempt_reopens_login(): void
    {
        $this->get(route('google.callback', ['state' => 'expired', 'code' => 'test']))
            ->assertRedirect(route('page', ['modal' => 'login']))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_two_tabs_can_start_google_login_and_the_first_can_complete_it_once(): void
    {
        $user = User::factory()->create(['login' => 'reader']);
        $responses = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'test-token'])),
            new Response(200, [], json_encode(['sub' => 'test-google-id', 'email' => $user->email, 'email_verified' => true])),
        ]);
        Socialite::extend('google', fn ($app) =>
            (new GoogleProvider($app['request'], 'client', 'secret', config('services.google.redirect')))
                ->setHttpClient(new Client(['handler' => HandlerStack::create($responses)]))
        );
        $first = $this->from(url('/gallery'))->get(route('google-auth'))->assertRedirect();
        parse_str(parse_url($first->headers->get('Location'), PHP_URL_QUERY), $query);
        Socialite::forgetDrivers();
        $this->from(url('/'))->get(route('google-auth'))->assertRedirect();
        Socialite::forgetDrivers();
        $callback = route('google.callback', ['state' => $query['state'], 'code' => 'test']);
        $this->get($callback)->assertRedirect(url('/gallery'));
        $this->assertAuthenticatedAs($user);
        $this->get($callback)->assertRedirect(route('page', ['modal' => 'login']));
    }
}
