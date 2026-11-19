<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Language;
use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_publish_to_an_existing_category_and_share_the_preview(): void
    {
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
        $this->actingAs($author)->postJson($url, $payload)->assertOk()->assertJsonStructure(['galleryUrl']);
        $this->postJson($url, $payload)->assertOk();
        $pattern = Pattern::sole();
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
