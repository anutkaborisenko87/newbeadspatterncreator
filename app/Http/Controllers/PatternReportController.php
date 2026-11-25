<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PatternReportServiceInterface;
use App\Http\Requests\StorePatternReportRequest;
use App\Models\Pattern;
use App\Models\PatternReport;
use App\Services\PatternImageExporter;
use App\Services\PatternReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatternReportController extends Controller
{
    public function __construct(private PatternReportServiceInterface $service) {}
    public function store(StorePatternReportRequest $request, Pattern $discussionPattern): JsonResponse
    {
        $this->service->submit($discussionPattern, $request->user(),
            $request->validated());
        return response()->json([], 201);
    }

    public function preview(Request $request, PatternReport $report, PatternImageExporter $exporter): StreamedResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_unless($report->drawing, 404);
        $image = $exporter->render($report->drawing, false, 'bottom_up', 'left_to_right');
        return response()->stream(fn () => imagepng($image), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }
}
