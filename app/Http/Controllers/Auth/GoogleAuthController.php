<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Services\PasswordServiceInterface;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use phpseclib4\Crypt\Hash;
use SebastianBergmann\Diff\Exception;

class GoogleAuthController
{
    public function __construct(private PasswordServiceInterface $passwordService) {}

    public function redirect(Request $request)
    {
        try {
            $redirectUrl = parse_url($request->headers->get('referer'), PHP_URL_PATH) ?? url('/');
            session(['redirect_url' => $redirectUrl]);
            return  Socialite::driver('google')->redirect();
        } catch (Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function callbackGoogle(): RedirectResponse
    {
        try {
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

            $redirectUrl = session('redirect_url', url('/'));
            if ($user->blocked) {
                return redirect($redirectUrl)->with('error', __('auth.blocked_user'));
            }
            Auth::login($user);
            session()->forget('redirect_url');
            return redirect($redirectUrl)->with('success', __('auth.logged_in'));
        } catch (Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

}
