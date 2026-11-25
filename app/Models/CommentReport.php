<?php

namespace App\Models;

use App\Notifications\CommentReportReviewed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentReport extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['evidence' => 'array'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function review(string $status, string $decision): void
    {
        DB::transaction(function () use ($status, $decision) {
            $report = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($report->status !== 'pending' || !in_array($status, ['upheld', 'dismissed'], true) || trim($decision) === '') {
                throw ValidationException::withMessages(['decision' => __('reports.review_error')]);
            }
            $report->update(['status' => $status, 'decision' => $decision]);
            $report->reporter->notify((new CommentReportReviewed($report))->locale($report->locale)->afterCommit());
        });
    }
}
