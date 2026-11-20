<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuthenticatedSessionController extends Controller
{
    /**
     * Open the shared sign-in modal.
     */
    public function create(Request $request): RedirectResponse
    {
        $request->session()->keep('status');

        return redirect()->route('page', ['modal' => 'login']);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $home = route('page');
        $previous = url()->previous($home);
        $fallback = Request::create($previous)->query('modal') === 'login' ? $home : $previous;

        return redirect()->intended($fallback)->with('success', __('auth.logged_in'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $redirectUrl = $this->logoutDestination($request);
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect($redirectUrl)->with('success', __('auth.logged_out'));
    }

    private function logoutDestination(Request $request): string
    {
        $gallery = route('page', ['slug' => 'gallery'], absolute: false);
        $previousUrl = url()->previous($gallery);
        $previous = Request::create($previousUrl);

        if ($previous->getSchemeAndHttpHost() !== $request->getSchemeAndHttpHost()) {
            return $gallery;
        }

        try {
            $route = Route::getRoutes()->match($previous);
        } catch (HttpExceptionInterface) {
            return $gallery;
        }

        foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && is_a(explode(':', $middleware, 2)[0], Authenticate::class, true)) {
                return $gallery;
            }
        }

        return $previousUrl;
    }
}
