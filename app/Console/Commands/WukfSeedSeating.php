<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Seating\EventSeat;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\PriceTier;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use App\Models\Seating\SeatingSection;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\Venue;
use App\Services\Seating\MarketplaceEventSeatingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transformă o competiție a tenantului demo „Federația Română de Karate WUKF”
 * într-un eveniment CU LOCURI NUMEROTATE: sală de sport cu patru tribune în jurul
 * suprafeței de concurs, două niveluri de preț, snapshot per eveniment.
 *
 *   php artisan wukf:seed-seating
 *   php artisan wukf:seed-seating --event=cupa-romaniei-karate-echipe-wukf --sold=14
 *
 * Precondiție: `php artisan wukf:seed` (tenant + locații + evenimente).
 *
 * Idempotent: sala se caută după nume pe locația evenimentului, price tiers după
 * tier_code, tipurile de bilet după nume, snapshot-ul după event_id; locurile
 * „vândute” demo sunt ținute minte în event_seating_layouts.notes. Aditiv: nu
 * șterge nimic — biletele de acces general sunt doar trecute pe „hidden”.
 *
 * Convenții verificate în cod (nu presupuse):
 *   - seating_layouts.status trebuie să fie 'published' (MarketplaceEventSeatingService);
 *   - snapshot-ul (event_seating_layouts) are status 'active' + published_at, iar
 *     API-ul public filtrează pe published_at NOT NULL;
 *   - coordonatele locului (x, y) sunt RELATIVE la colțul stânga-sus al secțiunii
 *     (resources/views/seating/embed.blade.php: absX = section.x + seat.x).
 */
class WukfSeedSeating extends Command
{
    protected $signature = 'wukf:seed-seating
        {--event=cupa-romaniei-karate-echipe-wukf : slug of the event to make seated}
        {--sold=14 : how many seats to mark as already sold}';

    protected $description = 'Sală pe locuri (4 tribune în jurul tatami-ului) + snapshot pentru o competiție WUKF';

    private const TENANT_SLUG = 'wukf';

    /** Numele stabil după care sala e regăsită pe locația evenimentului. */
    private const LAYOUT_NAME = 'WUKF — Sală de sport cu tribune';

    private const CANVAS_W = 1200;
    private const CANVAS_H = 800;

    /** Distanța între centrele a două locuri vecine / a două rânduri vecine (px pe canvas). */
    private const SEAT_GAP = 26;
    private const ROW_GAP = 28;
    /** Marginea dintre conturul secțiunii și primul rând. */
    private const PAD = 20;

    /** Rândurile mai scumpe de pe laturile lungi (cele mai apropiate de tatami). */
    private const PREMIUM_ROWS = ['A', 'B', 'C'];

    /** tier_code e UNIC GLOBAL în price_tiers → prefix de tenant. */
    private const TIERS = [
        'central' => [
            'name'  => 'Tribună centrală',
            'code'  => 'WUKF_TRIB_CENTRALA',
            'price' => 50,
            'color' => '#C8102E',
            'desc'  => 'Loc numerotat pe laturile lungi, rândurile A–C, lângă suprafața de concurs.',
        ],
        'standard' => [
            'name'  => 'Tribună',
            'code'  => 'WUKF_TRIBUNA',
            'price' => 35,
            'color' => '#1D4ED8',
            'desc'  => 'Loc numerotat în tribună sau peluză.',
        ],
    ];

    private array $colCache = [];

    /**
     * Cele patru tribune. Poziții absolute pe canvas pentru secțiune (x, y, w, h);
     * `mat` = pe ce latură a secțiunii se află tatami-ul (rândul A e lipit de ea,
     * următoarele se îndepărtează). Laturile lungi au rânduri orizontale, peluzele
     * au rânduri verticale (coloane) — fără rotație de secțiune, pentru că
     * renderer-ele existente nu tratează rotația la fel.
     *
     * Dreptunghiul liber din mijloc (tatami): x 300..900, y 240..560.
     */
    private function stands(): array
    {
        return [
            ['name' => 'Tribuna Nord', 'code' => 'TRIB_NORD', 'x' => 300, 'y' => 50, 'w' => 600, 'h' => 180,
                'mat' => 'bottom', 'rows' => 6, 'seats' => 22, 'premium' => true, 'color' => '#C8102E'],
            ['name' => 'Tribuna Sud', 'code' => 'TRIB_SUD', 'x' => 300, 'y' => 570, 'w' => 600, 'h' => 180,
                'mat' => 'top', 'rows' => 6, 'seats' => 22, 'premium' => true, 'color' => '#C8102E'],
            ['name' => 'Peluza Vest', 'code' => 'PEL_VEST', 'x' => 138, 'y' => 237, 'w' => 152, 'h' => 326,
                'mat' => 'right', 'rows' => 5, 'seats' => 12, 'premium' => false, 'color' => '#1D4ED8'],
            ['name' => 'Peluza Est', 'code' => 'PEL_EST', 'x' => 910, 'y' => 237, 'w' => 152, 'h' => 326,
                'mat' => 'left', 'rows' => 5, 'seats' => 12, 'premium' => false, 'color' => '#1D4ED8'],
        ];
    }

    public function handle(MarketplaceEventSeatingService $svc): int
    {
        $slug = trim((string) $this->option('event'));
        $soldWanted = max(0, (int) $this->option('sold'));

        $this->info('Seed seating WUKF — ' . $slug);

        foreach (['seating_layouts', 'seating_sections', 'seating_rows', 'seating_seats', 'price_tiers', 'event_seating_layouts', 'event_seats'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Tabela {$table} lipsește — modulul de seating nu e migrat pe acest mediu. Oprit, nu am modificat nimic.");
                return self::FAILURE;
            }
        }
        if (! $this->has('events', 'seating_layout_id')) {
            $this->error('Coloana events.seating_layout_id lipsește. Oprit, nu am modificat nimic.');
            return self::FAILURE;
        }
        if (! config('seating.enabled', true)) {
            $this->warn('  config seating.enabled = false pe acest mediu — datele se creează, dar modulul e oprit global.');
        }

        try {
            // ── 1. Tenant + eveniment + locație ──────────────────────────────
            $tenant = Tenant::where('slug', self::TENANT_SLUG)->first();
            if (! $tenant) {
                $this->error('Tenantul „' . self::TENANT_SLUG . '” nu există. Rulează întâi: php artisan wukf:seed');
                return self::FAILURE;
            }

            $event = Event::where('tenant_id', $tenant->id)->where('slug', $slug)->first();
            if (! $event) {
                $this->error("Evenimentul „{$slug}” nu există la tenantul #{$tenant->id}. Rulează întâi: php artisan wukf:seed");
                return self::FAILURE;
            }

            $venue = $event->venue_id ? Venue::find($event->venue_id) : null;
            if (! $venue) {
                $this->error("Evenimentul #{$event->id} nu are locație. Rulează întâi: php artisan wukf:seed");
                return self::FAILURE;
            }
            $this->line("  tenant #{$tenant->id}, eveniment #{$event->id}, locație #{$venue->id}");

            // ── 2. Price tiers (ca la demo-ul de teatru: per tenant, după tier_code) ──
            $tiers = [];
            foreach (self::TIERS as $key => $cfg) {
                $tier = $this->seedTier($tenant, $cfg);
                if (! $tier) {
                    return self::FAILURE;
                }
                $tiers[$key] = $tier;
            }

            // ── 3. Sala ──────────────────────────────────────────────────────
            $expectedSeats = 0;
            foreach ($this->stands() as $s) {
                $expectedSeats += $s['rows'] * $s['seats'];
            }

            $layout = $this->seedLayout($tenant, $venue, $tiers, $expectedSeats);
            if (! $layout) {
                return self::FAILURE;
            }

            // ── 4. Atașare pe eveniment + snapshot ───────────────────────────
            // Serviciul șterge și reface un snapshot al cărui layout diferă de cel ales
            // pe eveniment. Nu-l lăsăm să facă asta peste locuri deja vândute / ținute.
            $current = EventSeatingLayout::where('event_id', $event->id)->whereNotNull('published_at')->first();
            if ($current && (int) $current->layout_id !== (int) $layout->id) {
                $busy = EventSeat::where('event_seating_id', $current->id)->whereIn('status', ['sold', 'held'])->count();
                if ($busy > 0) {
                    $this->error("Evenimentul are deja un snapshot (#{$current->id}) pe altă sală (#{$current->layout_id}) cu {$busy} locuri vândute/ținute. Oprit — nu îl înlocuiesc.");
                    return self::FAILURE;
                }
                $this->warn("  snapshot vechi #{$current->id} (sala #{$current->layout_id}, fără locuri vândute) — va fi regenerat pe sala nouă.");
            }

            if ((int) $event->seating_layout_id !== (int) $layout->id) {
                $event->update(['seating_layout_id' => $layout->id]);
                $this->line("  sala #{$layout->id} atașată pe eveniment (seating_layout_id)");
            } else {
                $this->line("  sala #{$layout->id} era deja atașată pe eveniment");
            }

            $es = $svc->getOrCreateEventSeatingByEventId($event->id);
            if (! $es || (int) $es->layout_id !== (int) $layout->id) {
                $this->error('Nu s-a putut genera snapshot-ul de locuri al evenimentului (vezi laravel.log: MarketplaceEventSeatingService).');
                return self::FAILURE;
            }

            // Completare aditivă dacă snapshot-ul a rămas fără unele locuri.
            $seatCount = EventSeat::where('event_seating_id', $es->id)->count();
            if ($seatCount < $expectedSeats) {
                $sync = $svc->syncMissingSeatsFromLayout($es->id);
                $this->warn('  snapshot incomplet — locuri adăugate: ' . ($sync['added'] ?? 0));
                $seatCount = EventSeat::where('event_seating_id', $es->id)->count();
            }
        } catch (\Throwable $e) {
            $this->error('Eroare: ' . $e->getMessage());
            $this->line('  ' . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        // ── 5. Prețuri pe locuri (event_seats.price_tier_id) ─────────────────
        $premiumSections = array_values(array_map(
            fn ($s) => $s['name'],
            array_filter($this->stands(), fn ($s) => $s['premium'])
        ));
        $this->guarded('prețuri pe locuri', function () use ($es, $tiers, $premiumSections) {
            $changed = $this->assignTiers($es->id, $tiers, $premiumSections);
            $this->line("  prețuri pe locuri: {$changed} actualizate");
        });

        // ── 6. Locuri „vândute” demo ─────────────────────────────────────────
        $this->guarded('locuri vândute demo', fn () => $this->markSold($es, $soldWanted, $seatCount));

        // ── 7. Tipuri de bilet ───────────────────────────────────────────────
        $seatedTypes = [];
        try {
            $perTier = EventSeat::where('event_seating_id', $es->id)
                ->selectRaw('price_tier_id, COUNT(*) AS n')
                ->groupBy('price_tier_id')
                ->pluck('n', 'price_tier_id');

            $central = (int) ($perTier[$tiers['central']->id] ?? 0);
            $standard = (int) ($perTier[$tiers['standard']->id] ?? 0);
            if ($central + $standard !== $seatCount) {
                // Prețurile nu au ajuns pe toate locurile (pasul 5 a eșuat) — capacitate din planul sălii.
                $central = 0;
                foreach ($this->stands() as $s) {
                    $central += $s['premium'] ? count(self::PREMIUM_ROWS) * $s['seats'] : 0;
                }
                $standard = max(0, $seatCount - $central);
                $this->warn('  nu toate locurile au preț — capacitatea tipurilor de bilet e luată din planul sălii.');
            }

            $seatedTypes = $this->seedTicketTypes($event, ['central' => $central, 'standard' => $standard]);
        } catch (\Throwable $e) {
            $this->error('Tipuri de bilet: ' . $e->getMessage());
            $this->line('  ' . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        // ── Rezumat ──────────────────────────────────────────────────────────
        $counts = [];
        $this->guarded('numărătoare', function () use ($es, &$counts) {
            $counts = $es->fresh()->getSeatStatusCounts();
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Layout ID:         ' . $layout->id . '  (' . self::LAYOUT_NAME . ', status ' . $layout->status . ')');
        $this->line('  Event seating ID:  ' . $es->id);
        $this->line('  Locuri:            ' . $seatCount
            . ($counts ? '  (disponibile ' . $counts['available'] . ', vândute ' . $counts['sold'] . ', ținute ' . $counts['held'] . ')' : ''));
        $this->line('  Tipuri de bilet:');
        foreach ($seatedTypes as $tt) {
            $this->line('    #' . $tt->id . '  ' . $this->nameOf($tt) . ' — ' . number_format((float) $tt->price_max, 2, ',', '.')
                . ' ' . ($tt->currency ?: 'RON') . ', capacitate ' . $tt->capacity);
        }
        $this->line('  Verifică:');
        $this->line('    https://core.tixello.com/api/public/events/' . $event->id . '/seating');
        $this->line('    https://core.tixello.com/api/public/events/' . $event->id . '/seats');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */
    /* Price tiers                                                         */
    /* ------------------------------------------------------------------ */

    private function seedTier(Tenant $tenant, array $cfg): ?PriceTier
    {
        $tier = PriceTier::withoutGlobalScopes()->where('tier_code', $cfg['code'])->first();

        if ($tier && (int) $tier->tenant_id !== (int) $tenant->id) {
            $this->error("Price tier-ul {$cfg['code']} aparține altui tenant (#{$tier->tenant_id}). Oprit.");
            return null;
        }

        $data = $this->only('price_tiers', [
            'tenant_id'   => $tenant->id,
            'name'        => $cfg['name'],
            'tier_code'   => $cfg['code'],
            'currency'    => 'RON',
            'price'       => $cfg['price'],
            'color'       => $cfg['color'],
            'description' => $cfg['desc'],
            'is_active'   => true,
            'sort_order'  => $cfg['price'],
        ]);

        if ($tier) {
            $tier->update($data);
        } else {
            $tier = PriceTier::create($data);
        }
        $this->line("  price tier {$cfg['name']}: {$cfg['price']} RON (#{$tier->id})");

        return $tier;
    }

    /* ------------------------------------------------------------------ */
    /* Sala                                                                */
    /* ------------------------------------------------------------------ */

    /** @param array<string, PriceTier> $tiers */
    private function seedLayout(Tenant $tenant, Venue $venue, array $tiers, int $expectedSeats): ?SeatingLayout
    {
        $layout = SeatingLayout::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->where('name', self::LAYOUT_NAME)
            ->orderBy('id')
            ->first();

        if ($layout) {
            $sections = SeatingSection::where('layout_id', $layout->id)->count();
            $seats = (int) DB::table('seating_seats')
                ->join('seating_rows', 'seating_seats.row_id', '=', 'seating_rows.id')
                ->join('seating_sections', 'seating_rows.section_id', '=', 'seating_sections.id')
                ->where('seating_sections.layout_id', $layout->id)
                ->count();

            if ($sections > 0 && $seats !== $expectedSeats) {
                $this->error("Sala „" . self::LAYOUT_NAME . "” (#{$layout->id}) există, dar are {$seats} locuri în loc de {$expectedSeats}. "
                    . 'A fost modificată manual — nu o ating. Redenumește-o sau repar-o din designer, apoi rulează din nou.');
                return null;
            }

            if ($sections > 0) {
                if ($layout->status !== 'published') {
                    $layout->update(['status' => 'published']);
                    $this->line("  sala #{$layout->id} era „draft” — publicată");
                }
                $this->line("  sală existentă: #{$layout->id} ({$seats} locuri)");

                return $layout;
            }
            // Cochilie goală (fără secțiuni): o populăm mai jos.
        }

        return DB::transaction(function () use ($tenant, $venue, $tiers, $layout) {
            if (! $layout) {
                $layout = SeatingLayout::create($this->only('seating_layouts', [
                    'tenant_id' => $tenant->id,
                    'venue_id'  => $venue->id,
                    'name'      => self::LAYOUT_NAME,
                    'status'    => 'published',
                    'canvas_w'  => self::CANVAS_W,
                    'canvas_h'  => self::CANVAS_H,
                    'version'   => 1,
                    'notes'     => 'Demo WUKF: patru tribune în jurul suprafeței de concurs. Generat de wukf:seed-seating.',
                ]));
            } elseif ($layout->status !== 'published') {
                $layout->update(['status' => 'published']);
            }

            $total = 0;
            $order = 0;
            foreach ($this->stands() as $s) {
                $order++;
                $horizontal = in_array($s['mat'], ['top', 'bottom'], true);

                $meta = ['price_tier_id' => $tiers['standard']->id];
                if ($s['premium']) {
                    $meta['premium_rows'] = self::PREMIUM_ROWS;
                    $meta['premium_price_tier_id'] = $tiers['central']->id;
                }

                $section = SeatingSection::create($this->only('seating_sections', [
                    'layout_id'     => $layout->id,
                    'tenant_id'     => $tenant->id,
                    'name'          => $s['name'],
                    'section_code'  => $s['code'],
                    'section_type'  => 'standard',
                    'x_position'    => $s['x'],
                    'y_position'    => $s['y'],
                    'width'         => $s['w'],
                    'height'        => $s['h'],
                    'rotation'      => 0,
                    'display_order' => $order,
                    'color_hex'     => $s['color'],
                    'seat_color'    => $s['color'],
                    'meta'          => $meta,
                ]));

                // Centrare a șirului de locuri pe lungimea secțiunii.
                $along = $horizontal ? $s['w'] : $s['h'];
                $lead = ($along - ($s['seats'] - 1) * self::SEAT_GAP) / 2;

                for ($r = 0; $r < $s['rows']; $r++) {
                    $rowLabel = chr(65 + $r); // A = lângă tatami
                    $depth = self::PAD + $r * self::ROW_GAP;
                    // Coordonata „în adâncime” a rândului, relativă la secțiune.
                    $across = match ($s['mat']) {
                        'bottom' => $s['h'] - $depth,
                        'right'  => $s['w'] - $depth,
                        default  => $depth, // top / left
                    };

                    $row = SeatingRow::create($this->only('seating_rows', [
                        'section_id'        => $section->id,
                        'label'             => $rowLabel,
                        'seat_start_number' => 1,
                        'y'                 => $horizontal ? $across : $lead,
                        'rotation'          => 0,
                        'seat_count'        => $s['seats'],
                    ]));

                    for ($n = 1; $n <= $s['seats']; $n++) {
                        $pos = $lead + ($n - 1) * self::SEAT_GAP;
                        $label = (string) $n;

                        SeatingSeat::create($this->only('seating_seats', [
                            'row_id'       => $row->id,
                            'label'        => $label,
                            'display_name' => $section->generateSeatDisplayName($rowLabel, $label),
                            'x'            => $horizontal ? $pos : $across,
                            'y'            => $horizontal ? $across : $pos,
                            'angle'        => 0,
                            'shape'        => 'circle',
                            'status'       => 'active',
                            'seat_uid'     => $section->generateSeatUid($rowLabel, $label),
                        ]));
                        $total++;
                    }
                }

                $this->line("  secțiune {$s['name']}: {$s['rows']} rânduri × {$s['seats']} locuri");
            }

            $this->line("  sală creată: #{$layout->id} ({$total} locuri, status published)");

            return $layout;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Snapshot: prețuri + locuri vândute                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Laturile lungi, rândurile A–C → „Tribună centrală”; tot restul → „Tribună”.
     * Atinge doar rândurile al căror tier diferă (re-rulare = 0 modificări).
     *
     * @param array<string, PriceTier> $tiers
     */
    private function assignTiers(int $eventSeatingId, array $tiers, array $premiumSections): int
    {
        $centralId = $tiers['central']->id;
        $standardId = $tiers['standard']->id;

        $differs = fn ($q, int $id) => $q->where(function ($w) use ($id) {
            $w->whereNull('price_tier_id')->orWhere('price_tier_id', '!=', $id);
        });

        $central = EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereIn('section_name', $premiumSections)
            ->whereIn('row_label', self::PREMIUM_ROWS);
        $changed = $differs($central, $centralId)->update(['price_tier_id' => $centralId]);

        $standard = EventSeat::where('event_seating_id', $eventSeatingId)
            ->where(function ($w) use ($premiumSections) {
                $w->whereNotIn('section_name', $premiumSections)
                    ->orWhereNotIn('row_label', self::PREMIUM_ROWS);
            });
        $changed += $differs($standard, $standardId)->update(['price_tier_id' => $standardId]);

        // Motorul de prețuri ține prețul per loc în cache 60 s (price:{es}:{uid}).
        if ($changed > 0) {
            foreach (EventSeat::where('event_seating_id', $eventSeatingId)->pluck('seat_uid') as $uid) {
                Cache::forget("price:{$eventSeatingId}:{$uid}");
            }
        }

        return $changed;
    }

    /**
     * Marchează $wanted locuri răsfirate ca „sold”, ca harta să arate vie. Locurile
     * alese sunt ținute minte în notes.demo_sold_uids, deci re-rularea nu mai vinde
     * altele (doar completează dacă --sold a crescut).
     */
    private function markSold(EventSeatingLayout $es, int $wanted, int $seatCount): void
    {
        $wanted = min($wanted, intdiv($seatCount, 3));
        $hasNotes = $this->has('event_seating_layouts', 'notes');

        $notes = is_array($es->notes) ? $es->notes : [];
        $demo = array_values(array_filter((array) ($notes['demo_sold_uids'] ?? []), 'is_string'));

        // Fără coloana notes nu putem ține evidența: ne raportăm la totalul de locuri vândute.
        $already = $hasNotes
            ? count($demo)
            : EventSeat::where('event_seating_id', $es->id)->where('status', 'sold')->count();

        $need = $wanted - $already;
        if ($need <= 0) {
            $this->line("  locuri vândute demo: {$already} (nimic de adăugat)");
            return;
        }

        $available = EventSeat::where('event_seating_id', $es->id)
            ->where('status', 'available')
            ->orderBy('id')
            ->pluck('seat_uid')
            ->all();
        $n = count($available);
        if ($n === 0) {
            $this->warn('  niciun loc disponibil de marcat ca vândut.');
            return;
        }

        // Pas constant prin toate tribunele, determinist.
        $need = min($need, $n);
        $stride = max(1, intdiv($n, $need));
        $offset = intdiv($stride, 2);
        $chosen = [];
        for ($k = 0; $k < $need; $k++) {
            $chosen[] = $available[($offset + $k * $stride) % $n];
        }
        $chosen = array_values(array_unique($chosen));

        EventSeat::where('event_seating_id', $es->id)
            ->whereIn('seat_uid', $chosen)
            ->where('status', 'available')
            ->update([
                'status'         => 'sold',
                'version'        => DB::raw('version + 1'),
                'last_change_at' => now(),
            ]);

        if ($hasNotes) {
            $notes['demo_sold_uids'] = array_values(array_unique(array_merge($demo, $chosen)));
            $es->notes = $notes;
            $es->save();
        }

        $this->line('  locuri vândute demo: +' . count($chosen) . ' (total ' . ($already + count($chosen)) . ')');
    }

    /* ------------------------------------------------------------------ */
    /* Tipuri de bilet                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Checkout-ul demo leagă fiecare loc de tipul de bilet ACTIV cu prețul cel mai
     * apropiat (DemoCheckoutController::priceToTt) → pe un eveniment cu locuri rămân
     * active doar cele două tipuri de mai jos; cele de acces general trec pe „hidden”
     * (mutatorul is_active=false), fără ștergere.
     *
     * @param  array{central: int, standard: int} $seatsPerTier
     * @return TicketType[]
     */
    private function seedTicketTypes(Event $event, array $seatsPerTier): array
    {
        $existing = TicketType::where('event_id', $event->id)->get();
        $seatedNames = array_column(self::TIERS, 'name');

        $hidden = 0;
        foreach ($existing as $tt) {
            if (in_array($this->nameOf($tt), $seatedNames, true) || $tt->status !== 'active') {
                continue;
            }
            $tt->is_active = false;
            if ($this->has('ticket_types', 'autostart_when_previous_sold_out')) {
                // Altfel un tip „hidden” ar putea fi reactivat automat.
                $tt->autostart_when_previous_sold_out = false;
            }
            $tt->save();
            $hidden++;
        }
        if ($hidden > 0) {
            $this->line("  bilete de acces general scoase din vânzare (status hidden): {$hidden}");
        }

        $out = [];
        $order = 1;
        foreach (self::TIERS as $key => $cfg) {
            $tt = $existing->first(fn (TicketType $t) => $this->nameOf($t) === $cfg['name']);
            $creating = ! $tt;
            if ($creating) {
                $tt = new TicketType();
                $tt->event_id = $event->id;
                $tt->name = $cfg['name'];
            }

            // Mutatori virtuali: price_max -> price_cents, capacity -> quota_total, is_active -> status.
            $tt->price_max = $cfg['price'];
            $tt->is_active = true;
            $tt->currency = 'RON';

            $sold = (int) ($tt->quota_sold ?? 0);
            $tt->capacity = max((int) $seatsPerTier[$key], $sold);

            foreach ($this->only('ticket_types', [
                'description'   => $cfg['desc'],
                'sort_order'    => $order,
                'min_per_order' => 1,
                'max_per_order' => (int) config('seating.max_held_seats_per_session', 10),
                'color'         => $cfg['color'],
            ]) as $col => $val) {
                $tt->{$col} = $val;
            }

            if ($creating && $this->has('ticket_types', 'quota_sold')) {
                $tt->quota_sold = 0;
            }

            $tt->save();
            $out[] = $tt;
            $order++;
        }

        return $out;
    }

    /** Numele tipului de bilet ca text (coloana poate fi text sau json, în funcție de mediu). */
    private function nameOf(TicketType $tt): string
    {
        $n = $tt->name;
        if (is_string($n) && str_starts_with(ltrim($n), '{')) {
            $decoded = json_decode($n, true);
            if (is_array($decoded)) {
                $n = $decoded;
            }
        }
        if (is_array($n)) {
            $n = $n['ro'] ?? $n['en'] ?? (string) reset($n);
        }

        return trim((string) $n);
    }

    /* ------------------------------------------------------------------ */
    /* Utilitare (aceleași ca în WukfSeed)                                 */
    /* ------------------------------------------------------------------ */

    private function guarded(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->warn("  [{$label}] sărit: " . $e->getMessage());
        }
    }

    /** Coloanele reale ale tabelei (cache per rulare). Gol dacă nu pot fi citite. */
    private function cols(string $table): array
    {
        if (! array_key_exists($table, $this->colCache)) {
            try {
                $this->colCache[$table] = Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $this->colCache[$table] = [];
            }
        }

        return $this->colCache[$table];
    }

    private function has(string $table, string $column): bool
    {
        return in_array($column, $this->cols($table), true);
    }

    /** Păstrează doar cheile care sunt coloane reale (protecție la schema drift). */
    private function only(string $table, array $data): array
    {
        $cols = $this->cols($table);
        if (empty($cols)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($cols));
    }
}
