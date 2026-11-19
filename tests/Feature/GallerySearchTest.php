<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Models\User;
use App\Services\PatternService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class GallerySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_links_are_root_relative_for_guests_and_owners(): void
    {
        $author = User::factory()->create(['login' => 'gallery-author']);
        Pattern::create(['user_id' => $author->id, 'shared' => true, 'title' => 'Pattern']);
        $service = app(PatternService::class);

        $guestUrl = $service->getFilteredPatternsForCommonGallery()['data'][0]['author']['url'];
        $this->assertSame(route('gallery.author', encodeModelId($author->id), absolute: false), $guestUrl);
        $this->assertStringStartsWith('/', $guestUrl);

        $this->actingAs($author);
        $ownerUrl = $service->getFilteredPatternsForCommonGallery()['data'][0]['author']['url'];
        $this->assertSame(route('profile.index', absolute: false), $ownerUrl);
        $this->assertStringStartsWith('/', $ownerUrl);
    }

    public function test_search_combines_title_and_description_with_category_sort_and_pagination(): void
    {
        $author = User::factory()->create(['login' => 'gallery-author']);
        $category = PatternCategory::create();
        for ($i = 1; $i <= 6; $i++) {
            $pattern = Pattern::create([
                'user_id' => $author->id, 'shared' => true,
                'title' => $i === 6 ? 'Another pattern' : "Нова схема $i",
                'description' => $i === 6 ? 'Нова заготовка' : null,
            ]);
            $pattern->forceFill(['created_at' => now()->subDays(7 - $i)])->save();
            $pattern->category()->attach($category);
        }
        Pattern::create(['user_id' => $author->id, 'shared' => true, 'title' => 'Нова без категорії']);
        Pattern::create(['user_id' => $author->id, 'shared' => false, 'title' => 'Нова чернетка'])
            ->category()->attach($category);

        $query = ['patterns_search' => 'Нова', 'pattern_category_filter' => encodeModelId($category->id),
            'patterns_sort_by' => 'oldest', 'page' => 2];
        $this->app->instance('request', Request::create('/gallery', 'GET', $query));
        $results = app(PatternService::class)->getFilteredPatternsForCommonGallery();

        $this->assertSame(6, $results['total']);
        $this->assertSame(['Another pattern'], array_column($results['data'], 'title'));
        parse_str(parse_url($results['prev_page_url'], PHP_URL_QUERY), $previous);
        $this->assertSame('Нова', $previous['patterns_search']);
        $this->assertSame($query['pattern_category_filter'], $previous['pattern_category_filter']);
    }

    public function test_author_search_keeps_wildcards_literal_and_limits_results_to_the_author(): void
    {
        $author = User::factory()->create(['login' => 'gallery-author']);
        $other = User::factory()->create(['login' => 'other-author']);
        Pattern::create(['user_id' => $author->id, 'shared' => true, 'title' => '100%_beads']);
        Pattern::create(['user_id' => $author->id, 'shared' => true, 'title' => '100x-beads']);
        Pattern::create(['user_id' => $other->id, 'shared' => true, 'title' => '100%_beads']);
        $this->app->instance('request', Request::create('/gallery', 'GET', ['patterns_search' => '  %_BEADS  ']));
        $results = app(PatternService::class)->getFilteredPatternsForAuthorGalery(encodeModelId($author->id));

        $this->assertSame(1, $results['total']);
        $this->assertSame(['100%_beads'], array_column($results['data'], 'title'));
    }
}
