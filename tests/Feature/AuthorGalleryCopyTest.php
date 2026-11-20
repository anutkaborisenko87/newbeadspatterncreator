<?php

namespace Tests\Feature;

use App\Contracts\Services\PageServiceInterface;
use App\DTO\PageDataDTO;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\EditorDraft;
use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Models\User;
use App\Services\PatternService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorGalleryCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
    }

    public function test_a_public_pattern_is_copied_once_to_the_viewers_personal_gallery(): void
    {
        $author = User::factory()->create(['login' => 'original-author']);
        $viewer = User::factory()->create(['login' => 'collector']);
        $source = Pattern::create([
            'user_id' => $author->id, 'published' => true, 'shared' => true,
            'title' => 'Bracelet', 'description' => 'A striped bracelet', 'photo' => '/images/bracelet.jpg',
            'pattern_data' => [
                'galleryPublicationId' => (string) Str::uuid(),
                'gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => ['20,20' => '#0000ff'],
            ],
        ]);
        $category = PatternCategory::create();
        $source->category()->attach($category);
        $source->likes()->create(['user_id' => $viewer->id]);
        $authorGallery = route('gallery.author', [
            'userId' => encodeModelId($author->id), 'patterns_search' => 'bracelet', 'page' => 2,
        ]);
        $copyUrl = route('gallery.pattern.copy', $source);
        $this->postJson($copyUrl)->assertUnauthorized();
        $this->actingAs($author)->postJson($copyUrl)->assertNotFound();

        $service = app(PatternService::class);
        $this->actingAs($viewer);
        $card = $service->getFilteredPatternsForAuthorGalery(encodeModelId($author->id))['data'][0];
        $this->assertSame(route('gallery.pattern.copy', $source, absolute: false), $card['copyUrl']);
        $download = $this->get($card['pngLink'])->assertOk()->assertDownload("pattern-{$source->id}.png");
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($download->streamedContent())[2]);
        $this->from($authorGallery)->post($copyUrl)->assertStatus(303)->assertRedirect($authorGallery)
            ->assertSessionHas('success', __('main.author_gallery.saved'));
        $this->post($copyUrl)->assertStatus(303)->assertSessionHas('success', __('main.author_gallery.already_saved'));

        $copy = Pattern::where('user_id', $viewer->id)->sole();
        $this->assertSame($source->id, (int) $copy->copied_from_id);
        $this->assertFalse((bool) $copy->published);
        $this->assertFalse((bool) $copy->shared);
        $this->assertSame($source->pattern_data['drawnCells'], $copy->pattern_data['drawnCells']);
        $this->assertArrayNotHasKey('galleryPublicationId', $copy->pattern_data);
        $this->assertSame($source->photo, $copy->photo);
        $this->assertSame([$category->id], $copy->category()->allRelatedIds()->all());
        $this->assertSame(0, $copy->likes()->count());
        $item = $service->getOwnerGalleryPatterns($viewer->id)['data'][0];
        $this->assertSame(__('main.own_gallery.private_copy_by', ['author' => $author->login]), $item['privateCopyLabel']);
        $this->assertNotNull($item['deleteUrl']);
        $this->assertNull($item['editPublishedUrl']);
        $this->assertSame(route('editor-draft.edit', $copy, absolute: false), $item['editPrivateCopyUrl']);
        $this->assertNull($item['link']);
        $this->assertSame(1, $service->getAuthorPatterns($viewer->id, false)['total']);
        $this->assertSame(0, $service->getAuthorPatterns($viewer->id, true)['total']);
        $this->assertSame(0, $service->getFilteredPatternsForAuthorGalery(encodeModelId($viewer->id))['total']);
        $this->assertSame(1, $service->getFilteredPatternsForCommonGallery()['total']);

        $source->delete();
        $this->get($item['preview'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($item['jpgLink'])->assertOk()->assertDownload("pattern-{$copy->id}.jpg");
        $this->delete($item['deleteUrl'])->assertStatus(303);
        $this->assertModelMissing($copy);
    }

    public function test_only_the_owner_can_edit_a_private_copy_without_making_it_publishable(): void
    {
        $author = User::factory()->create(['login' => 'original-author']);
        $viewer = User::factory()->create(['login' => 'collector']);
        $drawing = ['gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => ['20,20' => '#ff0000']];
        $source = Pattern::create([
            'user_id' => $author->id, 'published' => true, 'shared' => true, 'pattern_data' => $drawing,
            'preview_image' => '/original.png', 'pngLink' => '/original.png', 'jpgLink' => '/original.jpg',
        ]);
        $hidden = Pattern::create(['user_id' => $author->id, 'published' => true, 'pattern_data' => $drawing]);
        $this->actingAs($viewer)->postJson(route('gallery.pattern.copy', $hidden))->assertNotFound();
        $this->post(route('gallery.pattern.copy', $source))->assertStatus(303);
        $copy = Pattern::where('copied_from_id', $source->id)->sole();
        $editUrl = route('editor-draft.edit', $copy);
        $updateUrl = route('editor-draft.saved.update', $copy);
        $this->mock(PageServiceInterface::class)->shouldReceive('getPageData')->with('editor')
            ->andReturn(new PageDataDTO(1, 'Editor', null, null, [['slug' => 'editor_page_section']], [], []));
        $this->getJson($editUrl, ['X-Inertia' => 'true'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('props.editorPrivateCopy.returnUrl', route('gallery.mine', absolute: false))
            ->assertJsonPath('props.editorDraft.drawing', $drawing)
            ->assertJsonPath('props.editorDraft.version', 1)
            ->assertJsonPath('props.editorSavedDraftId', $copy->id)
            ->assertJsonMissingPath('props.galleryPublication')
            ->assertJsonMissingPath('props.editorWorkingDraftUrl');

        $this->putJson(route('editor-draft.update'), ['owner' => $viewer->id, 'version' => 2, 'drawing' => $drawing])
            ->assertOk();
        $edited = [...$drawing, 'drawnCells' => ['30,20' => '#00ff00']];
        $snapshot = ['owner' => $viewer->id, 'version' => 3, 'drawing' => $edited];
        $this->putJson($updateUrl, $snapshot)->assertOk()->assertJsonPath('version', 3);
        $this->putJson($updateUrl, [...$snapshot, 'version' => 2, 'drawing' => $drawing])
            ->assertOk()->assertJsonPath('version', 3);
        $this->getJson($editUrl, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.editorDraft.drawing', $edited);
        $this->assertSame($drawing, EditorDraft::findOrFail($viewer->id)->drawing);
        $this->assertSame($drawing, $source->fresh()->pattern_data);
        $this->assertSame($author->login, $copy->refresh()->pattern_data['originalAuthor']);
        $this->assertSame($source->id, (int) $copy->copied_from_id);
        $item = app(PatternService::class)->getOwnerGalleryPatterns($viewer->id)['data'][0];
        $picture = $this->get($item['preview'])->assertOk()->streamedContent();
        $this->assertSame(0x00ff00, imagecolorat(imagecreatefromstring($picture), 13, 3));
        $this->assertSame(route('gallery.mine.preview', ['pattern' => $copy, 'format' => 'jpg'], absolute: false), $item['jpgLink']);

        $this->postJson(route('editor-draft.publish', $copy), [
            ...$snapshot,
            'publicationId' => (string) Str::uuid(), 'title' => 'Duplicate', 'category' => PatternCategory::create()->id,
        ])->assertNotFound();
        $this->postJson(route('editor-draft.revision', $copy))->assertNotFound();
        $this->postJson($editUrl, [...$snapshot, 'operation' => 4])->assertNotFound();
        $this->get(route('gallery.pattern.preview', ['pattern' => $copy, 'format' => 'png']))->assertNotFound();
        $this->assertFalse((bool) $copy->fresh()->published);
        $this->assertFalse((bool) $copy->fresh()->shared);

        $this->actingAs($author)->get($editUrl)->assertNotFound();
        $this->putJson($updateUrl, [...$snapshot, 'owner' => $author->id])->assertNotFound();
        $this->get(route('gallery.mine.preview', $copy))->assertNotFound();
        $this->deleteJson(route('gallery.mine.destroy', $copy))->assertNotFound();
        $this->postJson(route('gallery.pattern.copy', $copy))->assertNotFound();
    }
}
