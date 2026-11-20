<?php

namespace Tests\Feature;

use App\Contracts\Services\PageServiceInterface;
use App\DTO\PageDataDTO;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
    }

    public function test_private_gallery_filters_and_paginates_only_the_owners_publications(): void
    {
        $owner = User::factory()->create(['login' => 'gallery-owner']);
        $other = User::factory()->create(['login' => 'another-owner']);
        $category = PatternCategory::create();
        for ($i = 1; $i <= 4; $i++) {
            Pattern::create(['user_id' => $owner->id, 'published' => true, 'title' => "Bead pattern $i"])
                ->category()->attach($category);
        }
        Pattern::create(['user_id' => $owner->id, 'published' => true, 'title' => 'Bead pattern without category']);
        Pattern::create(['user_id' => $owner->id, 'published' => true, 'title' => 'Flower'])
            ->category()->attach($category);
        Pattern::create(['user_id' => $other->id, 'published' => true, 'shared' => true, 'title' => 'Bead pattern'])
            ->category()->attach($category);
        Pattern::create(['user_id' => $owner->id, 'title' => 'Bead pattern draft'])->category()->attach($category);

        $this->mock(PageServiceInterface::class)
            ->shouldReceive('getPageData')->once()->with('gallery')->andReturnUsing(fn () => new PageDataDTO(
                1, 'Gallery', null, null, [['slug' => 'gallery_page_section', 'gallery_items' => getMainGalleryItemsList()]], [], [],
            ));
        $query = ['patterns_search' => 'Bead', 'pattern_category_filter' => encodeModelId($category->id), 'page' => 2];
        $url = route('gallery.mine', $query);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('props.ownGallery.title', __('main.profile_my_patterns_title'))
            ->assertJsonPath('props.data.sections.0.gallery_items.total', 4)
            ->assertJsonPath('props.data.sections.0.gallery_items.last_page', 2)
            ->assertJsonCount(1, 'props.data.sections.0.gallery_items.data');
    }

    public function test_only_the_owner_can_delete_a_publication_and_the_revision_stays_a_draft(): void
    {
        $owner = User::factory()->create(['login' => 'gallery-owner']);
        $other = User::factory()->create(['login' => 'another-owner']);
        $published = Pattern::create(['user_id' => $owner->id, 'published' => true, 'shared' => true]);
        $draft = Pattern::create(['user_id' => $owner->id, 'pattern_data' => [
            'editorDraftVersion' => '1', 'revisionOf' => (string) $published->id,
        ]]);
        $delete = route('gallery.mine.destroy', $published);
        $gallery = route('gallery.mine', ['patterns_search' => 'pattern', 'page' => 2]);

        $this->deleteJson($delete)->assertUnauthorized();
        $this->actingAs($other)->deleteJson($delete)->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('gallery.mine.destroy', $draft))->assertNotFound();
        $this->from($gallery)->delete($delete)->assertStatus(303)->assertRedirect($gallery);
        $this->assertModelMissing($published);
        $this->assertModelExists($draft);
    }

    public function test_owner_can_download_the_preview_as_png_and_jpeg(): void
    {
        $owner = User::factory()->create(['login' => 'gallery-owner']);
        $other = User::factory()->create(['login' => 'another-owner']);
        ob_start();
        imagejpeg(imagecreatetruecolor(120, 80));
        $jpeg = ob_get_clean();
        $drawing = [
            'gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['20,40' => '#0000ff'],
            'editorPreview' => base64_encode($jpeg), 'editorPreviewFormat' => 'flat-grid',
            'editorPreviewVersion' => 1, 'editorEditVersion' => 1,
        ];
        $pattern = Pattern::create(['user_id' => $owner->id, 'published' => true, 'pattern_data' => $drawing]);
        $url = fn (string $format) => route('gallery.mine.preview', ['pattern' => $pattern, 'format' => $format]);

        $this->getJson($url('png'))->assertUnauthorized();
        $this->actingAs($other)->get($url('png'))->assertNotFound();
        $png = $this->actingAs($owner)->get($url('png'))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertDownload("pattern-{$pattern->id}.png")->streamedContent();
        $this->assertSame([120, 80, IMAGETYPE_PNG], array_slice(getimagesizefromstring($png), 0, 3));
        $this->assertSame($jpeg, $this->get($url('jpg'))->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertDownload("pattern-{$pattern->id}.jpg")->streamedContent());

        $pattern->update(['pattern_data' => [...$drawing, 'editorEditVersion' => 2]]);
        $regenerated = $this->get($url('jpg'))->assertOk()->streamedContent();
        $this->assertSame([40, 30, IMAGETYPE_JPEG], array_slice(getimagesizefromstring($regenerated), 0, 3));
    }
}
