<?php

namespace App\Http\Controllers;

use App\Contracts\Services\CommentReportServiceInterface;
use App\Http\Requests\StoreCommentReportRequest;
use App\Models\CommentReport;
use App\Models\Pattern;
use App\Services\CommentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommentReportController extends Controller
{
    public function __construct(private CommentReportServiceInterface
                                $reports) {}
    public function store(StoreCommentReportRequest $request, Pattern $pattern, int $commentId): JsonResponse
    {
        $this->reports->create($request->user(), $pattern, $commentId,
            $request->validated());

        return response()->json([], 201);
    }

    public function evidence(Request $request, CommentReport $report, int $index): StreamedResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $path = $report->evidence[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
