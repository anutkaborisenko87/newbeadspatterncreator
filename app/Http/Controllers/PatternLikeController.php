<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PatternLikeController extends Controller
{
    /**
     * Updates the like status of a pattern for the current user.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern for which the like status is to be updated.
     * @return RedirectResponse A redirect response back to the previous page.
     * @throws ValidationException|Throwable If the request validation fails.
     */
    public function update(Request $request, Pattern $pattern): RedirectResponse
    {
        $request->validate(['liked' => ['required', 'boolean']]);
        $viewer = $request->user()->getKey();

        DB::transaction(function () use ($request, $pattern, $viewer) {
            $pattern = Pattern::query()->publiclyShared()->lockForUpdate()->findOrFail($pattern->getKey());
            abort_if((int) $pattern->user_id === $viewer, 403);

            if ($request->boolean('liked')) {
                $pattern->likes()->firstOrCreate(['user_id' => $viewer]);
            } else {
                $pattern->likes()->where('user_id', $viewer)->delete();
            }
        });

        return back(303);
    }

    /**
     * Retrieves the list of users who have liked a specific pattern.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern for which the list of users is to be retrieved.
     * @return JsonResponse A JSON response containing the list of users who liked the pattern.
     */
    public function index(Request $request, Pattern $pattern): JsonResponse
    {
        abort_unless((int) $pattern->user_id === $request->user()->getKey()
            && !$pattern->isPrivateCopy() && ($pattern->published || $pattern->shared), 404);

        $users = User::query()->whereIn('id', $pattern->likes()->select('user_id'))
            ->orderBy('login')->get(['id', 'login'])->map(fn (User $user) => [
                'login' => $user->login,
                'url' => route('gallery.author', ['userId' => encodeModelId($user->getKey())], absolute: false),
            ]);

        return response()->json(['users' => $users])->header('Cache-Control', 'private, no-store');
    }
}
