<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Composes the branded card a shared short previews with (D1): the poster, a
 * dark scrim, the title and a play glyph, at 1200×630 for OG.
 *
 * Done with GD rather than a render service: this is an image, not a video, and
 * pulling in a managed renderer for it would be paying twice.
 *
 * TODO(owner): the card carries no Tixello wordmark yet — drop a transparent PNG
 * at storage/app/public/brand/tixello-share.png and it gets composited in.
 */
class GenerateShareCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const LOGO_PATH = 'brand/tixello-share.png';

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(protected int $shortId) {}

    public function handle(): void
    {
        $short = Short::find($this->shortId);

        if (! $short || ! $short->poster_path) {
            return;
        }

        if (! function_exists('imagecreatetruecolor')) {
            Log::info('GenerateShareCardJob: GD unavailable, skipping', ['short_id' => $this->shortId]);

            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($short->poster_path)) {
            return;
        }

        try {
            $poster = @imagecreatefromstring($disk->get($short->poster_path));

            if ($poster === false) {
                return;
            }

            $card = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

            // Cover-fit the vertical poster into a landscape card.
            $this->drawCover($card, $poster);
            imagedestroy($poster);

            $this->drawScrim($card);
            $this->drawTitle($card, $short->title ?? '');
            $this->drawLogo($card, $disk);

            ob_start();
            imagejpeg($card, null, 86);
            $bytes = (string) ob_get_clean();
            imagedestroy($card);

            $path = "shorts/share-cards/{$short->id}.jpg";
            $disk->put($path, $bytes);

            $short->forceFill(['share_card_path' => $path])->save();
        } catch (\Throwable $e) {
            Log::warning('GenerateShareCardJob failed', ['short_id' => $this->shortId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  \GdImage  $card
     * @param  \GdImage  $poster
     */
    protected function drawCover($card, $poster): void
    {
        $sourceW = imagesx($poster);
        $sourceH = imagesy($poster);

        $scale = max(self::WIDTH / $sourceW, self::HEIGHT / $sourceH);
        $cropW = (int) round(self::WIDTH / $scale);
        $cropH = (int) round(self::HEIGHT / $scale);

        imagecopyresampled(
            $card,
            $poster,
            0,
            0,
            (int) round(($sourceW - $cropW) / 2),
            // Bias upward: faces and titles live in the top half of a vertical poster.
            (int) round(($sourceH - $cropH) / 3),
            self::WIDTH,
            self::HEIGHT,
            $cropW,
            $cropH,
        );
    }

    /** @param \GdImage $card */
    protected function drawScrim($card): void
    {
        // Bottom-up gradient so light posters keep the title readable.
        for ($y = (int) (self::HEIGHT * 0.45); $y < self::HEIGHT; $y++) {
            $progress = ($y - self::HEIGHT * 0.45) / (self::HEIGHT * 0.55);
            $alpha = (int) round(127 - ($progress * 105));
            $colour = imagecolorallocatealpha($card, 8, 6, 18, max(0, min(127, $alpha)));
            imageline($card, 0, $y, self::WIDTH, $y, $colour);
        }
    }

    /** @param \GdImage $card */
    protected function drawTitle($card, string $title): void
    {
        if ($title === '') {
            return;
        }

        $white = imagecolorallocate($card, 255, 255, 255);
        // Built-in font only — bundling a TTF is a licensing question, not a
        // technical one, so it stays an owner decision.
        imagestring($card, 5, 48, self::HEIGHT - 90, substr($title, 0, 90), $white);
    }

    /**
     * @param  \GdImage  $card
     */
    protected function drawLogo($card, Filesystem $disk): void
    {
        if (! $disk->exists(self::LOGO_PATH)) {
            return;
        }

        $logo = @imagecreatefromstring($disk->get(self::LOGO_PATH));

        if ($logo === false) {
            return;
        }

        $logoW = imagesx($logo);
        $logoH = imagesy($logo);
        $target = 140;
        $scaled = (int) round($logoH * ($target / $logoW));

        imagecopyresampled($card, $logo, 48, 44, 0, 0, $target, $scaled, $logoW, $logoH);
        imagedestroy($logo);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateShareCardJob failed', ['short_id' => $this->shortId, 'error' => $e->getMessage()]);
    }
}
