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
        $this->assertSame($capture, $this->get($preview)->assertHeader('Content-Type', 'image/jpeg')->streamedContent());

        $this->putJson($update, [...$payload, 'version' => 3, 'preview' => 'not-a-jpeg'])->assertUnprocessable();
        $this->putJson($update, [...$payload, 'version' => 3, 'preview' => null])->assertOk();
        $this->assertSame(2, $pattern->refresh()->editorSnapshot()['previewVersion']);
        $this->putJson($update, [...$payload, 'version' => 3])->assertOk();
        $this->assertSame(3, $pattern->refresh()->editorSnapshot()['previewVersion']);
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
