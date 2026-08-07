<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Computes the low-quality placeholder shown before the poster loads (D9).
 *
 * Not a true BlurHash: encoding one needs a DCT implementation and a library
 * this project does not carry. What it produces instead is a tiny average-colour
 * gradient descriptor — same job (something pleasant instead of a black hole on
 * a slow network), a fraction of the cost, and the column stays compatible if a
 * real BlurHash encoder is added later.
 *
 * TODO(owner): if the placeholder quality matters, add kornrunner/blurhash and
 * swap encode() — the column, the payload field and the client are unchanged.
 */
class GenerateBlurhashJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(protected int $shortId) {}

    public function handle(): void
    {
        $short = Short::find($this->shortId);

        if (! $short || ! $short->poster_path) {
            return;
        }

        if (! function_exists('imagecreatefromstring')) {
            Log::info('GenerateBlurhashJob: GD unavailable, skipping', ['short_id' => $this->shortId]);

            return;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($short->poster_path)) {
                return;
            }

            $hash = $this->encode($disk->get($short->poster_path));

            if ($hash) {
                $short->forceFill(['blurhash' => $hash])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('GenerateBlurhashJob failed', ['short_id' => $this->shortId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * A 2×3 grid of average colours, hex-encoded — enough for the client to
     * paint a plausible blurred stand-in.
     */
    protected function encode(string $bytes): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Downscale to the grid, letting GD do the averaging.
        $grid = imagecreatetruecolor(2, 3);
        imagecopyresampled($grid, $image, 0, 0, 0, 0, 2, 3, $width, $height);
        imagedestroy($image);

        $parts = [];
        for ($y = 0; $y < 3; $y++) {
            for ($x = 0; $x < 2; $x++) {
                $rgb = imagecolorat($grid, $x, $y);
                $parts[] = sprintf('%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
            }
        }

        imagedestroy($grid);

        return 'g2x3:'.implode('', $parts);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateBlurhashJob failed', ['short_id' => $this->shortId, 'error' => $e->getMessage()]);
    }
}
