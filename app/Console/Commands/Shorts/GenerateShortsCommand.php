<?php

namespace App\Console\Commands\Shorts;

use App\Jobs\Shorts\GenerateShortFromOwnerJob;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Sweeps the catalogue for anything that should have a short and does not (B3).
 *
 * This is the piece that makes automatic generation actually automatic. The job
 * that builds a short has existed since phase 8; nothing ever dispatched it, so
 * in practice no short was ever generated. A scheduled sweep is the right
 * trigger rather than a model observer: an event is created long before its
 * poster is uploaded, and an observer would fire on an empty record and then
 * never look again.
 *
 * Idempotent by construction — the generator skips owners that already have a
 * short — so a partial run, an overlapping run, or a nightly re-run all
 * converge on the same result.
 */
class GenerateShortsCommand extends Command
{
    protected $signature = 'shorts:generate
        {--type=* : event|artist|venue (si pluralele lor). Implicit: toate cele activate}
        {--limit= : Override the per-type batch ceiling}
        {--id=* : Generate for specific ids (requires exactly one --type)}
        {--dry-run : Report what would be queued without queueing it}';

    protected $description = 'Generate shorts from catalogue images for events, artists and venues without one';

    public function handle(): int
    {
        if (! config('shorts.autogen.enabled', true)) {
            $this->warn('Automatic generation is disabled (shorts.autogen.enabled).');

            return self::SUCCESS;
        }

        $requested = array_filter((array) $this->option('type'));
        $types = $this->resolveTypes();

        if ($types === []) {
            /* Doua cauze diferite, care cereau mesaje diferite: un `--type`
               gresit tacea la fel ca „totul dezactivat din config", iar
               comanda iesea cu SUCCESS — deci nimic nu semnala greseala. */
            if ($requested !== []) {
                $this->error(sprintf(
                    'Tip necunoscut: %s. Valori acceptate: event, artist, venue (si pluralele lor).',
                    implode(', ', $requested),
                ));

                return self::FAILURE;
            }

            $this->warn('Toate tipurile sunt dezactivate din config (shorts.autogen.*).');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('shorts.autogen.batch_limit', 200));
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($types as $type) {
            $queued = $this->sweep($type, $limit, $dryRun);
            $total += $queued;

            $this->line(sprintf(
                '%s: %d %s',
                str($type)->plural()->title()->toString(),
                $queued,
                $dryRun ? 'would be queued' : 'queued',
            ));
        }

        $this->info($dryRun
            ? "Dry run — {$total} shorts would be generated."
            : "Queued {$total} generation jobs.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTypes(): array
    {
        $requested = array_filter((array) $this->option('type'));

        if ($requested !== []) {
            /* Textul de ajutor anunta pluralul („events, artists or venues"),
               dar codul accepta doar singularul — o comanda scrisa exact dupa
               `--help` nu facea nimic, tacut. Acceptam ambele. */
            $normalised = array_map(
                fn (string $t) => rtrim(mb_strtolower(trim($t)), 's'),
                $requested,
            );

            return array_values(array_intersect($normalised, ['event', 'artist', 'venue']));
        }

        return array_values(array_filter([
            config('shorts.autogen.events', true) ? 'event' : null,
            config('shorts.autogen.artists', true) ? 'artist' : null,
            config('shorts.autogen.venues', true) ? 'venue' : null,
        ]));
    }

    protected function sweep(string $type, int $limit, bool $dryRun): int
    {
        $class = match ($type) {
            'event' => Event::class,
            'artist' => Artist::class,
            'venue' => Venue::class,
        };

        $ids = $this->option('id');

        $candidates = $ids && count($this->resolveTypes()) === 1
            ? array_map('intval', $ids)
            : $this->candidateIds($type, $class, $limit);

        foreach ($candidates as $id) {
            if (! $dryRun) {
                GenerateShortFromOwnerJob::dispatch((new $class)->getMorphClass(), (int) $id);
            }
        }

        return count($candidates);
    }

    /**
     * Owners with at least one usable image and no short yet.
     *
     * The "no short yet" filter is applied here as well as in the generator.
     * The generator's check is the correctness guarantee; this one exists so a
     * nightly sweep over a large catalogue does not queue thousands of jobs
     * whose only job is to discover they have nothing to do.
     *
     * @return array<int, int>
     */
    protected function candidateIds(string $type, string $class, int $limit): array
    {
        $morph = (new $class)->getMorphClass();

        $query = $class::query()
            ->whereNotIn(
                (new $class)->getKeyName(),
                Short::query()
                    ->where('owner_type', $morph)
                    ->whereNotNull('owner_id')
                    ->select('owner_id'),
            )
            ->limit($limit);

        match ($type) {
            'event' => $this->constrainEvents($query),
            'artist' => $this->constrainArtists($query),
            'venue' => $this->constrainVenues($query),
        };

        return $query->pluck((new $class)->getKeyName())->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Only events inside the horizon, and only ones that have artwork.
     *
     * A feed full of last year's posters is worse than a short feed: it makes
     * the whole surface look abandoned, and none of those tickets are for sale.
     */
    protected function constrainEvents(Builder $query): void
    {
        $horizon = (int) config('shorts.autogen.event_horizon_days', 120);

        $query
            ->whereNotNull('event_date')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->whereDate('event_date', '<=', now()->addDays($horizon)->toDateString())
            ->where(fn (Builder $q) => $q
                ->whereNotNull('poster_url')
                ->orWhereNotNull('hero_image_url'))
            // A short already attributed to the event through event_id counts as
            // coverage even when its owner is the artist.
            ->whereNotIn('id', Short::query()->whereNotNull('event_id')->select('event_id'))
            ->orderBy('event_date');
    }

    /**
     * Artists with a picture and an upcoming gig.
     *
     * Generating for the whole roster would fill the feed with acts nobody can
     * buy a ticket for — the point of an artist short is the gig behind it.
     */
    protected function constrainArtists(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNotNull('main_image_url')
            ->orWhereNotNull('portrait_url'));

        if (Schema::hasColumn('artists', 'is_active')) {
            $query->where(fn (Builder $q) => $q->whereNull('is_active')->orWhere('is_active', true));
        }

        // Guarded on the pivot TABLE, not on the relation method: the method
        // always exists, and checking it proves nothing. A schema without
        // event_artist would throw here, and this runs unattended at 03:00.
        // Without the pivot, "has an upcoming gig" is unknowable, so every
        // pictured artist is a candidate rather than none of them.
        if (Schema::hasTable('event_artist')) {
            $query->whereHas('events', fn (Builder $q) => $q
                ->whereDate('event_date', '>=', now()->toDateString()));
        }
    }

    protected function constrainVenues(Builder $query): void
    {
        $query->whereNotNull('image_url');
    }
}
