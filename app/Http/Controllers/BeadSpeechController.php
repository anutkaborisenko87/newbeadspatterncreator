<?php

namespace App\Http\Controllers;

use App\Services\UkrainianBeadSpeech;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Process;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BeadSpeechController extends Controller
{

    /**
     * Generates a WAV audio response for the given language, number, and optional count.
     *
     * @param string $language Language code used to select the speech renderer.
     * @param string $number Number value to render as speech.
     * @param string|null $count Optional count value used when rendering speech.
     * @return Response WAV audio HTTP response with cache headers.
     * @throws JsonException
     */
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

    /**
     * Synthesizes a localized bead dictation phrase into WAV audio.
     *
     * @param string $language The locale and eSpeak voice to use for synthesis.
     * @param string $number The row or color number to include in the spoken phrase.
     * @param string|null $count The bead quantity to include, or null to synthesize a row announcement.
     * @return string The synthesized WAV audio data.
     *
     * @throws HttpException If audio synthesis fails.
     */
    private function synthesize(string $language, string $number, ?string $count): string
    {
        $label = fn (string $key) => __("main.bead_dictation.$key", [], $language);
        $phrase = $count === null
            ? "{$label('row')} $number."
            : "{$label('color')} $number, {$label('quantity')} $count.";

        $result = Process::timeout(5)->run(['espeak-ng', '--stdout', '-b', '1', '-v', $language, '-s', '145', $phrase]);
        abort_unless($result->successful(), 503);

        $wav = $result->output();
        $wav = substr_replace($wav, pack('V', strlen($wav) - 8), 4, 4);
        return substr_replace($wav, pack('V', strlen($wav) - 44), 40, 4);
    }
}
