<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PatternServiceInterface;
use App\Contracts\Services\ProfileServiceInterface;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileServiceInterface $profileService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Page', $this->profileService->getProfilePage());
    }

    public function drafts(Request $request, PatternServiceInterface $patterns): \Symfony\Component\HttpFoundation\Response
    {
        $title = __('main.profile_drafts_title');

        return Inertia::render('Page', ['data' => [
            'title' => $title,
            'sections' => [[
                'slug' => 'drafts',
                'standalone' => true,
                'section_title' => $title,
                'labels' => __('main.profile_draft_cards'),
                'gallery_items' => $patterns->getAuthorPatterns($request->user()->getKey(), draft: true, perPage: 9),
            ]],
        ]])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Display the user's profile form.
     */
    public function edit(): JsonResponse
    {
        return response()->json($this->profileService->getAuthUserData()->toArray());
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
