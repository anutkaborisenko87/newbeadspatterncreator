<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Services\PasswordServiceInterface;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use GuzzleHttp\Exception\GuzzleException;

class GoogleAuthController
{
    public function __construct(private PasswordServiceInterface $passwordService) {}

    public function redirect(Request $request)
    {
        $origin = Request::create(config('services.google.redirect'))->getSchemeAndHttpHost();
        if ($request->getSchemeAndHttpHost() !== $origin) {
            return redirect()->away($origin.$request->getPathInfo());
        }
        $previous = $request->headers->get('referer', '');
        $returnUrl = str_starts_with($previous, $origin.'/') ? $previous : $origin.'/';
        $redirect = Socialite::driver('google')->redirect();
        // Socialite keeps one state; keep each tab's pending attempt in this browser session.
        $attempts = array_filter($request->session()->get('google_attempts', []),
            fn ($attempt) => $attempt['expires'] > time());
        $state = $request->session()->get('state');
        $attempts[$state] = ['state' => $state, 'return' => $returnUrl, 'expires' => time() + 900];
        $request->session()->put('google_attempts', array_slice($attempts, -8, preserve_keys: true));
        return $redirect;
    }

    public function callbackGoogle(Request $request): RedirectResponse
    {
        try {
            $state = $request->query('state');
            $attempts = $request->session()->get('google_attempts', []);
            $attempt = is_string($state) ? ($attempts[$state] ?? null) : null;
            if (!$attempt || $attempt['expires'] <= time()) {
                throw new InvalidStateException();
            }
            unset($attempts[$state]);
            $request->session()->put('google_attempts', $attempts);
            $request->session()->put('state', $attempt['state']);
            if ($request->has('error') || !$request->filled('code')) {
                $request->session()->forget('state');
                return $this->loginError();
            }
            $googleUser = Socialite::driver('google')->user();
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $user = User::where('email', $googleUser->getEmail())->first();
            }

            if (!$user) {
                $generatedPassword = $this->passwordService->generatePassword();
                $email = $googleUser->getEmail();

                $baseLogin = Str::before($email, '@');
                $login = $baseLogin;
                $counter = 1;

                while (User::where('login', $login)->exists()) {
                    $login = $baseLogin . $counter;
                    $counter++;
                }

                $nameParts = explode(' ', trim($googleUser->getName()), 2);

                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';

                $user = User::create([
                    'login' => $login,
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'password' => $this->passwordService->hashPassword($generatedPassword),
                ]);
            } elseif (!$user->google_id) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            }
            if (!$user->email_verified_at) {
                $user->update(['email_verified_at' => now()]);
            }

            $redirectUrl = $attempt['return'];
            if ($user->blocked) {
                return redirect($redirectUrl)->with('error', __('auth.blocked_user'));
            }
            Auth::login($user);
            return redirect()->intended($redirectUrl)->with('success', __('auth.logged_in'));
        } catch (InvalidStateException|GuzzleException) {
            return $this->loginError();
        }
    }

    private function loginError(): RedirectResponse
    {
        return redirect()->route('page', ['modal' => 'login'])->with('error', __('auth.google_retry'));
    }
}
