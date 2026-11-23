<?php

namespace Tests\Feature;

use App\Contracts\Services\PageServiceInterface;
use App\DTO\PageDataDTO;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\EditorDraft;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_drafts_page_paginates_only_the_signed_in_authors_drafts(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $owner = User::factory()->create(['login' => 'draft-owner']);
        $other = User::factory()->create(['login' => 'other-author']);
        for ($i = 1; $i <= 10; $i++) {
            Pattern::create(['user_id' => $owner->id, 'title' => "Draft $i", 'pattern_data' => ['editorDraftVersion' => 1]]);
        }
        Pattern::create(['user_id' => $owner->id, 'published' => true]);
        Pattern::create(['user_id' => $other->id]);

        $url = route('profile.drafts');
        $this->getJson($url)->assertUnauthorized();
        $gallery = 'props.data.sections.0.gallery_items';
        $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath("$gallery.total", 10)->assertJsonCount(9, "$gallery.data");
        $this->get(route('profile.drafts', ['drafts_page' => 2]))->assertOk()
            ->assertJsonCount(1, "$gallery.data")->assertJsonPath("$gallery.data.0.title", 'Draft 1');
    }

    public function test_editor_view_is_restored_and_controls_the_saved_preview_grid(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $owner = User::factory()->create(['login' => 'view-owner']);
        $view = ['showBeadPreview' => true, 'offsetDirection' => 'vertical',
            'showDictation' => false, 'bottomUp' => false, 'leftToRight' => false];
        $drawing = ['gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['30,20' => '#0000ff'], 'view' => $view];
        $snapshot = ['owner' => $owner->id, 'version' => 1, 'drawing' => $drawing];
        $working = route('editor-draft.update');
        $this->actingAs($owner)->putJson($working, $snapshot)->assertOk();
        $this->assertSame($view, EditorDraft::findOrFail($owner->id)->drawing['view']);

        $this->postJson($working, $snapshot)->assertOk()->assertJsonPath('drawing.view', $view);
        $pattern = Pattern::sole();
        $this->assertSame($view, $pattern->editorSnapshot()['drawing']['view']);
        $url = route('editor-draft.preview', $pattern);
        $image = imagecreatefromstring($this->get($url)->assertOk()->streamedContent());
        $this->assertSame(35, imagesy($image));
        $this->assertSame(0x0000ff, imagecolorat($image, 13, 8));

        $drawing['view']['showBeadPreview'] = false;
        $this->putJson(route('editor-draft.saved.update', $pattern), [
            ...$snapshot, 'version' => 2, 'drawing' => $drawing,
        ])->assertOk();
        $image = imagecreatefromstring($this->get($url)->assertOk()->streamedContent());
        $this->assertSame(30, imagesy($image));
        $this->assertSame(0x0000ff, imagecolorat($image, 13, 3));
    }

    public function test_only_the_owner_can_delete_an_unpublished_editor_draft(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $owner = User::factory()->create(['login' => 'draft-owner']);
        $other = User::factory()->create(['login' => 'other-user']);
        $draft = Pattern::create(['user_id' => $owner->id, 'pattern_data' => ['editorDraftVersion' => '1']]);
        $published = Pattern::create([
            'user_id' => $owner->id, 'published' => true, 'pattern_data' => ['editorDraftVersion' => '2'],
        ]);
        $url = route('editor-draft.destroy', $draft);
        $profile = route('profile.index', ['drafts_sort_by' => 'newest']);

        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs($other)->deleteJson($url)->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('editor-draft.destroy', $published))->assertNotFound();
        $this->from($profile)->delete($url)->assertStatus(303)->assertRedirect($profile);
        $this->assertModelMissing($draft);
        $this->assertModelExists($published);
    }

    public function test_starting_from_a_saved_draft_preserves_both_drawings_and_opens_a_blank_workspace(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $owner = User::factory()->create(['login' => 'draft-owner']);
        $this->actingAs($owner);
        $workingUrl = route('editor-draft.update');
        $drawing = ['gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['20,40' => '#0000ff']];
        $payload = ['owner' => $owner->id, 'version' => 2, 'drawing' => $drawing];
        $this->postJson($workingUrl, $payload)->assertOk();
        $saved = Pattern::sole();
        $this->putJson($workingUrl, [...$payload, 'version' => 5])->assertOk();

        $edited = [...$drawing, 'drawnCells' => ['30,30' => '#ff0000']];
        $restart = [...$payload, 'version' => 4, 'drawing' => $edited, 'operation' => 6];
        $url = route('editor-draft.edit', ['pattern' => $saved]);
        $this->postJson($url, $restart)->assertOk()
            ->assertJsonPath('editorUrl', route('page', ['slug' => 'editor'], absolute: false));
        $this->assertSame($edited, $saved->refresh()->editorSnapshot()['drawing']);
        $this->assertSame($drawing, Pattern::whereKeyNot($saved->id)->sole()->editorSnapshot()['drawing']);
        $this->assertSame(['version' => 6, 'drawing' => [...$drawing, 'drawnCells' => []]], EditorDraft::findOrFail($owner->id)->snapshot());

        $this->putJson($workingUrl, [...$payload, 'version' => 7])->assertOk();
        $this->postJson($url, $restart)->assertOk();
        $this->assertDatabaseCount('patterns', 2);
        $this->assertSame(7, EditorDraft::findOrFail($owner->id)->version);
    }

    public function test_saved_preview_includes_the_client_capture_for_the_current_version(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $owner = User::factory()->create(['login' => 'draft-owner']);
        $working = route('editor-draft.update');
        $drawing = ['gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['20,40' => '#0000ff']];
        $image = imagecreatetruecolor(800, 300);
        ob_start();
        imagejpeg($image);
        $capture = ob_get_clean();
        $payload = ['owner' => $owner->id, 'version' => 2, 'drawing' => $drawing, 'preview' => base64_encode($capture)];

        $this->actingAs($owner)->postJson($working, $payload)->assertOk();
        $pattern = Pattern::sole();
        $preview = route('editor-draft.preview', ['pattern' => $pattern]);
        $update = route('editor-draft.saved.update', ['pattern' => $pattern]);
        $legacy = $this->get($preview)->assertHeader('Content-Type', 'image/png')->streamedContent();
        $this->assertSame([40, 30], array_slice(getimagesizefromstring($legacy), 0, 2));

        $this->putJson($update, [...$payload, 'version' => 3, 'preview' => 'not-a-jpeg'])->assertUnprocessable();
        $this->putJson($update, [...$payload, 'version' => 3, 'preview' => null])->assertOk();
        $this->assertSame(2, $pattern->refresh()->editorSnapshot()['previewVersion']);
        $this->putJson($update, [...$payload, 'version' => 3, 'previewFormat' => 'flat-grid'])->assertOk();
        $this->assertSame(3, $pattern->refresh()->editorSnapshot()['previewVersion']);
        $this->assertSame($capture, $this->get($preview)->assertHeader('Content-Type', 'image/jpeg')->streamedContent());
    }

    public function test_saved_draft_opens_and_autosaves_without_replacing_the_working_draft(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $this->mock(PageServiceInterface::class)
            ->shouldReceive('getPageData')->with('editor')
            ->andReturn(new PageDataDTO(1, 'Editor', null, null, [['slug' => 'editor_page_section']], [], []));

        $owner = User::factory()->create(['login' => 'draft-owner']);
        $other = User::factory()->create(['login' => 'other-user']);
        $drawing = ['gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['20,40' => '#0000ff']];
        $working = route('editor-draft.update');
        $original = ['owner' => $owner->id, 'version' => 2, 'drawing' => $drawing];
        $this->actingAs($owner)->postJson($working, $original)->assertOk();
        $pattern = Pattern::sole();
        $editor = route('editor-draft.edit', ['pattern' => $pattern]);
        $update = route('editor-draft.saved.update', ['pattern' => $pattern]);

        $this->getJson($editor, ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.editorDraft.drawing', $drawing)
            ->assertJsonPath('props.editorSavedDraftId', $pattern->id);

        $edited = [...$drawing, 'drawnCells' => ['30,20' => '#ff0000']];
        $payload = [...$original, 'version' => 4, 'drawing' => $edited];
        $this->putJson($update, $payload)->assertOk()->assertJsonPath('version', 4);
        $this->putJson($update, $original)->assertOk()->assertJsonPath('version', 4);
        $this->getJson($editor, ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.editorDraft.drawing', $edited);

        $this->assertSame('2', $pattern->refresh()->pattern_data['editorDraftVersion']);
        $this->assertDatabaseCount('patterns', 1);
        $this->assertSame([], EditorDraft::findOrFail($owner->id)->drawing['drawnCells']);
        $this->actingAs($other)->getJson($editor)->assertNotFound();
        $this->putJson($update, [...$payload, 'owner' => $other->id])->assertNotFound();
    }

    public function test_archiving_saves_a_profile_draft_and_starts_an_empty_working_draft(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $owner = User::factory()->create(['login' => 'draft-owner']);
        $other = User::factory()->create(['login' => 'other-user']);
        $url = route('editor-draft.update');
        $drawing = ['gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['20,40' => '#0000ff']];
        $payload = ['owner' => $owner->id, 'version' => 2, 'drawing' => $drawing];

        $this->postJson($url, $payload)->assertUnauthorized();
        $this->actingAs($other)->postJson($url, $payload)->assertForbidden();
        $this->actingAs($owner)->postJson($url, $payload)->assertOk()
            ->assertJsonPath('version', 3)->assertJsonPath('drawing.drawnCells', []);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('version', 3);
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('version', 3);
        $this->postJson($url, [...$payload, 'version' => 1])->assertConflict();

        $pattern = Pattern::sole();
        $this->assertDatabaseHas('patterns', ['id' => $pattern->id, 'user_id' => $owner->id, 'published' => false, 'shared' => false]);
        $this->assertSame($drawing['drawnCells'], $pattern->pattern_data['drawnCells']);
        $this->assertSame(['version' => 3, 'drawing' => [...$drawing, 'drawnCells' => []]], EditorDraft::findOrFail($owner->id)->snapshot());

        $preview = route('editor-draft.preview', ['pattern' => $pattern]);
        $image = $this->get($preview)->assertOk()->assertHeader('Content-Type', 'image/png')->streamedContent();
        $this->assertStringStartsWith("\x89PNG", $image);
        $this->actingAs($other)->get($preview)->assertNotFound();
    }

    public function test_editor_restores_the_latest_draft_only_for_its_owner(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $this->mock(PageServiceInterface::class)
            ->shouldReceive('getPageData')->with('editor')
            ->andReturn(new PageDataDTO(1, 'Editor', null, null, [['slug' => 'editor_page_section']], [], []));

        $owner = User::factory()->create(['login' => 'draft-owner']);
        $other = User::factory()->create(['login' => 'other-user']);
        $url = route('editor-draft.update');
        $editor = route('page', ['slug' => 'editor']);
        $drawing = ['gridWidth' => 40, 'gridHeight' => 30, 'drawnCells' => ['20,40' => '#0000ff']];
        $snapshot = ['version' => 2, 'drawing' => $drawing];
        $payload = ['owner' => $owner->id, ...$snapshot];

        $this->putJson($url, $payload)->assertUnauthorized();
        $this->actingAs($owner)->putJson($url, $payload)->assertOk()->assertJsonPath('version', 2);
        $this->putJson($url, [...$payload, 'version' => 1, 'drawing' => [...$drawing, 'drawnCells' => []]])
            ->assertOk()->assertJsonPath('version', 2);
        $this->putJson($url, [...$payload, 'version' => 3, 'drawing' => [...$drawing, 'drawnCells' => ['60,40' => '#0000ff']]])
            ->assertUnprocessable();

        $this->getJson($editor, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.editorDraft', $snapshot);
        $this->actingAs($other)->putJson($url, $payload)->assertForbidden();
        $this->getJson($editor, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.editorDraft', null);
    }
}
