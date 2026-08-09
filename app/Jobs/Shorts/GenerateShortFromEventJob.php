<?php

namespace App\Jobs\Shorts;

use App\Models\Event;
use App\Services\Shorts\ShortAutoGenerator;
use App\Services\Video\VideoRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates a short for one event (B3).
 *
 * Kept as its own job because it is the case with a named caller elsewhere; the
 * logic itself moved to ShortAutoGenerator when artists and venues gained the
 * same treatment, so there is one implementation rather than three that drift.
 */
class GenerateShortFromEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(protected int $eventId) {}

    public function handle(VideoRenderer $renderer): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            return;
        }

        // Built here rather than injected so the renderer passed to handle()
        // wins — the tests drive this job with a specific renderer.
        (new ShortAutoGenerator($renderer))->generate($event);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateShortFromEventJob failed', ['event_id' => $this->eventId, 'error' => $e->getMessage()]);
    }
}
