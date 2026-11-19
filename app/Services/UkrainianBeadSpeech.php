<?php

namespace App\Services;

class UkrainianBeadSpeech
{
    public function render(int $number, ?int $count): string
    {
        $directory = resource_path('audio/bead-dictation/uk');
        $bank = file_get_contents("$directory/clips.pcm");
        $index = json_decode(file_get_contents("$directory/clips.json"), true, flags: JSON_THROW_ON_ERROR);
        $rate = $index['sampleRate'];

        $keys = [$count === null ? 'row' : 'color', ...$this->numberClips($number)];
        if ($count !== null) {
            array_push($keys, 'pause', 'quantity', ...$this->numberClips($count));
        }

        $pcm = '';
        foreach ($keys as $key) {
            if ($key === 'pause') {
                $pcm .= str_repeat("\0", intdiv($rate, 5) * 2);
                continue;
            }
            if ($key === 3) {
                $pcm .= file_get_contents("$directory/three.pcm");
                continue;
            }

            [$offset, $length] = $index['clips'][$key];
            $pcm .= substr($bank, $offset, $length);
        }
        $pcm .= str_repeat("\0", intdiv($rate, 5) * 2);

        $length = strlen($pcm);

        return 'RIFF'.pack('V', $length + 36).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            .'data'.pack('V', $length).$pcm;
    }

    private function numberClips(int $number): array
    {
        $clips = [];
        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand', 1 => null] as $base => $scale) {
            $group = intdiv($number, $base);
            if ($group === 0) {
                continue;
            }
            $number %= $base;

            $hundreds = intdiv($group, 100);
            if ($hundreds > 0) {
                $clips[] = $hundreds * 100;
            }

            $lastTwo = $group % 100;
            $units = $lastTwo % 10;
            if ($lastTwo >= 20) {
                $clips[] = $lastTwo - $units;
            } else {
                $units = $lastTwo;
            }
            if ($units > 0) {
                $clips[] = match (true) {
                    $scale === 'thousand' && $units === 1 => 'one_feminine',
                    $scale === 'thousand' && $units === 2 => 'two_feminine',
                    default => $units,
                };
            }

            if ($scale !== null) {
                $ending = match (true) {
                    $units === 1 => 'one',
                    $units >= 2 && $units <= 4 => 'few',
                    default => 'many',
                };
                $clips[] = "{$scale}_$ending";
            }
        }

        return $clips;
    }
}
