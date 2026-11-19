<?php

namespace App\Http\Controllers;

use App\Services\UkrainianBeadSpeech;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Process;

class BeadSpeechController extends Controller
{
    public function __invoke(string $language, string $number, ?string $count = null): Response
    {
        $wav = $language === 'uk'
            ? app(UkrainianBeadSpeech::class)->render((int) $number, $count === null ? null : (int) $count)
            : $this->synthesize($language, $number, $count);

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function synthesize(string $language, string $number, ?string $count): string
    {
        $label = fn (string $key) => __("main.bead_dictation.$key", [], $language);
        $phrase = $count === null
            ? "{$label('row')} $number."
            : "{$label('color')} $number, {$label('quantity')} $count.";

        $result = Process::timeout(5)->run(['espeak-ng', '--stdout', '-b', '1', '-v', $language, '-s', '145', $phrase]);
        abort_unless($result->successful(), 503);

        $wav = $result->output();
        // eSpeak writes placeholder lengths when streaming WAV to stdout.
        $wav = substr_replace($wav, pack('V', strlen($wav) - 8), 4, 4);
        return substr_replace($wav, pack('V', strlen($wav) - 44), 40, 4);
    }
}
