<?php

namespace Tests\Feature;

use App\DTO\PatternGalleryDTO;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternLikesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
    }

    public function test_readers_can_set_and_remove_only_their_own_like_and_the_owner_can_see_their_galleries(): void
    {
        $author = User::factory()->create(['login' => 'author']);
        $reader = User::factory()->create(['login' => 'reader']);
        $second = User::factory()->create(['login' => 'second-reader']);
        $pattern = Pattern::create(['user_id' => $author->id, 'published' => true, 'shared' => true]);
        $url = route('gallery.pattern.likes.update', $pattern);
        $list = route('gallery.pattern.likes.index', $pattern);
        $likes = fn () => PatternGalleryDTO::fromModel($pattern->fresh())->likes;

        $this->assertNull($likes()['updateUrl']);
        $this->assertNull($likes()['listUrl']);
        $this->putJson($url, ['liked' => true])->assertUnauthorized();
        $this->getJson($list)->assertUnauthorized();

        $this->actingAs($reader);
        $this->assertNotNull($likes()['updateUrl']);
        $this->assertNull($likes()['listUrl']);
        $this->putJson($url, ['liked' => true])->assertStatus(303);
        $this->putJson($url, ['liked' => true])->assertStatus(303);
        $this->assertSame(1, $pattern->likes()->count());
        $this->assertTrue($likes()['liked']);
        $this->getJson($list)->assertNotFound();
        $this->actingAs($second)->putJson($url, ['liked' => true])->assertStatus(303);

        $this->actingAs($author);
        $this->assertNull($likes()['updateUrl']);
        $this->assertNotNull($likes()['listUrl']);
        $this->putJson($url, ['liked' => true])->assertForbidden();
        $this->putJson($url, ['liked' => false])->assertForbidden();
        $this->getJson($list)->assertOk()->assertExactJson(['users' => [
            ['login' => 'reader', 'url' => route('gallery.author', encodeModelId($reader->id), absolute: false)],
            ['login' => 'second-reader', 'url' => route('gallery.author', encodeModelId($second->id), absolute: false)],
        ]]);

        $this->actingAs($reader)->putJson($url, ['liked' => false])->assertStatus(303);
        $this->putJson($url, ['liked' => false])->assertStatus(303);
        $this->assertFalse($likes()['liked']);
        $this->assertSame(1, $likes()['count']);
        $this->actingAs($second);
        $this->assertTrue($likes()['liked']);
        $this->putJson($url, ['liked' => false])->assertStatus(303);
        $this->actingAs($author)->getJson($list)->assertExactJson(['users' => []]);
    }

    public function test_private_patterns_do_not_accept_likes(): void
    {
        $author = User::factory()->create(['login' => 'owner']);
        $reader = User::factory()->create(['login' => 'reader']);
        $draft = Pattern::create(['user_id' => $author->id]);

        $this->actingAs($reader)->putJson(route('gallery.pattern.likes.update', $draft), ['liked' => true])->assertNotFound();
        $this->actingAs($author)->getJson(route('gallery.pattern.likes.index', $draft))->assertNotFound();
        $this->assertSame(0, $draft->likes()->count());
    }
}
