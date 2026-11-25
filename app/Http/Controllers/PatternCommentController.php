<?php

namespace App\Http\Controllers;

use App\DTO\PatternGalleryDTO;
use App\Models\Comment;
use App\Models\Pattern;
use App\Models\User;
use App\Services\PatternImageExporter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PatternCommentController extends Controller
{
    /**
     * Displays the discussion page for a specific pattern.
     *
     * @param Pattern $pattern The pattern for which the discussion is to be displayed.
     * @return Response The Inertia response containing the discussion page data.
     */
    public function show(Pattern $pattern): Response
    {
        $encodedId = encodeModelId($pattern->id);
        $moderator = auth()->check() && (int) auth()->id() === (int) $pattern->user_id;
        return Inertia::render('PatternDiscussion', [
            'pattern' => PatternGalleryDTO::fromModel($pattern, includeCopyAction: true)->toArray(),
            'initialComments' => fn () => $this->comments($pattern->id),
            'moderator' => $moderator,
            'blockedCommenters' => fn () => $moderator ? $pattern->blockedCommenters()->orderBy('login')->get(['users.id', 'login'])->makeHidden('pivot') : [],
            'commentingBlocked' => fn () => auth()->check() && $pattern->blockedCommenters()->whereKey(auth()->id())->exists(),
            'reportLabels' => __('reports'),
            'feedUrl' => route('patterns.comments.feed', $encodedId, absolute: false),
            'socketKey' => config('broadcasting.connections.reverb.key'),
            'channel' => 'patterns.'.$encodedId.'.comments',
            'diagramUrl' => $pattern->hasEditorDrawing() ? route('patterns.comments.diagram', $encodedId, absolute: false) : null,
            'labels' => [...__('main.discussion'), 'copy' => __('main.author_gallery.copy')],
        ]);
    }

    /**
     * Displays the diagram image for a specific pattern.
     *
     * @param Pattern $pattern The pattern for which the diagram is to be displayed.
     * @param PatternImageExporter $exporter The service responsible for exporting the pattern image.
     * @return StreamedResponse The streamed response containing the diagram image.
     */
    public function diagram(Pattern $pattern, PatternImageExporter $exporter): StreamedResponse
    {
        abort_unless($pattern->isPubliclyShared() && $pattern->hasEditorDrawing(), 404);
        $drawing = $pattern->pattern_data;
        $view = $drawing['view'] ?? [];
        $image = $exporter->render($drawing, (bool) ($view['showDictation'] ?? false),
            ($view['bottomUp'] ?? true) ? 'bottom_up' : 'top_down',
            ($view['leftToRight'] ?? true) ? 'left_to_right' : 'right_to_left');
        return response()->stream(fn () => imagepng($image), 200, ['Content-Type' => 'image/png']);
    }

    /**
     * Displays the feed of comments for a specific pattern.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern for which the comment feed is to be displayed.
     * @return JsonResponse The JSON response containing the comment feed data.
     */
    public function feed(Request $request, Pattern $pattern): JsonResponse
    {
        $request->validate(['after' => ['sometimes', 'integer', 'min:0']]);
        return response()->json(['comments' => $this->comments($pattern->id, $request->integer('after'))])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Stores a new comment for a specific pattern.
     *
     * @param Request $request The current HTTP request containing the comment data.
     * @param Pattern $pattern The pattern for which the comment is to be stored.
     * @return JsonResponse The JSON response containing the stored comment data.
     * @throws Throwable
     */
    public function store(Request $request, Pattern $pattern): JsonResponse
    {
        abort_unless($pattern->isPubliclyShared(), 404);
        $input = $request->validate([
            'comment' => ['required', 'string', 'max:4000'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $comment = DB::transaction(function () use ($pattern, $request, $input) {
            Pattern::query()->publiclyShared()->select('id')->lockForUpdate()->findOrFail($pattern->id);
            $this->ensureCanComment($pattern, $request->user());
            $parent = $input['parent_id'] ?? null;
            if ($parent !== null && !$pattern->comments()->where('approved', true)->whereKey($parent)->exists()) {
                throw ValidationException::withMessages(['parent_id' => __('main.discussion.invalid_reply')]);
            }
            return $pattern->comments()->create([
                ...$input, 'user_id' => $request->user()->id, 'approved' => true,
            ]);
        });
        $comment->setRelation('user', $request->user());
        $payload = ['comment' => $this->entry($comment)];
        $this->notify($pattern, 'comment.created', $payload);
        return response()->json($payload, 201);
    }

    /**
     * Updates an existing comment for a specific pattern.
     *
     * @param Request $request The current HTTP request containing the updated comment data.
     * @param Pattern $pattern The pattern for which the comment is to be updated.
     * @param int $commentId The ID of the comment to be updated.
     * @return JsonResponse The JSON response containing the updated comment data.
     * @throws Throwable
     */
    public function update(Request $request, Pattern $pattern, int $commentId): JsonResponse
    {
        $comment = DB::transaction(function () use ($request, $pattern, $commentId) {
            // Reply creation takes the same lock before inserting a child.
            Pattern::query()->publiclyShared()->select('id')->lockForUpdate()->findOrFail($pattern->id);
            $comment = $this->ownedComment($request, $pattern, $commentId);
            $this->ensureCanComment($pattern, $request->user());
            if ($comment->replies()->exists()) {
                throw ValidationException::withMessages(['comment' => __('main.discussion.edit_locked')]);
            }
            $comment->update($request->validate(['comment' => ['required', 'string', 'max:4000']]));
            return $comment;
        });
        $payload = ['comment' => $this->entry($comment)];
        $this->notify($pattern, 'comment.updated', $payload);
        return response()->json($payload);
    }

    /**
     * Deletes an existing comment for a specific pattern.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern for which the comment is to be deleted.
     * @param int $commentId The ID of the comment to be deleted.
     * @return \Illuminate\Http\Response The response indicating the result of the deletion.
     * @throws Throwable
     */
    public function destroy(Request $request, Pattern $pattern, int $commentId): \Illuminate\Http\Response
    {
        $comment = $pattern->comments()->where('approved', true)->findOrFail($commentId);
        $viewer = (int) $request->user()->id;
        abort_unless($viewer === (int) $comment->user_id || $viewer === (int) $pattern->user_id, 403);
        $comment->delete();
        $this->notify($pattern, 'comment.deleted', ['id' => $commentId]);
        return response()->noContent();
    }

    /**
     * Sets the block status for a specific user on a specific pattern.
     *
     * @param Request $request The current HTTP request containing the block status.
     * @param Pattern $pattern The pattern for which the block status is to be set.
     * @param User $user The user for whom the block status is to be set.
     * @return \Illuminate\Http\Response The response indicating the result of the block status update.
     * @throws Throwable
     */
    public function setBlock(Request $request, Pattern $pattern, User $user): \Illuminate\Http\Response
    {
        abort_unless((int) $pattern->user_id === (int) $request->user()->id, 403);
        abort_if($user->is($request->user()), 403);
        $request->validate(['blocked' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $pattern, $user) {
            Pattern::query()->publiclyShared()->lockForUpdate()->findOrFail($pattern->id);
            if ($request->boolean('blocked')) {
                $pattern->blockedCommenters()->syncWithoutDetaching([$user->id]);
            } else {
                $pattern->blockedCommenters()->detach($user->id);
            }
        });
        $this->notify($pattern, 'permissions.changed');
        return response()->noContent();
    }

    /**
     * Ensures that the given user is allowed to comment on the specified pattern.
     *
     * @param Pattern $pattern The pattern for which the comment permission is to be checked.
     * @param User $user The user for whom the comment permission is to be checked.
     * @throws HttpResponseException If the user is blocked from commenting on the pattern.
     */
    private function ensureCanComment(Pattern $pattern, User $user): void
    {
        abort_if($pattern->blockedCommenters()->whereKey($user->id)->exists(), 403, __('main.discussion.blocked_notice'));
    }

    /**
     * Retrieves the comment owned by the current user for the specified pattern and comment ID.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern for which the comment is to be retrieved.
     * @param int $id The ID of the comment to be retrieved.
     * @return Comment The comment owned by the current user.
     * @throws HttpResponseException If the comment is not owned by the current user.
     */
    private function ownedComment(Request $request, Pattern $pattern, int $id): Comment
    {
        $comment = $pattern->comments()->where('approved', true)->findOrFail($id);
        abort_unless((int) $comment->user_id === (int) $request->user()->id, 403);
        return $comment;
    }

    /**
     * Notifies the specified pattern of a comment-related event.
     *
     * @param Pattern $pattern The pattern for which the notification is to be sent.
     * @param string $event The event name to be notified.
     * @param array $payload The payload data to be sent with the notification.
     */
    private function notify(Pattern $pattern, string $event, array $payload = []): void
    {
        // A delivery failure must not turn a committed comment into a failed submission.
        rescue(fn () => Broadcast::connection()->broadcast(
            ['patterns.'.encodeModelId($pattern->id).'.comments'], $event, $payload
        ));
    }

    /**
     * Retrieves the comments for a specific pattern, optionally filtered by an "after" comment ID.
     *
     * @param int $pattern The ID of the pattern for which comments are to be retrieved.
     * @param int $after The ID of the comment after which to retrieve comments (optional).
     * @return Collection The collection of comments for the specified pattern.
     */
    private function comments(int $pattern, int $after = 0): Collection
    {
        return Comment::where('pattern_id', $pattern)->where('approved', true)->where('id', '>', $after)
            ->withExists(['replies as has_hidden_replies' => fn ($query) => $query->where('approved', false)])
            ->with('user:id,login')->orderBy('id')->get()->map(fn (Comment $comment) => $this->entry($comment));
    }

    /**
     * Prepares the comment entry data for JSON response.
     *
     * @param Comment $comment The comment for which the entry data is to be prepared.
     * @return array The prepared comment entry data.
     */
    private function entry(Comment $comment): array
    {
        return ['id' => $comment->id, 'parent_id' => $comment->parent_id, 'created_at' => $comment->created_at?->toISOString(),
            'has_hidden_replies' => (bool) $comment->has_hidden_replies,
            'author_id' => $comment->user_id, 'login' => $comment->user->login, 'text' => $comment->comment];
    }
}
