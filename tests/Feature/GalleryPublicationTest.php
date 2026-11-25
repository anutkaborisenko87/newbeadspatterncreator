<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Language;
use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_revisions_are_private_until_replaced_copied_or_discarded(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $author = User::factory()->create(['login' => 'gallery-author']);
        $other = User::factory()->create(['login' => 'other-author']);
        $category = PatternCategory::create();
        $originalDrawing = ['gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => ['20,20' => '#0000ff']];
        $original = Pattern::create([
            'user_id' => $author->id, 'title' => 'Original', 'published' => true, 'shared' => true,
            'photo' => '/storage/product-photos/original.jpg',
            'pattern_data' => $originalDrawing,
        ]);
        $original->category()->attach($category);
        $open = route('editor-draft.revision', $original);
        $this->actingAs($other)->post($open)->assertNotFound();
        $this->actingAs($author)->post($open)->assertStatus(303);
        $draft = Pattern::where('published', false)->sole();
        $this->post($open)->assertRedirect(route('editor-draft.edit', $draft));
        $changed = [...$originalDrawing, 'drawnCells' => ['20,20' => '#ff0000']];
        $payload = [
            'owner' => $author->id, 'version' => 9_000_000_000_000_000, 'drawing' => $changed,
            'title' => 'Updated', 'category' => $category->id, 'publicationId' => (string) Str::uuid(),
            'publicationMode' => 'replace',
        ];
        $this->putJson(route('editor-draft.saved.update', $draft), $payload)->assertOk();
        $this->assertSame($changed, $draft->fresh()->editorSnapshot()['drawing']);
        $this->assertSame($originalDrawing, $original->fresh()->pattern_data);
        $publish = route('editor-draft.publish', $draft);
        $this->postJson($publish, $payload)->assertOk()->assertJsonPath('workspace.drawing.drawnCells', []);
        $this->postJson($publish, $payload)->assertOk();
        $this->assertModelMissing($draft);
        $this->assertSame('Updated', $original->fresh()->title);
        $this->assertSame('/storage/product-photos/original.jpg', $original->fresh()->photo);
        $this->assertSame($changed['drawnCells'], $original->fresh()->pattern_data['drawnCells']);

        $this->post($open)->assertStatus(303);
        $draft = Pattern::where('published', false)->sole();
        $this->postJson(route('editor-draft.publish', $draft), [
            ...$payload, 'publicationId' => (string) Str::uuid(), 'publicationMode' => 'new', 'title' => 'Copy',
        ])->assertOk();
        $this->assertSame('Updated', $original->fresh()->title);
        $this->assertDatabaseHas('patterns', ['id' => $draft->id, 'published' => true, 'title' => 'Copy']);

        $this->post($open)->assertStatus(303);
        $draft = Pattern::where('published', false)->sole();
        $this->delete(route('editor-draft.destroy', ['pattern' => $draft, 'returnToEditor' => 1]))
            ->assertRedirect(route('page', ['slug' => 'editor']));
        $this->assertModelMissing($draft);
        $this->assertModelExists($original);
    }

    public function test_author_can_publish_to_an_existing_category_and_share_the_preview(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $author = User::factory()->create(['login' => 'gallery-author']);
        $category = PatternCategory::create();
        $url = route('editor-draft.publish');
        $payload = [
            'owner' => $author->id,
            'version' => 1,
            'drawing' => ['gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => ['20,20' => '#0000ff']],
            'title' => 'Bead pattern',
            'description' => 'An optional description',
            'category' => $category->id,
            'publicationId' => (string) Str::uuid(),
        ];

        $this->postJson($url, $payload)->assertUnauthorized();
        $upload = [...$payload, 'drawing' => json_encode($payload['drawing'])];
        $this->actingAs($author)->post($url, [...$upload, 'photo' => UploadedFile::fake()->create('invalid.txt')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->post($url, [...$upload, 'photo' => UploadedFile::fake()->image('bracelet.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('workspace.drawing.drawnCells', [])
            ->assertJsonPath('editorUrl', route('page', ['slug' => 'editor'], absolute: false));
        $this->postJson($url, $payload)->assertOk();
        $pattern = Pattern::sole();
        $this->assertStringStartsWith('/storage/product-photos/', $pattern->photo);
        Storage::disk('public')->assertExists(substr($pattern->photo, strlen('/storage/')));
        $this->assertTrue((bool) $pattern->published);
        $this->assertTrue((bool) $pattern->shared);
        $this->assertSame($category->id, $pattern->category()->sole()->id);
        $this->assertSame($payload['title'], $pattern->title);
        auth()->logout();
        $this->get(route('gallery.pattern.preview', $pattern))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_publication_translates_a_new_category_and_can_retry_after_service_failure(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $author = User::factory()->create(['login' => 'gallery-author']);
        $other = User::factory()->create(['login' => 'other-author']);
        $uk = Language::create(['slug' => 'uk', 'locale' => 'uk', 'title' => 'Українська', 'name' => 'Ukrainian']);
        $en = Language::create(['slug' => 'en', 'locale' => 'en', 'title' => 'English', 'name' => 'English']);
        $ru = Language::create(['slug' => 'ru', 'locale' => 'ru', 'title' => 'Русский', 'name' => 'Russian']);
        app()->setLocale('uk');
        Http::preventStrayRequests();
        Http::fake(['https://api.mymemory.translated.net/get*' => Http::sequence()
            ->push(['responseStatus' => 429], 429)
            ->push(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Ornaments']])
            ->push(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Орнаменты']])]);
        $drawing = ['gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => ['20,20' => '#0000ff']];
        $draft = Pattern::create(['user_id' => $author->id, 'pattern_data' => [...$drawing, 'editorDraftVersion' => '1']]);
        $url = route('editor-draft.publish', $draft);
        $payload = [
            'owner' => $author->id, 'version' => 2, 'drawing' => $drawing,
            'title' => 'Схема', 'category' => 'new', 'categoryName' => 'Орнаменти',
            'publicationId' => (string) Str::uuid(),
        ];

        $this->get(route('gallery.pattern.preview', $draft))->assertNotFound();
        $this->actingAs($other)->postJson($url, $payload)->assertNotFound();
        $this->actingAs($author)->postJson($url, [...$payload, 'categoryName' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('categoryName');
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('categoryName');
        $this->assertDatabaseCount('pattern_categories', 0);
        $this->assertFalse((bool) $draft->fresh()->published);
        $translations = [$uk->id => 'Орнаменти', $en->id => 'Ornaments', $ru->id => 'Орнаменты'];
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['q'] === 'Орнаменти' && $request['langpair'] === 'uk|en');
        Http::assertSent(fn ($request) => $request['q'] === 'Орнаменти' && $request['langpair'] === 'uk|ru');
        $this->assertDatabaseCount('patterns', 1);
        $category = PatternCategory::sole();
        $this->assertSame($translations, $category->translations()->orderBy('language_id')->pluck('title', 'language_id')->all());
        $this->assertSame($category->id, $draft->category()->sole()->id);
        $this->assertTrue((bool) $draft->fresh()->published);
    }
}
