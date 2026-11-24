<?php

namespace App\Http\Controllers;

use App\DTO\PatternGalleryDTO;
use App\Models\Comment;
use App\Models\Pattern;
use App\Services\PatternImageExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatternCommentController extends Controller
{
    public function show(Pattern $pattern): Response
    {
        $encodedId = encodeModelId($pattern->id);
        return Inertia::render('PatternDiscussion', [
            'pattern' => PatternGalleryDTO::fromModel($pattern, includeCopyAction: true)->toArray(),
            'initialComments' => $this->comments($pattern->id),
            'feedUrl' => route('patterns.comments.feed', $encodedId, absolute: false),
            'socketKey' => config('broadcasting.connections.reverb.key'),
            'channel' => 'patterns.'.$encodedId.'.comments',
            'diagramUrl' => $pattern->hasEditorDrawing() ? route('patterns.comments.diagram', $encodedId, absolute: false) : null,
            'labels' => [...__('main.discussion'), 'copy' => __('main.author_gallery.copy')],
        ]);
    }

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

    public function feed(Request $request, Pattern $pattern): JsonResponse
    {
        $request->validate(['after' => ['sometimes', 'integer', 'min:0']]);
        return response()->json(['comments' => $this->comments($pattern->id, $request->integer('after'))])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, Pattern $pattern): JsonResponse
    {
        abort_unless($pattern->isPubliclyShared(), 404);
        $input = $request->validate([
            'comment' => ['required', 'string', 'max:4000'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $comment = DB::transaction(function () use ($pattern, $request, $input) {
            Pattern::query()->publiclyShared()->select('id')->lockForUpdate()->findOrFail($pattern->id);
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

    public function update(Request $request, Pattern $pattern, int $commentId): JsonResponse
    {
        $comment = DB::transaction(function () use ($request, $pattern, $commentId) {
            // Reply creation takes the same lock before inserting a child.
            Pattern::query()->publiclyShared()->select('id')->lockForUpdate()->findOrFail($pattern->id);
            $comment = $this->ownedComment($request, $pattern, $commentId);
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

    public function destroy(Request $request, Pattern $pattern, int $commentId): \Illuminate\Http\Response
    {
        $this->ownedComment($request, $pattern, $commentId)->delete();
        $this->notify($pattern, 'comment.deleted', ['id' => $commentId]);
        return response()->noContent();
    }

    private function ownedComment(Request $request, Pattern $pattern, int $id): Comment
    {
        $comment = $pattern->comments()->where('approved', true)->findOrFail($id);
        abort_unless((int) $comment->user_id === (int) $request->user()->id, 403);
        return $comment;
    }

    private function notify(Pattern $pattern, string $event, array $payload = []): void
    {
        // A delivery failure must not turn a committed comment into a failed submission.
        rescue(fn () => Broadcast::connection()->broadcast(
            ['patterns.'.encodeModelId($pattern->id).'.comments'], $event, $payload
        ));
    }

    private function comments(int $pattern, int $after = 0): Collection
    {
        return Comment::where('pattern_id', $pattern)->where('approved', true)->where('id', '>', $after)
            ->withExists(['replies as has_hidden_replies' => fn ($query) => $query->where('approved', false)])
            ->with('user:id,login')->orderBy('id')->get()->map(fn (Comment $comment) => $this->entry($comment));
    }

    private function entry(Comment $comment): array
    {
        return ['id' => $comment->id, 'parent_id' => $comment->parent_id, 'created_at' => $comment->created_at?->toISOString(),
            'has_hidden_replies' => (bool) $comment->has_hidden_replies,
            'author_id' => $comment->user_id, 'login' => $comment->user->login, 'text' => $comment->comment];
    }
}
