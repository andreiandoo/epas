<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Seating\EventSeat;
use App\Models\Seating\PriceTier;
use App\Services\Seating\MarketplaceEventSeatingService;
use Illuminate\Console\Command;

/**
 * Generează snapshot-ul de sală pentru un eveniment de teatru și atașează
 * price tiers pe locuri (ca API-ul public de seating să întoarcă prețuri).
 *
 *   php artisan teatru:snapshot {eventId}
 *
 * Precondiție: venue-ul evenimentului are un SeatingLayout publicat
 * (vezi: php artisan teatru:seed-seating {venueId}).
 */
class TeatruSnapshotEvent extends Command
{
    protected $signature = 'teatru:snapshot {event : ID-ul evenimentului} {--sold=8 : procent locuri marcate vândute (realism)}';
    protected $description = 'Creează snapshot-ul de seating + prețuri pentru un eveniment';

    public function handle(MarketplaceEventSeatingService $svc): int
    {
        $eventId = (int) $this->argument('event');
        $event = Event::withoutGlobalScopes()->find($eventId);
        if (!$event) {
            $this->error("Evenimentul #{$eventId} nu există.");
            return self::FAILURE;
        }

        $es = $svc->getOrCreateEventSeatingByEventId($eventId);
        if (!$es) {
            $this->error('Nu s-a putut genera snapshot-ul. Verifică: evenimentul are venue, iar venue-ul are un SeatingLayout PUBLICAT cu secțiuni.');
            return self::FAILURE;
        }

        $tenantId = $event->tenant_id;

        // Atașează price tier pe locuri, după numele secțiunii
        $tiers = PriceTier::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn ($t) => mb_strtolower($t->name));

        $updated = 0;
        foreach ($tiers as $name => $tier) {
            $n = EventSeat::where('event_seating_id', $es->id)
                ->whereRaw('LOWER(section_name) = ?', [$name])
                ->update(['price_tier_id' => $tier->id]);
            $updated += $n;
        }

        // Marcaje "vândut" pentru realism (deterministic)
        $soldPct = max(0, min(40, (int) $this->option('sold')));
        $soldCount = 0;
        if ($soldPct > 0) {
            $seats = EventSeat::where('event_seating_id', $es->id)
                ->where('status', 'available')
                ->orderBy('id')
                ->get();
            $step = max(2, (int) round(100 / $soldPct));
            foreach ($seats as $i => $seat) {
                if ($i % $step === 0) {
                    $seat->update(['status' => 'sold', 'version' => $seat->version + 1]);
                    $soldCount++;
                }
            }
        }

        $total = EventSeat::where('event_seating_id', $es->id)->count();

        $this->info("OK — event_seating_id #{$es->id}");
        $this->line("  locuri total:        {$total}");
        $this->line("  cu preț (tier):      {$updated}");
        $this->line("  marcate vândute:     {$soldCount}");
        $this->newLine();
        $this->line("Verifică API:");
        $this->line("  curl https://core.tixello.com/api/public/events/{$eventId}/seating");
        $this->line("  curl https://core.tixello.com/api/public/events/{$eventId}/seats");

        return self::SUCCESS;
    }
}
