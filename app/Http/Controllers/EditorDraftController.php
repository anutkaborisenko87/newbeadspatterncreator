<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PatternServiceInterface;
use App\Models\EditorDraft;
use App\Models\Language;
use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Services\CategoryNameTranslator;
use App\Services\PatternImageExporter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EditorDraftController extends Controller
{

    /**
     * Deletes an unpublished, unshared editor draft pattern owned by the authenticated user.
     *
     * Aborts with a 404 response when the pattern is not an editor draft for the user
     * or when the draft cannot be deleted.
     *
     * Redirects back to the editor when requested for a draft revision; otherwise
     * redirects back to the previous page.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern draft being deleted.
     * @return RedirectResponse The redirect response after deletion.
     */
    public function destroy(Request $request, Pattern $pattern): RedirectResponse
    {
        abort_unless($pattern->isEditorDraftFor($request->user()->getKey()), 404);
        abort_unless(Pattern::whereKey($pattern->getKey())->where('user_id', $request->user()->getKey())
            ->where('published', false)->where('shared', false)->delete(), 404);

        if ($request->boolean('returnToEditor') && isset($pattern->pattern_data['revisionOf'])) {
            return redirect()->route('page', ['slug' => 'editor'], 303);
        }

        return back(303);
    }

    /**
     * Starts a revision draft for the given pattern and redirects to the draft editor.
     *
     * @param Request $request
     * @param Pattern $pattern
     * @param PatternServiceInterface $patterns
     * @return RedirectResponse
     */
    public function startRevision(Request $request, Pattern $pattern, PatternServiceInterface $patterns): RedirectResponse
    {
        $draft = $patterns->getOrCreateRevisionDraft($pattern, $request->user()->getKey());

        return redirect()->route('editor-draft.edit', ['pattern' => $draft], 303);
    }

    /**
     * Updates the editor draft for the authenticated user with the provided snapshot.
     *
     * Aborts with a 404 response when the user is not authenticated or when the draft cannot be updated.
     *
     * @param Request $request The current HTTP request containing the snapshot data.
     * @return JsonResponse A JSON response containing the updated version of the draft.
     * @throws Throwable
     */
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

    /**
     * Validates the snapshot data from the request.
     *
     * @param Request $request The current HTTP request containing the snapshot data.
     * @return JsonResponse The validated snapshot data.
     * @throws Throwable
     */
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

    /**
     * Starts the editor from a saved draft pattern owned by the authenticated user.
     *
     * Validates the submitted editor snapshot, preview, and operation identifier,
     * updates the saved pattern with the latest editor state, archives any current
     * working draft content when needed, and replaces the working draft with the
     * saved drawing.
     *
     * @param Request $request
     * @param Pattern $pattern
     * @return JsonResponse
     *
     * @throws ValidationException
     * @throws Throwable
     */
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

    /**
     * Creates a new profile draft pattern for the given user with the specified version, drawing, and optional preview.
     *
     * @param int $userId The ID of the user for whom the draft is being created.
     * @param int $version The version number of the draft.
     * @param array $drawing The drawing data for the draft.
     * @param array|null $preview Optional preview data for the draft.
     */
    private function createProfileDraft(int $userId, int $version, array $drawing, ?array $preview = null): void
    {
        Pattern::create([
            'user_id' => $userId,
            'published' => false,
            'shared' => false,
            'pattern_data' => [...$drawing, 'editorDraftVersion' => (string) $version, ...$this->previewData($preview, $version)],
        ]);
    }

    /**
     * Extracts the preview data from the given preview array and version.
     *
     * @param Request $request
     * @param Pattern $pattern
     * @return StreamedResponse The extracted preview data.
     */
    public function preview(Request $request, Pattern $pattern): StreamedResponse
    {
        abort_unless($pattern->isEditorDraftFor($request->user()->getKey()), 404);

        return $this->renderPreview($pattern);
    }

    /**
     * Streams a preview image for a publicly shared pattern.
     *
     * Ensures the pattern is publicly accessible and has editor drawing data before
     * rendering the preview. When no explicit format is requested, the pattern must
     * also be published and associated with a gallery publication.
     *
     * @param Pattern $pattern
     * @param string|null $format
     * @return StreamedResponse
     */
    public function publishedPreview(Pattern $pattern, ?string $format = null): StreamedResponse
    {
        abort_unless($pattern->isPubliclyShared() && $pattern->hasEditorDrawing(), 404);
        if ($format === null) {
            abort_unless($pattern->published && isset($pattern->pattern_data['galleryPublicationId']), 404);
        }

        return $this->renderPreview($pattern, $format);
    }

    /**
     * Streams a preview image for a pattern owned by the authenticated user.
     *
     * Ensures the pattern is in the personal gallery of the user and has editor drawing data.
     *
     * @param Request $request
     * @param Pattern $pattern
     * @param string|null $format
     * @return StreamedResponse
     */
    public function ownedPreview(Request $request, Pattern $pattern, ?string $format = null): StreamedResponse
    {
        abort_unless($pattern->isInPersonalGalleryOf($request->user()->getKey()) && $pattern->hasEditorDrawing(), 404);

        return $this->renderPreview($pattern, $format);
    }

    /**
     * Validates the preview data from the request.
     *
     * @param Pattern $pattern
     * @param string|null $downloadFormat
     * @return StreamedResponse The validated preview data or null if not provided.
     */
    private function renderPreview(Pattern $pattern, ?string $downloadFormat = null): StreamedResponse
    {
        $drawing = $pattern->pattern_data;
        $previewVersion = $drawing['editorPreviewVersion'] ?? null;
        $drawingVersion = $drawing['editorEditVersion'] ?? $drawing['editorDraftVersion'] ?? $previewVersion;
        $offset = !empty($drawing['view']['showBeadPreview']) ? ($drawing['view']['offsetDirection'] ?? 'horizontal') : null;
        $format = $offset ? 'offset-grid' : 'flat-grid';
        $storedPreview = isset($drawing['editorPreview']) && ($drawing['editorPreviewFormat'] ?? null) === $format
            && (int) $previewVersion === (int) $drawingVersion
            ? base64_decode($drawing['editorPreview'], true) : false;
        $outputFormat = $downloadFormat ?? ($storedPreview ? 'jpg' : 'png');
        $writeImage = function () use ($storedPreview, $outputFormat, $drawing, $offset) {
            if ($storedPreview && $outputFormat === 'jpg') {
                echo $storedPreview;
                return;
            }

            $image = $storedPreview ? @imagecreatefromstring($storedPreview) : false;
            $image = $image ?: $this->drawPreview($drawing, $offset);
            if ($outputFormat === 'jpg') {
                imagejpeg($image, null, 95);
            } else {
                imagepng($image);
            }
        };
        $headers = [
            'Content-Type' => $outputFormat === 'jpg' ? 'image/jpeg' : 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $downloadFormat === null
            ? response()->stream($writeImage, 200, $headers)
            : response()->streamDownload($writeImage, 'pattern-' . $pattern->getKey() . '.' . $outputFormat, $headers);
    }

    /**
     * Draws a preview image based on the provided drawing data and offset.
     *
     * @param array $drawing The drawing data containing grid dimensions and drawn cells.
     * @param string|null $offset The offset direction for the preview ('horizontal', 'vertical', or null).
     * @return \GdImage The generated preview image.
     */
    private function drawPreview(array $drawing, ?string $offset): \GdImage
    {
        $width = max(1, $drawing['gridWidth']);
        $height = max(1, $drawing['gridHeight']);
        $imageWidth = $width + ($offset === 'horizontal' ? 5 : 0);
        $imageHeight = $height + ($offset === 'vertical' && $width > 10 ? 5 : 0);
        $scale = min(320 / $imageWidth, 240 / $imageHeight, 1);
        $image = imagecreatetruecolor((int) ceil($imageWidth * $scale), (int) ceil($imageHeight * $scale));
        imagefill($image, 0, 0, 0xffffff);
        if (!$offset && $scale * 10 >= 1) {
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
            [$x, $y] = array_map(fn ($coordinate) => (int) $coordinate - 20, explode(',', $key));
            [$x, $y] = PatternImageExporter::cellPosition($x, $y, $width, $height, $offset);
            imagefilledrectangle($image,
                (int) floor($x * $scale), (int) floor($y * $scale),
                (int) ceil(($x + 10) * $scale) - 1, (int) ceil(($y + 10) * $scale) - 1,
                hexdec(substr($color, 1)));
        }
        if ($offset && $scale * 10 >= 2) {
            $line = imagecolorallocatealpha($image, 180, 180, 180, 70);
            for ($y = 0; $y < $drawing['gridHeight']; $y += 10) {
                for ($x = 0; $x < $drawing['gridWidth']; $x += 10) {
                    [$left, $top] = PatternImageExporter::cellPosition($x, $y, $width, $height, $offset);
                    imagerectangle($image, (int) round($left * $scale), (int) round($top * $scale),
                        (int) round(($left + 10) * $scale), (int) round(($top + 10) * $scale), $line);
                }
            }
        }

        return $image;
    }

    /**
     * Extracts the preview data from the given preview array and version.
     *
     * @param Request $request
     * @param CategoryNameTranslator $translator
     * @param string|null $pattern
     * @return JsonResponse The extracted preview data, including base64-encoded image and format.
     * @throws Throwable
     */
    public function publish(Request $request, CategoryNameTranslator $translator, ?string $pattern = null): JsonResponse
    {
        $userId = $request->user()->getKey();
        $draftId = $pattern;
        $source = $draftId !== null ? Pattern::find($draftId) : null;
        abort_if($source && ((int) $source->user_id !== $userId || $source->isPrivateCopy()), 404);
        if ($request->hasFile('photo') && is_string($request->input('drawing'))) {
            $request->merge(['drawing' => json_decode($request->input('drawing'), true)]);
        }
        $snapshot = $this->validateSnapshot($request);
        if (!$snapshot['drawing']['drawnCells']) {
            throw ValidationException::withMessages(['drawing' => __('main.gallery_publication.empty')]);
        }
        $preview = $this->validatePreview($request);
        $createCategory = $request->input('category') === 'new';
        $rules = [
            'publicationId' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => $createCategory ? ['required', Rule::in(['new'])] : ['required', 'integer', Rule::exists('pattern_categories', 'id')],
            'publicationMode' => ['sometimes', Rule::in(['new', 'replace'])],
        ];
        if ($createCategory) {
            $rules['categoryName'] = ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (strlen($value) > 500) {
                    $fail(__('main.gallery_publication.category_name_too_long'));
                }
            }];
        }
        $fields = $request->validate($rules);

        $alreadyPublished = fn () => Pattern::where('user_id', $userId)->where('published', true)
            ->where('pattern_data->galleryPublicationId', $fields['publicationId'])
            ->exists();
        $result = ['editorUrl' => route('page', ['slug' => 'editor'], absolute: false)];
        if ($alreadyPublished()) {
            return response()->json([...$result, 'workspace' => EditorDraft::find($userId)?->snapshot()]);
        }
        if ($draftId !== null) {
            abort_unless($source?->isEditorDraftFor($userId), 404);
        }

        $translations = $createCategory
            ? $translator->translate($fields['categoryName'], app()->getLocale(), Language::getAllEnableLanguages())
            : [];

        $workspace = DB::transaction(function () use ($userId, $draftId, $snapshot, $preview, $fields, $createCategory, $translations, $alreadyPublished) {
            User::query()->lockForUpdate()->findOrFail($userId);
            $working = EditorDraft::firstOrNew(['user_id' => $userId]);
            if ($alreadyPublished()) {
                return $working->snapshot();
            }

            $draft = $draftId !== null ? Pattern::query()->lockForUpdate()->findOrFail($draftId) : null;
            if ($draft) {
                abort_unless($draft->isEditorDraftFor($userId), 404);
                abort_if($draft->editorSnapshot()['version'] > $snapshot['version'], 409);
            } else {
                abort_if($working->exists && $working->version > $snapshot['version'], 409);
            }
            $replace = ($fields['publicationMode'] ?? 'new') === 'replace';
            $publication = $draft ?? new Pattern;
            $originalId = $draft?->pattern_data['revisionOf'] ?? null;
            abort_if($replace && !$originalId, 422);
            if ($originalId) {
                $original = Pattern::query()->lockForUpdate()->findOrFail($originalId);
                abort_if($original->publication_blocked, 403, __('pattern_reports.blocked'));
                abort_unless($original->canRevise($userId), 404);
                if ($replace) {
                    $publication = $original;
                }
            }
            if ($createCategory) {
                $category = PatternCategory::create();
                foreach ($translations as $languageId => $title) {
                    $category->translations()->create(['language_id' => $languageId, 'title' => $title]);
                }
                $categoryId = $category->getKey();
            } else {
                $categoryId = $fields['category'];
            }

            if (isset($fields['photo'])) {
                $path = $fields['photo']->store('product-photos', 'public');
                abort_if($path === false, 500);
                DB::afterRollBack(fn () => \Illuminate\Support\Facades\Storage::disk('public')->delete($path));
                $publication->photo = '/storage/'.$path;
            }
            $publication->fill([
                'user_id' => $userId,
                'title' => $fields['title'],
                'description' => $fields['description'] ?? null,
                'published' => true,
                'shared' => true,
                'pattern_data' => [
                    ...$snapshot['drawing'],
                    'galleryPublicationId' => $fields['publicationId'],
                    ...$this->previewData($preview, $snapshot['version']),
                ],
            ])->save();
            $publication->category()->sync([$categoryId]);
            if ($replace) {
                $draft->delete();
            }
            if ($draft && !empty($working->drawing['drawnCells'])) {
                $this->createProfileDraft($userId, $working->version, $working->drawing);
            }
            $working->fill([
                'version' => max($working->version ?? 0, $snapshot['version']) + 1,
                'drawing' => [...$snapshot['drawing'], 'drawnCells' => []],
            ])->save();

            return $working->snapshot();
        });

        return response()->json([...$result, 'workspace' => $workspace]);
    }

    /**
     * Updates the saved drawing for the authenticated user with the provided snapshot.
     *
     * Aborts with a 404 response when the user is not authenticated or when the saved drawing cannot be updated.
     *
     * @param Request $request The current HTTP request containing the snapshot data.
     * @param Pattern $pattern The saved pattern being updated.
     * @return JsonResponse A JSON response containing the updated version of the saved drawing.
     * @throws Throwable
     */
    public function updateSaved(Request $request, Pattern $pattern): JsonResponse
    {
        $userId = $request->user()->getKey();
        abort_unless($pattern->canEditSavedDrawing($userId), 404);
        $validated = $this->validateSnapshot($request);
        $preview = $this->validatePreview($request);

        $version = DB::transaction(function () use ($pattern, $userId, $validated, $preview) {
            $draft = Pattern::query()->lockForUpdate()->findOrFail($pattern->getKey());
            abort_unless($draft->canEditSavedDrawing($userId), 404);
            $snapshot = $draft->editorSnapshot();
            $version = $snapshot['version'];
            if ((int) $validated['version'] > $version) {
                $version = (int) $validated['version'];
                $draft->pattern_data = [...$draft->pattern_data, ...$validated['drawing'], 'editorEditVersion' => $version,
                    ...$this->previewData($preview, $version)];
                if ($draft->isPrivateCopy()) {
                    $draft->fill(['preview_image' => null, 'pngLink' => null, 'jpgLink' => null]);
                }
                $draft->save();
            } elseif ((int) $validated['version'] === $version && $preview !== null && $snapshot['previewVersion'] !== $version) {
                $draft->pattern_data = [...$draft->pattern_data, ...$this->previewData($preview, $version)];
                Pattern::withoutTimestamps(fn () => $draft->save());
            }

            return $version;
        });

        return response()->json(['version' => $version]);
    }

    /**
     * Validates the preview data from the request.
     *
     * @param Request $request The current HTTP request containing the preview data.
     * @return array|null The validated preview data or null if not provided.
     * @throws ValidationException If the preview data is invalid.
     */
    private function validatePreview(Request $request): ?array
    {
        $fields = $request->validate([
            'preview' => ['nullable', 'string', 'max:600000'],
            'previewFormat' => ['sometimes', 'in:flat-grid,offset-grid'],
        ]);
        $preview = $fields['preview'] ?? null;
        if ($preview === null) {
            return null;
        }

        $bytes = base64_decode($preview, true);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$size || $size[2] !== IMAGETYPE_JPEG || $size[0] > 1200 || $size[1] > 900) {
            throw ValidationException::withMessages(['preview' => 'Invalid editor preview.']);
        }

        return ['editorPreview' => $preview, 'editorPreviewFormat' => $fields['previewFormat'] ?? null];
    }

    /**
     * Prepares the preview data for storage by including the editor preview version.
     *
     * @param array|null $preview The preview data to be stored.
     * @param int $version The version number of the editor draft.
     * @return array The prepared preview data with the editor preview version included.
     */
    private function previewData(?array $preview, int $version): array
    {
        return $preview === null ? [] : [...$preview, 'editorPreviewVersion' => $version];
    }

    /**
     * Validates the snapshot data from the request.
     *
     * @param Request $request The current HTTP request containing the snapshot data.
     * @return array The validated snapshot data.
     * @throws ValidationException If the snapshot data is invalid.
     */
    private function validateSnapshot(Request $request): array
    {
        abort_unless((string) $request->input('owner') === (string) $request->user()->getKey(), 403);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1', 'max:9007199254740990'],
            'drawing' => ['required', 'array:gridWidth,gridHeight,drawnCells,view'],
            'drawing.gridWidth' => ['required', 'integer', 'min:0', 'multiple_of:10'],
            'drawing.gridHeight' => ['required', 'integer', 'min:0', 'multiple_of:10'],
            'drawing.drawnCells' => ['present', 'array'],
            'drawing.view' => ['sometimes', 'array:showBeadPreview,offsetDirection,showDictation,bottomUp,leftToRight'],
            'drawing.view.showBeadPreview' => ['required_with:drawing.view', 'boolean'],
            'drawing.view.offsetDirection' => ['required_with:drawing.view', 'in:horizontal,vertical'],
            'drawing.view.showDictation' => ['required_with:drawing.view', 'boolean'],
            'drawing.view.bottomUp' => ['required_with:drawing.view', 'boolean'],
            'drawing.view.leftToRight' => ['required_with:drawing.view', 'boolean'],
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

    /**
     * Checks if the given coordinate is a valid grid coordinate based on the specified size.
     *
     * A valid grid coordinate must be greater than or equal to 20, less than the size plus 20,
     * and a multiple of 10.
     *
     * @param int $coordinate The coordinate to check.
     * @param int $size The size of the grid.
     * @return bool True if the coordinate is valid; otherwise, false.
     */
    private function isGridCoordinate(int $coordinate, int $size): bool
    {
        return $coordinate >= 20 && $coordinate - 20 < $size && $coordinate % 10 === 0;
    }
}
