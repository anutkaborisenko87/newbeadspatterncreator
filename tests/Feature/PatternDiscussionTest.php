<?php

namespace Tests\Feature;

use App\DTO\PatternGalleryDTO;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Pattern;
use App\Models\CommentReport;
use App\Notifications\CommentReportReviewed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class PatternDiscussionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
    }

    public function test_guests_read_the_live_discussion_and_signed_in_readers_can_reply(): void
    {
        $author = User::factory()->create(['login' => 'author']);
        $reader = User::factory()->create(['login' => 'reader']);
        $pattern = Pattern::create(['user_id' => $author->id, 'shared' => true]);
        $url = route('patterns.comments.show', encodeModelId($pattern->id));
        $feed = route('patterns.comments.feed', encodeModelId($pattern->id));
        $this->get(route('patterns.comments.legacy', $pattern))->assertRedirect($url);
        $this->assertSame(route('patterns.comments.show', encodeModelId($pattern->id), absolute: false),
            PatternGalleryDTO::fromModel($pattern)->comments['link']);
        $hidden = $pattern->comments()->create(['user_id' => $reader->id, 'comment' => 'Unapproved']);
        $this->withHeader('X-Inertia', 'true')->get($url)->assertOk()
            ->assertJsonPath('component', 'PatternDiscussion')->assertJsonPath('props.initialComments', [])
            ->assertJsonPath('props.pattern.comments.count', 0);
        $this->postJson($url, ['comment' => 'Guest'])->assertUnauthorized();
        $root = $this->actingAs($reader)->postJson($url, ['comment' => 'First'])
            ->assertCreated()->assertJsonPath('comment.login', 'reader')->json('comment.id');
        $reply = $this->actingAs($author)->postJson($url, ['comment' => 'Reply', 'parent_id' => $root])
            ->assertCreated()->assertJsonPath('comment.parent_id', $root)->json('comment.id');
        $this->postJson($url, ['comment' => ' ', 'parent_id' => $root])->assertUnprocessable();
        $this->postJson($url, ['comment' => 'Invalid', 'parent_id' => $hidden->id])->assertUnprocessable();
        $other = Pattern::create(['user_id' => $author->id, 'shared' => true]);
        $this->postJson(route('patterns.comments.store', encodeModelId($other->id)), ['comment' => 'Invalid', 'parent_id' => $root])
            ->assertUnprocessable();

        $this->app['auth']->guard()->logout();
        $this->getJson($feed)->assertOk()->assertJsonCount(2, 'comments');
        $this->getJson($feed.'?after='.$root)->assertOk()->assertJsonCount(1, 'comments')
            ->assertJsonPath('comments.0.id', $reply)->assertJsonPath('comments.0.text', 'Reply');
        $this->getJson($feed.'?after='.$reply)->assertJsonPath('comments', []);
        $this->get($url)->assertOk()->assertJsonPath('props.pattern.comments.count', 2);
    }

    public function test_private_patterns_have_no_public_discussion_feed_or_diagram(): void
    {
        $owner = User::factory()->create(['login' => 'owner']);
        $pattern = Pattern::create(['user_id' => $owner->id]);
        $this->assertNull(PatternGalleryDTO::fromModel($pattern)->comments['link']);
        foreach (['show', 'feed', 'diagram'] as $route) {
            $this->getJson(route("patterns.comments.$route", encodeModelId($pattern->id)))->assertNotFound();
        }
        $this->actingAs($owner)->postJson(route('patterns.comments.store', encodeModelId($pattern->id)), ['comment' => 'Private'])
            ->assertNotFound();
    }

    public function test_only_the_comment_author_can_edit_and_pattern_owner_can_remove_the_branch(): void
    {
        $author = User::factory()->create(['login' => 'reader']);
        $owner = User::factory()->create(['login' => 'owner']);
        $pattern = Pattern::create(['user_id' => $owner->id, 'shared' => true]);
        $other = Pattern::create(['user_id' => $owner->id, 'shared' => true]);
        $comment = $pattern->comments()->create(['user_id' => $author->id, 'approved' => true, 'comment' => 'First']);
        $url = route('patterns.comments.update', [encodeModelId($pattern->id), $comment->id]);
        $this->actingAs($author)->patchJson($url, ['comment' => ' '])->assertUnprocessable();
        $this->patchJson($url, ['comment' => 'Edited'])->assertOk()->assertJsonPath('comment.text', 'Edited');
        $this->app['auth']->guard()->logout();
        $reply = $pattern->comments()->create(['user_id' => $owner->id, 'approved' => true, 'comment' => 'Reply', 'parent_id' => $comment->id]);
        $nested = $pattern->comments()->create(['user_id' => $author->id, 'approved' => true, 'comment' => 'Nested', 'parent_id' => $reply->id]);
        $secondReply = $pattern->comments()->create(['user_id' => $author->id, 'approved' => true, 'comment' => 'Second reply', 'parent_id' => $comment->id]);
        $sibling = $pattern->comments()->create(['user_id' => $owner->id, 'approved' => true, 'comment' => 'Separate thread']);
        $this->patchJson($url, ['comment' => 'Guest'])->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs($owner)->patchJson($url, ['comment' => 'Changed'])->assertForbidden();
        $this->actingAs(User::factory()->create(['login' => 'visitor']))->deleteJson($url)->assertForbidden();
        $this->actingAs($author)->deleteJson(route('patterns.comments.destroy', [encodeModelId($other->id), $comment->id]))
            ->assertNotFound();
        $this->patchJson($url, ['comment' => 'Too late'])->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->assertSame('Edited', $comment->fresh()->comment);
        $this->actingAs($owner)->deleteJson($url)->assertNoContent();
        $this->assertModelMissing($comment);
        $this->assertModelMissing($reply);
        $this->assertModelMissing($nested);
        $this->assertModelMissing($secondReply);
        $this->getJson(route('patterns.comments.feed', encodeModelId($pattern->id)))
            ->assertJsonCount(1, 'comments')->assertJsonPath('comments.0.id', $sibling->id);
    }

    public function test_owner_can_report_another_comment_with_private_evidence_and_receive_a_decision(): void
    {
        Storage::fake('local');
        Notification::fake();
        $owner = User::factory()->create(['login' => 'owner']);
        $reader = User::factory()->create(['login' => 'reader']);
        $pattern = Pattern::create(['user_id' => $owner->id, 'shared' => true]);
        $comment = $pattern->comments()->create(['user_id' => $reader->id, 'approved' => true, 'comment' => 'Original']);
        $url = route('patterns.comments.report', [encodeModelId($pattern->id), $comment->id]);
        $payload = ['topic' => 'spam', 'message' => 'Repeated advertisements'];
        $this->actingAs($reader)->postJson($url, $payload)->assertForbidden();
        $this->actingAs($owner)->postJson($url, [...$payload, 'topic' => 'invalid'])->assertUnprocessable();
        $this->post($url, [...$payload, 'screenshots' => [UploadedFile::fake()->image('proof.png')]])->assertCreated();
        $report = CommentReport::sole();
        $comment->delete();
        $this->assertSame('Original', $report->fresh()->comment_text);
        Storage::disk('local')->assertExists($report->evidence[0]);
        $evidence = route('reports.evidence', [$report, 0]);
        $this->get($evidence)->assertForbidden();
        $admin = User::factory()->create(['login' => 'admin']);
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get($evidence)->assertOk();
        $report->review('upheld', 'Reviewed by moderator');
        $this->assertSame('Reviewed by moderator', $report->fresh()->decision);
        Notification::assertSentTo($owner, CommentReportReviewed::class);
        Notification::assertNotSentTo($reader, CommentReportReviewed::class);
    }

    public function test_only_owner_can_block_a_reader_in_one_discussion_and_restore_access(): void
    {
        $owner = User::factory()->create(['login' => 'owner']);
        $reader = User::factory()->create(['login' => 'reader']);
        $pattern = Pattern::create(['user_id' => $owner->id, 'shared' => true]);
        $other = Pattern::create(['user_id' => $owner->id, 'shared' => true]);
        $key = encodeModelId($pattern->id);
        $block = route('patterns.comments.block', [$key, $reader]);
        $post = route('patterns.comments.store', $key);
        $comment = $pattern->comments()->create(['user_id' => $reader->id, 'approved' => true, 'comment' => 'First']);
        $this->putJson($block, ['blocked' => true])->assertUnauthorized();
        $this->actingAs($reader)->putJson($block, ['blocked' => true])->assertForbidden();
        $this->actingAs($owner)->putJson(route('patterns.comments.block', [$key, $owner]), ['blocked' => true])->assertForbidden();
        $this->putJson($block, ['blocked' => true])->assertNoContent();
        $this->putJson($block, ['blocked' => true])->assertNoContent();
        $this->actingAs($reader)->postJson($post, ['comment' => 'Top level'])->assertForbidden();
        $this->postJson($post, ['comment' => 'Reply', 'parent_id' => $comment->id])->assertForbidden();
        $this->patchJson($post.'/'.$comment->id, ['comment' => 'Changed'])->assertForbidden();
        $this->assertModelExists($comment);
        $this->postJson(route('patterns.comments.store', encodeModelId($other->id)), ['comment' => 'Allowed'])->assertCreated();
        $this->putJson($block, ['blocked' => false])->assertForbidden();
        $this->actingAs($owner)->putJson($block, ['blocked' => false])->assertNoContent();
        $this->actingAs($reader)->postJson($post, ['comment' => 'Allowed again'])->assertCreated();
    }

    public function test_a_saved_comment_is_sent_to_its_discussion_channel(): void
    {
        $reader = User::factory()->create(['login' => 'reader']);
        $pattern = Pattern::create(['user_id' => $reader->id, 'shared' => true]);
        $broadcaster = \Mockery::mock(\Illuminate\Contracts\Broadcasting\Broadcaster::class);
        Broadcast::shouldReceive('connection')->once()->andReturn($broadcaster);
        $broadcaster->shouldReceive('broadcast')->once()->with(
            ['patterns.'.encodeModelId($pattern->id).'.comments'], 'comment.created',
            \Mockery::on(fn ($payload) => $payload['comment']['text'] === 'Live'
                && $payload['comment']['login'] === 'reader'
                && $pattern->comments()->whereKey($payload['comment']['id'])->exists())
        );
        $this->actingAs($reader)->postJson(route('patterns.comments.store', encodeModelId($pattern->id)), ['comment' => 'Live'])
            ->assertCreated();
    }

    public function test_a_reply_also_locks_its_author_even_before_it_is_visible(): void
    {
        $author = User::factory()->create(['login' => 'author']);
        $pattern = Pattern::create(['user_id' => $author->id, 'shared' => true]);
        $comment = $pattern->comments()->create(['user_id' => $author->id, 'approved' => true, 'comment' => 'First']);
        $reply = $pattern->comments()->create(['user_id' => $author->id, 'comment' => 'Pending', 'parent_id' => $comment->id]);
        $url = route('patterns.comments.update', [encodeModelId($pattern->id), $comment->id]);
        $this->actingAs($author)->patchJson($url, ['comment' => 'Changed'])->assertUnprocessable();
        $this->getJson(route('patterns.comments.feed', encodeModelId($pattern->id)))
            ->assertJsonCount(1, 'comments')->assertJsonPath('comments.0.has_hidden_replies', true);
        $reply->delete();
        $this->patchJson($url, ['comment' => 'Changed'])->assertOk();
    }

    public function test_the_discussion_diagram_uses_the_saved_grids_and_dictation(): void
    {
        $author = User::factory()->create(['login' => 'author']);
        $drawing = ['gridWidth' => 100, 'gridHeight' => 60, 'drawnCells' => ['20,20' => '#123456']];
        $pattern = Pattern::create(['user_id' => $author->id, 'shared' => true]);
        $url = route('patterns.comments.diagram', encodeModelId($pattern->id));
        $widths = [];
        foreach ([[false, false], [true, false], [true, true]] as [$offset, $dictation]) {
            $pattern->update(['pattern_data' => [...$drawing, 'view' => [
                'showBeadPreview' => $offset, 'offsetDirection' => 'vertical', 'showDictation' => $dictation,
                'bottomUp' => false, 'leftToRight' => false,
            ]]]);
            $png = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->streamedContent();
            $widths[] = getimagesizefromstring($png)[0];
        }
        $this->assertGreaterThan($widths[0], $widths[1]);
        $this->assertGreaterThan($widths[1], $widths[2]);
    }
}
