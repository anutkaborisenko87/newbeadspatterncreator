<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Services\PatternImageExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatternDownloadController extends Controller
{
    public function __construct(private PatternImageExporter $exporter)
    {
    }

    public function published(Request $request, Pattern $pattern, string $format): StreamedResponse
    {
        abort_unless($pattern->isPubliclyShared() && $pattern->hasEditorDrawing(), 404);

        return $this->download($request, $pattern, $format);
    }

    public function owned(Request $request, Pattern $pattern, string $format): StreamedResponse
    {
        abort_unless($pattern->isInPersonalGalleryOf($request->user()->getKey()) && $pattern->hasEditorDrawing(), 404);

        return $this->download($request, $pattern, $format);
    }

    private function download(Request $request, Pattern $pattern, string $format): StreamedResponse
    {
        $options = $request->validate([
            'dictation' => ['sometimes', 'boolean'],
            'rows' => ['sometimes', 'in:bottom_up,top_down'],
            'columns' => ['sometimes', 'in:left_to_right,right_to_left'],
        ]);
        $view = $pattern->pattern_data['view'] ?? [];
        $image = $this->exporter->render(
            $pattern->pattern_data,
            (bool) ($options['dictation'] ?? false),
            $options['rows'] ?? (($view['bottomUp'] ?? true) ? 'bottom_up' : 'top_down'),
            $options['columns'] ?? (($view['leftToRight'] ?? true) ? 'left_to_right' : 'right_to_left'),
        );

        return response()->streamDownload(static function () use ($image, $format) {
            if ($format === 'jpg') {
                imagejpeg($image, null, 95);
            } else {
                imagepng($image);
            }
        }, 'pattern-' . $pattern->getKey() . '.' . $format, [
            'Content-Type' => $format === 'jpg' ? 'image/jpeg' : 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
