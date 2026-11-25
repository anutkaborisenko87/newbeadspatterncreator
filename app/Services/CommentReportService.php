<?php

namespace App\Services;

use App\Contracts\Services\CommentReportServiceInterface;
use App\Models\CommentReport;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommentReportService implements CommentReportServiceInterface
{
    public function __construct(private CommentReport $model)
    {}

    public function create(User $reporter, Pattern $pattern, int $commentId, array $input): void
    {
        DB::transaction(function () use ($reporter, $pattern, $commentId, $input) {
            $comment = $pattern->comments()->where('approved', true)->lockForUpdate()->findOrFail($commentId);
            abort_if((int) $comment->user_id === (int) $reporter->id, 403);
            $paths = [];
            foreach (($input['screenshots'] ?? []) as $file) {
                $path = $file->store('report-evidence', 'local');
                abort_if($path === false, 500);
                DB::afterRollBack(fn () => Storage::disk('local')->delete($path));
                $paths[] = $path;
            }
            $this->model::create([
                'reporter_id' => $reporter->id, 'reported_user_id' => $comment->user_id,
                'reported_login' => $comment->user->login, 'comment_id' => $comment->id,
                'pattern_title' => $pattern->title, 'comment_text' => $comment->comment,
                'topic' => $input['topic'], 'message' => $input['message'],
                'locale' => app()->getLocale(), 'evidence' => $paths,
            ]);
        });
    }
}
