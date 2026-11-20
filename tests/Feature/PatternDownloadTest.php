<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Models\Pattern;
use App\Models\User;
use App\Services\PatternService;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, LocaleMiddleware::class]);
        $this->app->setLocale('uk');
    }

    public function test_download_always_contains_a_square_grid_and_uses_the_saved_offset_setting(): void
    {
        $author = User::factory()->create(['login' => 'author']);
        $pattern = Pattern::create([
            'user_id' => $author->id, 'published' => true, 'shared' => true,
            'pattern_data' => [
                'gridWidth' => 50, 'gridHeight' => 40,
                'drawnCells' => ['20,20' => '#ff0000', '30,20' => '#0000ff'],
                'view' => ['showBeadPreview' => false, 'offsetDirection' => 'horizontal'],
            ],
        ]);
        $png = route('gallery.pattern.download', ['pattern' => $pattern, 'format' => 'png']);
        $flat = imagecreatefromstring($this->get($png)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertDownload("pattern-{$pattern->id}.png")->streamedContent());
        $this->assertSame(0xff0000, imagecolorat($flat, 28, 60));
        $this->assertLessThan(250, imagesx($flat));

        $pattern->update(['pattern_data' => [
            ...$pattern->pattern_data, 'view' => ['showBeadPreview' => true, 'offsetDirection' => 'horizontal'],
        ]]);
        $horizontal = imagecreatefromstring($this->get($png)->assertOk()->streamedContent());
        $this->assertGreaterThan(imagesx($flat), imagesx($horizontal));
        $this->assertSame(0xff0000, imagecolorat($horizontal, 28, 60));
        $this->assertSame(0xff0000, imagecolorat($horizontal, 236, 60));

        $pattern->update(['pattern_data' => [
            ...$pattern->pattern_data, 'view' => ['showBeadPreview' => true, 'offsetDirection' => 'vertical'],
        ]]);
        $vertical = imagecreatefromstring($this->get($png)->assertOk()->streamedContent());
        $this->assertGreaterThan(imagesy($horizontal), imagesy($vertical));
        $this->assertSame(0x0000ff, imagecolorat($vertical, 204, 72));
    }

    public function test_private_copy_download_respects_both_dictation_directions(): void
    {
        $author = User::factory()->create(['login' => 'author']);
        $owner = User::factory()->create(['login' => 'owner']);
        $source = Pattern::create(['user_id' => $author->id, 'published' => true, 'shared' => true]);
        $copy = new Pattern([
            'user_id' => $owner->id,
            'pattern_data' => [
                'gridWidth' => 50, 'gridHeight' => 40,
                'drawnCells' => [
                    '20,20' => '#ff0000', '30,20' => '#00ff00',
                    '20,50' => '#0000ff', '30,50' => '#ffff00',
                ],
                'view' => ['showBeadPreview' => false, 'bottomUp' => false, 'leftToRight' => true],
            ],
        ]);
        $copy->copied_from_id = $source->id;
        $copy->save();
        $owned = route('gallery.mine.download', ['pattern' => $copy, 'format' => 'png']);
        $public = route('gallery.pattern.download', ['pattern' => $copy, 'format' => 'png']);
        $this->getJson($owned)->assertUnauthorized();
        $this->actingAs($author)->get($owned)->assertNotFound();
        $this->actingAs($owner)->get($public)->assertNotFound();
        $this->getJson($owned . '?rows=diagonal')->assertUnprocessable();

        $item = app(PatternService::class)->getOwnerGalleryPatterns($owner->id)['data'][0];
        $this->assertSame('top_down', $item['download']['rows']);
        $this->assertSame('left_to_right', $item['download']['columns']);
        $this->assertSame(route('gallery.mine.download', ['pattern' => $copy, 'format' => 'png'], absolute: false), $item['download']['png']);

        $flat = imagecreatefromstring($this->get($owned)->assertOk()->streamedContent());
        $forward = imagecreatefromstring($this->get($owned . '?dictation=1')->assertOk()->streamedContent());
        $reverse = imagecreatefromstring($this->get($owned . '?dictation=1&rows=bottom_up&columns=right_to_left')
            ->assertOk()->streamedContent());
        $this->assertGreaterThan(imagesx($flat), imagesx($forward));
        $this->assertSame([[0xff0000, 0x00ff00], [0x0000ff, 0xffff00]], $this->lastDictationRows($forward, imagesx($flat)));
        $this->assertSame([[0xffff00, 0x0000ff], [0x00ff00, 0xff0000]], $this->lastDictationRows($reverse, imagesx($flat)));

        $jpg = $this->get($item['download']['jpg'] . '?dictation=1')->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertDownload("pattern-{$copy->id}.jpg")->streamedContent();
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($jpg)[2]);
    }

    private function lastDictationRows(GdImage $image, int $left): array
    {
        $beadColors = [0xff0000, 0x00ff00, 0x0000ff, 0xffff00];
        $rows = [];
        $previous = [];
        for ($y = 0; $y < imagesy($image); $y++) {
            $swatches = [];
            for ($x = $left; $x < imagesx($image); $x++) {
                $color = imagecolorat($image, $x, $y);
                if (in_array($color, $beadColors, true) && !in_array($color, $swatches, true)) {
                    $swatches[] = $color;
                }
            }
            if ($swatches && !$previous) {
                $rows[] = $swatches;
            }
            $previous = $swatches;
        }

        return array_slice($rows, -2);
    }
}
