<?php

namespace App\Http\Controllers;

use App\Models\EditorDraft;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditorDraftController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $validated = $this->validateSnapshot($request);
        $version = DB::transaction(function () use ($userId, $validated) {
            // Lock the account even when its first draft has yet to be created.
            User::query()->lockForUpdate()->findOrFail($userId);
            $draft = EditorDraft::firstOrNew(['user_id' => $userId]);
            if (!$draft->exists || $validated['version'] > $draft->version) {
                $draft->fill($validated)->save();
            }

            return $draft->version;
        });

        return response()->json(['version' => $version]);
    }

    public function archive(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $validated = $this->validateSnapshot($request);
        $version = (int) $validated['version'];
        $drawing = $validated['drawing'];
        $preview = $this->validatePreview($request);

        $snapshot = DB::transaction(function () use ($userId, $version, $drawing, $preview) {
            User::query()->lockForUpdate()->findOrFail($userId);
            $draft = EditorDraft::firstOrNew(['user_id' => $userId]);
            if (Pattern::where('user_id', $userId)->where('pattern_data->editorDraftVersion', (string) $version)->exists()) {
                return $draft->snapshot();
            }
            abort_if($draft->exists && $draft->version > $version, 409);

            $this->createProfileDraft($userId, $version, $drawing, $preview);

            $draft->fill([
                'version' => $version + 1,
                'drawing' => [...$drawing, 'drawnCells' => []],
            ])->save();

            return $draft->snapshot();
        });

        return response()->json($snapshot);
    }

    public function startFromSaved(Request $request, Pattern $pattern): JsonResponse
    {
        $userId = $request->user()->getKey();
        abort_unless($pattern->isEditorDraftFor($userId), 404);
        $snapshot = $this->validateSnapshot($request);
        $preview = $this->validatePreview($request);
        $operation = (int) $request->validate([
            'operation' => ['required', 'integer', 'min:1', 'max:9007199254740990'],
        ])['operation'];

        DB::transaction(function () use ($pattern, $userId, $snapshot, $preview, $operation) {
            User::query()->lockForUpdate()->findOrFail($userId);
            $saved = Pattern::query()->lockForUpdate()->findOrFail($pattern->getKey());
            abort_unless($saved->isEditorDraftFor($userId), 404);
            if ($operation <= ($saved->pattern_data['editorRestartVersion'] ?? 0)) {
                return;
            }
            abort_if((int) $snapshot['version'] < $saved->editorSnapshot()['version'], 409);

            $version = (int) $snapshot['version'];
            $drawing = $snapshot['drawing'];
            $saved->pattern_data = [...$saved->pattern_data, ...$drawing, 'editorEditVersion' => $version,
                'editorRestartVersion' => $operation, ...$this->previewData($preview, $version)];
            $saved->save();

            $working = EditorDraft::firstOrNew(['user_id' => $userId]);
            if (!empty($working->drawing['drawnCells'])) {
                $this->createProfileDraft($userId, $working->version, $working->drawing);
            }
            $working->fill([
                'version' => max($version, $working->version ?? 0) + 1,
                'drawing' => [...$drawing, 'drawnCells' => []],
            ])->save();
        });

        return response()->json(['editorUrl' => route('page', ['slug' => 'editor'], absolute: false)]);
    }

    private function createProfileDraft(int $userId, int $version, array $drawing, ?string $preview = null): void
    {
        Pattern::create([
            'user_id' => $userId,
            'published' => false,
            'shared' => false,
            'pattern_data' => [...$drawing, 'editorDraftVersion' => (string) $version, ...$this->previewData($preview, $version)],
        ]);
    }

    public function preview(Request $request, Pattern $pattern): StreamedResponse
    {
        abort_unless($pattern->isEditorDraftFor($request->user()->getKey()), 404);
        $drawing = $pattern->pattern_data;
        if (isset($drawing['editorPreview'])) {
            return response()->stream(static function () use ($drawing) {
                echo base64_decode($drawing['editorPreview']);
            }, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response()->stream(function () use ($drawing) {
            $width = max(1, $drawing['gridWidth']);
            $height = max(1, $drawing['gridHeight']);
            $scale = min(320 / $width, 240 / $height, 1);
            $image = imagecreatetruecolor(max(1, (int) ceil($width * $scale)), max(1, (int) ceil($height * $scale)));
            imagefill($image, 0, 0, 0xffffff);
            if ($scale * 10 >= 1) {
                for ($x = 0; $x <= $width; $x += 10) {
                    $column = min(imagesx($image) - 1, (int) round($x * $scale));
                    imageline($image, $column, 0, $column, imagesy($image) - 1, $x % 50 === 0 ? 0xcccccc : 0xeeeeee);
                }
                for ($y = 0; $y <= $height; $y += 10) {
                    $row = min(imagesy($image) - 1, (int) round($y * $scale));
                    imageline($image, 0, $row, imagesx($image) - 1, $row, ($height - $y) % 50 === 0 ? 0xcccccc : 0xeeeeee);
                }
            }
            foreach ($drawing['drawnCells'] as $key => $color) {
                [$x, $y] = explode(',', $key);
                imagefilledrectangle($image,
                    (int) floor(($x - 20) * $scale), (int) floor(($y - 20) * $scale),
                    (int) ceil(($x - 10) * $scale) - 1, (int) ceil(($y - 10) * $scale) - 1,
                    hexdec(substr($color, 1)));
            }
            imagepng($image);
        }, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }

    public function updateSaved(Request $request, Pattern $pattern): JsonResponse
    {
        $userId = $request->user()->getKey();
        abort_unless($pattern->isEditorDraftFor($userId), 404);
        $validated = $this->validateSnapshot($request);
        $preview = $this->validatePreview($request);

        $version = DB::transaction(function () use ($pattern, $userId, $validated, $preview) {
            $draft = Pattern::query()->lockForUpdate()->findOrFail($pattern->getKey());
            abort_unless($draft->isEditorDraftFor($userId), 404);
            $snapshot = $draft->editorSnapshot();
            $version = $snapshot['version'];
            if ((int) $validated['version'] > $version) {
                $version = (int) $validated['version'];
                $draft->pattern_data = [...$draft->pattern_data, ...$validated['drawing'], 'editorEditVersion' => $version,
                    ...$this->previewData($preview, $version)];
                $draft->save();
            } elseif ((int) $validated['version'] === $version && $preview !== null && $snapshot['previewVersion'] !== $version) {
                $draft->pattern_data = [...$draft->pattern_data, ...$this->previewData($preview, $version)];
                Pattern::withoutTimestamps(fn () => $draft->save());
            }

            return $version;
        });

        return response()->json(['version' => $version]);
    }

    private function validatePreview(Request $request): ?string
    {
        $preview = $request->validate(['preview' => ['nullable', 'string', 'max:600000']])['preview'] ?? null;
        if ($preview === null) {
            return null;
        }

        $bytes = base64_decode($preview, true);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$size || $size[2] !== IMAGETYPE_JPEG || $size[0] > 1200 || $size[1] > 900) {
            throw ValidationException::withMessages(['preview' => 'Invalid editor preview.']);
        }

        return $preview;
    }

    private function previewData(?string $preview, int $version): array
    {
        return $preview === null ? [] : ['editorPreview' => $preview, 'editorPreviewVersion' => $version];
    }

    private function validateSnapshot(Request $request): array
    {
        abort_unless((string) $request->input('owner') === (string) $request->user()->getKey(), 403);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1', 'max:9007199254740990'],
            'drawing' => ['required', 'array:gridWidth,gridHeight,drawnCells'],
            'drawing.gridWidth' => ['required', 'integer', 'min:0', 'multiple_of:10'],
            'drawing.gridHeight' => ['required', 'integer', 'min:0', 'multiple_of:10'],
            'drawing.drawnCells' => ['present', 'array'],
        ]);

        $drawing = $validated['drawing'];
        foreach ($drawing['drawnCells'] as $key => $color) {
            if (!preg_match('/^(\d+),(\d+)$/D', (string) $key, $matches)
                || !$this->isGridCoordinate((int) $matches[1], $drawing['gridWidth'])
                || !$this->isGridCoordinate((int) $matches[2], $drawing['gridHeight'])
                || !is_string($color) || !preg_match('/^#[0-9a-f]{6}$/iD', $color)) {
                throw ValidationException::withMessages(['drawing.drawnCells' => 'Invalid grid cell.']);
            }
        }

        return $validated;
    }

    private function isGridCoordinate(int $coordinate, int $size): bool
    {
        return $coordinate >= 20 && $coordinate - 20 < $size && $coordinate % 10 === 0;
    }
}
