<?php

namespace App\Jobs\Shorts;

use App\Services\Shorts\ShortAutoGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates a short for one event, artist or venue (B3).
 *
 * Takes the morph type and key rather than the model so a queued job cannot
 * carry a stale serialised record across a deploy — and so the same job covers
 * all three owner kinds.
 */
class GenerateShortFromOwnerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        protected string $ownerType,
        protected int $ownerId,
    ) {}

    public function handle(ShortAutoGenerator $generator): void
    {
        $owner = $this->resolveOwner();

        if (! $owner) {
            return;
        }

        $generator->generate($owner);
    }

    protected function resolveOwner(): ?Model
    {
        $class = Relation::getMorphedModel($this->ownerType) ?? $this->ownerType;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            Log::warning('GenerateShortFromOwnerJob: unknown owner type', ['type' => $this->ownerType]);

            return null;
        }

        return $class::query()->find($this->ownerId);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateShortFromOwnerJob failed', [
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'error' => $e->getMessage(),
        ]);
    }
}
