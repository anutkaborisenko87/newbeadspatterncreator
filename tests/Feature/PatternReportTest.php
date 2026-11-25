<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Pattern;
use App\Models\PatternCategory;
use App\Models\PatternReport;
use App\Models\User;
use App\Notifications\PatternReportSubmitted;
use App\Services\PatternReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatternReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_notifies_both_parties_and_block_applies_to_existing_revisions(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        Notification::fake();
        $author = User::factory()->create(['login' => 'author']);
        $reader = User::factory()->create(['login' => 'reader']);
        $drawing = ['gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => ['20,20' => '#0000ff']];
        $pattern = Pattern::create(['title' => 'Bracelet', 'user_id' => $author->id, 'published' => true, 'shared' => true, 'pattern_data' => $drawing]);
        $url = route('patterns.report', encodeModelId($pattern->id));
        $payload = ['topic' => 'copyright', 'message' => 'Copied design'];
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->actingAs($author)->postJson($url, $payload)->assertForbidden();
        $this->post(route('editor-draft.revision', $pattern))->assertRedirect();
        $draft = Pattern::where('published', false)->sole();
        $this->actingAs($reader)->postJson($url, ['topic' => 'invalid'])->assertJsonValidationErrors(['topic', 'message']);
        $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)->assertCreated();
        $report = PatternReport::sole();
        $this->assertSame($drawing, $report->drawing);
        Notification::assertSentTo($author, PatternReportSubmitted::class);
        Notification::assertSentTo($reader, PatternReportSubmitted::class);
        Notification::assertCount(2);
        $this->get(route('pattern-reports.preview', $report))->assertForbidden();
        app(PatternReportService::class)->review($report, 'upheld', true);
        $this->assertFalse($pattern->fresh()->isPubliclyShared());
        $this->postJson($url, $payload)->assertNotFound();
        $this->assertTrue($pattern->fresh()->isInPersonalGalleryOf($author->id));
        $category = PatternCategory::create();
        foreach (['new', 'replace'] as $mode) {
            $this->actingAs($author)->postJson(route('editor-draft.publish', $draft), [
                'owner' => $author->id, 'version' => 9000000000000000, 'drawing' => $drawing,
                'title' => 'Changed', 'category' => $category->id, 'publicationId' => (string) Str::uuid(), 'publicationMode' => $mode,
            ])->assertForbidden();
        }
    }

    public function test_author_can_withdraw_without_losing_edit_access(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $author = User::factory()->create(['login' => 'author']);
        $reader = User::factory()->create(['login' => 'reader']);
        $pattern = Pattern::create(['user_id' => $author->id, 'published' => true, 'shared' => true,
            'pattern_data' => ['gridWidth' => 30, 'gridHeight' => 30, 'drawnCells' => []]]);
        $url = route('gallery.mine.unpublish', $pattern);
        $this->actingAs($reader)->delete($url)->assertForbidden();
        $this->actingAs($author)->delete($url)->assertRedirect();
        $this->assertTrue($pattern->fresh()->canRevise($author->id));
        $this->assertFalse(Pattern::publiclyShared()->whereKey($pattern->id)->exists());
    }
}
