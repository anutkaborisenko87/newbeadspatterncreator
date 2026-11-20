<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    /**
     * Open the password reset modal on the home page.
     */
    public function create(Request $request): RedirectResponse
    {
        $email = $request->query('email');
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('page', ['modal' => 'forgot-password'])->header('Referrer-Policy', 'no-referrer');
        }

        $request->session()->put('password_reset', [
            'id' => (string) Str::uuid(),
            'email' => $email,
            'token' => $request->route('token'),
        ]);

        return redirect()->route('page', ['modal' => 'reset-password'])->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $reset = $request->session()->get('password_reset');
        if (!$reset || $request->input('resetId') !== $reset['id']) {
            return redirect()->route('page', ['modal' => 'forgot-password']);
        }

        $password = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            ['email' => $reset['email'], 'token' => $reset['token'], ...$password],
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status == Password::PASSWORD_RESET) {
            $request->session()->forget('password_reset');
            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
