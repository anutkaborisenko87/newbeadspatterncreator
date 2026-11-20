<?php

namespace App\Services;

use GdImage;
use Illuminate\Validation\ValidationException;

class PatternImageExporter
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    private const CELL = 10;
    private const PADDING = 20;
    private const GRID_TOP = 52;
    private const LINE_HEIGHT = 22;
    private const COLUMN_WIDTH = 304;
    private const MAX_SIDE = 8192;
    private const MAX_PIXELS = 20_000_000;
    private const INK = 0x031945;

    private array $textWidths = [];

    public function render(array $drawing, bool $includeDictation, string $rowDirection, string $columnDirection): GdImage
    {
        $width = max(self::CELL, (int) $drawing['gridWidth']);
        $height = max(self::CELL, (int) $drawing['gridHeight']);
        $view = $drawing['view'] ?? [];
        $showOffset = $view['showBeadPreview'] ?? (($drawing['editorPreviewFormat'] ?? null) === 'offset-grid');
        $offset = $showOffset ? ($view['offsetDirection'] ?? 'horizontal') : null;
        $extraWidth = $offset === 'horizontal' ? self::CELL / 2 : 0;
        $extraHeight = $offset === 'vertical' && $width > self::CELL ? self::CELL / 2 : 0;
        $scale = min(1.6, 1920 / ($width + $extraWidth), 1920 / ($height + $extraHeight));
        $flatPanelWidth = max(160, (int) ceil($width * $scale) + 2 * self::PADDING);
        $offsetPanelWidth = $offset
            ? max(160, (int) ceil(($width + $extraWidth) * $scale) + 2 * self::PADDING) : 0;
        $gridHeight = (int) ceil(($height + $extraHeight) * $scale);
        $diagramWidth = $flatPanelWidth + $offsetPanelWidth;
        $dictation = $includeDictation ? $this->dictationLines($drawing, $rowDirection, $columnDirection) : [];
        $maxColumns = intdiv(self::MAX_SIDE - $diagramWidth - self::PADDING, self::COLUMN_WIDTH);
        $linesPerColumn = max(1, intdiv(max($gridHeight, 360), self::LINE_HEIGHT), (int) ceil(count($dictation) / $maxColumns));
        $columns = array_chunk($dictation, $linesPerColumn);
        $outputWidth = $diagramWidth + count($columns) * self::COLUMN_WIDTH + self::PADDING;
        $dictationHeight = min($linesPerColumn, count($dictation)) * self::LINE_HEIGHT;
        $outputHeight = self::GRID_TOP + max($gridHeight + 14, $dictationHeight) + self::PADDING;
        if ($outputHeight > self::MAX_SIDE || $outputWidth * $outputHeight > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['pattern' => __('main.pattern_download.too_large')]);
        }

        $image = imagecreatetruecolor($outputWidth, $outputHeight);
        imagefill($image, 0, 0, 0xffffff);
        $this->drawGrid($image, $drawing['drawnCells'], self::PADDING, $width, $height, $scale, null);
        if ($offset) {
            $this->drawGrid($image, $drawing['drawnCells'], $flatPanelWidth + self::PADDING, $width, $height, $scale, $offset);
        }
        if ($columns) {
            $this->drawDictation($image, $columns, $diagramWidth + self::PADDING);
        }

        return $image;
    }

    public static function cellPosition(int $x, int $y, int $width, int $height, ?string $offset): array
    {
        if ($offset === 'horizontal') {
            $shift = intdiv($height - $y, self::CELL) * 5;
            $x = (($x - $shift) % $width + $width) % $width;
        } elseif ($offset === 'vertical') {
            $y += (intdiv($x, self::CELL) % 2) * 5;
        }

        return [$x, $y];
    }

    private function drawGrid(GdImage $image, array $cells, int $left, int $width, int $height, float $scale, ?string $offset): void
    {
        $label = match ($offset) {
            'horizontal' => __('main.bead_preview_offsets.horizontal'),
            'vertical' => __('main.bead_preview_offsets.vertical'),
            default => __('main.pattern_download.flat_grid'),
        };
        imagefttext($image, 13, 0, $left, 29, self::INK, self::FONT, $label);
        $xPixel = fn (int $x) => $left + (int) round($x * $scale);
        $yPixel = fn (int $y) => self::GRID_TOP + (int) round($y * $scale);

        foreach ($cells as $key => $color) {
            [$x, $y] = $this->gridCoordinates($key);
            [$x, $y] = self::cellPosition($x, $y, $width, $height, $offset);
            imagefilledrectangle($image, $xPixel($x), $yPixel($y),
                max($xPixel($x), $xPixel($x + self::CELL) - 1),
                max($yPixel($y), $yPixel($y + self::CELL) - 1), hexdec(substr($color, 1)));
        }
        $line = imagecolorallocatealpha($image, 155, 155, 155, 63);
        if ($scale * self::CELL < 2) {
            imagerectangle($image, $left, self::GRID_TOP, $xPixel($width), $yPixel($height), $line);
            return;
        }

        if ($offset === 'horizontal') {
            for ($y = 0; $y < $height; $y += self::CELL) {
                $shift = intdiv($height - $y, self::CELL) % 2 * 5;
                imagerectangle($image, $xPixel($shift), $yPixel($y),
                    $xPixel($width + $shift), $yPixel($y + self::CELL), $line);
                for ($x = $shift + self::CELL; $x < $width + $shift; $x += self::CELL) {
                    imageline($image, $xPixel($x), $yPixel($y), $xPixel($x), $yPixel($y + self::CELL), $line);
                }
            }
        } elseif ($offset === 'vertical') {
            for ($x = 0; $x < $width; $x += self::CELL) {
                $shift = intdiv($x, self::CELL) % 2 * 5;
                imagerectangle($image, $xPixel($x), $yPixel($shift),
                    $xPixel($x + self::CELL), $yPixel($height + $shift), $line);
                for ($y = $shift + self::CELL; $y < $height + $shift; $y += self::CELL) {
                    imageline($image, $xPixel($x), $yPixel($y), $xPixel($x + self::CELL), $yPixel($y), $line);
                }
            }
        } else {
            for ($x = 0; $x <= $width; $x += self::CELL) {
                imageline($image, $xPixel($x), self::GRID_TOP, $xPixel($x), $yPixel($height), $line);
                if ($x && $x % 50 === 0 && $scale >= 0.7) {
                    imagestring($image, 2, $xPixel($x) - 3, $yPixel($height) + 4, (string) intdiv($x, self::CELL), self::INK);
                }
            }
            for ($y = 0; $y <= $height; $y += self::CELL) {
                imageline($image, $left, $yPixel($y), $xPixel($width), $yPixel($y), $line);
                if (($height - $y) % 50 === 0 && $scale >= 0.7) {
                    $value = (string) intdiv($height - $y, self::CELL);
                    imagestring($image, 2, $left - (strlen($value) * 6 + 2), $yPixel($y) - 5, $value, self::INK);
                }
            }
        }
    }

    private function dictationLines(array $drawing, string $rowDirection, string $columnDirection): array
    {
        $labels = __('main.bead_dictation');
        $rows = $palette = [];
        foreach ($drawing['drawnCells'] as $key => $color) {
            [$x, $y] = $this->gridCoordinates($key);
            if ($x < 0 || $y < 0 || $x >= $drawing['gridWidth'] || $y >= $drawing['gridHeight']) {
                continue;
            }
            $color = strtolower($color);
            $rows[$y][$x] = $color;
            $palette[$color] ??= ['number' => count($palette) + 1, 'count' => 0];
            $palette[$color]['count']++;
        }

        $lines = [
            [[$labels[$rowDirection], null]],
            [[$labels[$columnDirection], null]],
            [[$labels['total'] . ' ' . array_sum(array_column($palette, 'count')), null]],
        ];
        $legend = [];
        foreach ($palette as $color => $entry) {
            $legend[] = [$labels['color'] . ' ' . $entry['number'] . ' ×' . $entry['count'], $color];
        }
        $this->appendTokens($lines, $legend);
        $rowDirection === 'bottom_up' ? krsort($rows, SORT_NUMERIC) : ksort($rows, SORT_NUMERIC);
        $firstRow = array_key_first($rows);
        $step = $columnDirection === 'left_to_right' ? self::CELL : -self::CELL;
        foreach ($rows as $y => $cells) {
            $step > 0 ? ksort($cells, SORT_NUMERIC) : krsort($cells, SORT_NUMERIC);
            $runs = [];
            $previousColor = null;
            $previousX = null;
            foreach ($cells as $x => $color) {
                if ($previousColor === $color && $x === $previousX + $step) {
                    $runs[array_key_last($runs)]['count']++;
                } else {
                    $runs[] = ['color' => $color, 'count' => 1];
                }
                $previousColor = $color;
                $previousX = $x;
            }
            $number = intdiv(abs($y - $firstRow), self::CELL) + 1;
            $tokens = array_map(fn (array $run) => [
                $palette[$run['color']]['number'] . ' ×' . $run['count'], $run['color'],
            ], $runs);
            $this->appendTokens($lines, $tokens, $labels['row'] . ' ' . $number);
        }

        return $lines;
    }

    private function appendTokens(array &$lines, array $tokens, ?string $heading = null): void
    {
        $prefix = $heading === null ? [] : [[$heading, null]];
        $prefixWidth = $heading === null ? 0 : $this->tokenWidth($heading, null);
        $line = $prefix;
        $width = $prefixWidth;
        foreach ($tokens as [$text, $color]) {
            $tokenWidth = $this->tokenWidth($text, $color);
            if ($width + $tokenWidth > self::COLUMN_WIDTH - self::PADDING && $width > $prefixWidth) {
                $lines[] = $line;
                $line = $prefix;
                $width = $prefixWidth;
            }
            $line[] = [$text, $color];
            $width += $tokenWidth;
        }
        if ($line) {
            $lines[] = $line;
        }
    }

    private function drawDictation(GdImage $image, array $columns, int $left): void
    {
        imagefttext($image, 13, 0, $left, 29, self::INK, self::FONT, __('main.bead_dictation.title'));
        foreach ($columns as $index => $lines) {
            $columnLeft = $left + $index * self::COLUMN_WIDTH;
            if ($index > 0) {
                imageline($image, $columnLeft - 9, self::GRID_TOP, $columnLeft - 9, imagesy($image) - self::PADDING, 0xd0daec);
            }
            foreach ($lines as $row => $line) {
                $x = $columnLeft;
                $baseline = self::GRID_TOP + ($row + 1) * self::LINE_HEIGHT - 5;
                foreach ($line as [$text, $color]) {
                    if ($color !== null) {
                        imagefilledrectangle($image, $x, $baseline - 11, $x + 10, $baseline - 1, hexdec(substr($color, 1)));
                        imagerectangle($image, $x, $baseline - 11, $x + 10, $baseline - 1, 0x7e8da7);
                    }
                    imagefttext($image, 10, 0, $x + ($color === null ? 0 : 16), $baseline, self::INK, self::FONT, $text);
                    $x += $this->tokenWidth($text, $color);
                }
            }
        }
    }

    private function tokenWidth(string $text, ?string $color): int
    {
        if (!isset($this->textWidths[$text])) {
            $box = imageftbbox(10, 0, self::FONT, $text);
            $this->textWidths[$text] = $box[2] - $box[0];
        }

        return $this->textWidths[$text] + ($color === null ? 0 : 16) + 10;
    }

    private function gridCoordinates(string $key): array
    {
        [$x, $y] = explode(',', $key);

        return [(int) $x - 20, (int) $y - 20];
    }
}
