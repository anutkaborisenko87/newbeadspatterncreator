<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->back()->with('success', __('auth.logged_in'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $previousUrl = url()->previous();
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $protectedPaths = config('protected_paths.logout_redirect_exceptions', []);
        $isProtected = false;
        foreach ($protectedPaths as $path) {
            if (strpos($previousUrl, $path) !== false) {
                $isProtected = true;
                break;
            }
        }

        $redirectUrl = $isProtected ? '/' : $previousUrl;

        return redirect($redirectUrl)->with('success', __('auth.logged_out'));
    }
}
