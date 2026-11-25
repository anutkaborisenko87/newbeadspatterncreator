<?php

namespace App\Services;

use App\Contracts\Services\PatternReportServiceInterface;
use App\Models\Pattern;
use App\Models\PatternReport;
use App\Models\User;
use App\Notifications\PatternReportSubmitted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatternReportService implements PatternReportServiceInterface
{
    public function __construct(private PatternReport $model) {}
    /**
     * @inheritdoc
     */
    public function submit(Pattern $pattern, User $reporter, array $input): void
    {
        DB::transaction(function () use ($pattern, $reporter, $input) {
            $pattern = Pattern::publiclyShared()->lockForUpdate()->findOrFail($pattern->id);
            abort_if((int) $pattern->user_id === (int) $reporter->id, 403);
            if ($this->model::where('pattern_id', $pattern->id)->where('reporter_id',
                $reporter->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['topic' => __('pattern_reports.pending')]);
            }
            $report = $this->model::create([
                'pattern_id' => $pattern->id, 'reporter_id' => $reporter->id, 'author_id' => $pattern->user_id,
                'title' => $pattern->title, 'preview' => $pattern->preview_image,
                'drawing' => $pattern->hasEditorDrawing() ? Arr::only($pattern->pattern_data, ['gridWidth', 'gridHeight', 'drawnCells', 'view']) : null,
                'topic' => $input['topic'], 'message' => $input['message'],
            ]);
            foreach ([$reporter, $pattern->user] as $recipient) {
                $recipient->notify((new PatternReportSubmitted($report))->locale(app()->getLocale())->afterCommit());
            }
        });
    }

    public function review(PatternReport $record, string $status, bool $block): void
    {
        DB::transaction(function () use ($record, $status, $block) {
            $report = PatternReport::query()->lockForUpdate()->findOrFail($record->id);
            if ($report->status !== 'pending' || !in_array($status, ['upheld', 'dismissed'], true) || ($block && $status !== 'upheld')) {
                throw ValidationException::withMessages(['status' => __('reports.review_error')]);
            }
            if ($block && $report->pattern_id) {
                Pattern::whereKey($report->pattern_id)->update(['publication_blocked' => true, 'shared' => false, 'published' => true]);
            }
            $report->update(['status' => $status]);
        });
    }
}
