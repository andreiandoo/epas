<?php

namespace App\Console\Commands;

use App\Models\Seating\PriceTier;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use App\Models\Seating\SeatingSection;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo seating pentru un teatru (tenant): creează 2 price tiers (Staluri/Balcon)
 * și o sală desenată (secțiuni + rânduri + locuri) pentru un venue dat.
 *
 *   php artisan teatru:seed-seating {venueId}
 *
 * Venue-ul și evenimentele se creează în panoul /tenant. Snapshot-ul per eveniment:
 *   php artisan teatru:snapshot {eventId}
 */
class TeatruSeedSeating extends Command
{
    protected $signature = 'teatru:seed-seating {venue : ID-ul venue-ului (Sala Mare)} {--force : recreează sala dacă există}';
    protected $description = 'Creează price tiers + sală (staluri/balcon) pentru un venue de teatru';

    // Structura sălii: secțiune => [tier price RON, color, [rând => nr locuri]]
    private array $plan = [
        'Staluri' => [
            'price' => 80,
            'color' => '#722F37',
            'code'  => 'STALURI',
            'y'     => 120,
            'rows'  => ['A' => 16, 'B' => 18, 'C' => 18, 'D' => 20, 'E' => 20, 'F' => 20],
        ],
        'Balcon' => [
            'price' => 120,
            'color' => '#D4AF37',
            'code'  => 'BALCON',
            'y'     => 520,
            'rows'  => ['G' => 14, 'H' => 14],
        ],
    ];

    public function handle(): int
    {
        $venueId = (int) $this->argument('venue');
        $venue = Venue::withoutGlobalScopes()->find($venueId);
        if (!$venue) {
            $this->error("Venue #{$venueId} nu există.");
            return self::FAILURE;
        }
        $tenantId = $venue->tenant_id;
        $this->info("Venue #{$venueId} (tenant {$tenantId}) — " . ($venue->getRawOriginal('name') ?? $venue->slug ?? ''));

        // 1) Price tiers (idempotent după tier_code)
        $tiers = [];
        foreach ($this->plan as $sectionName => $cfg) {
            $tier = PriceTier::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('tier_code', $cfg['code'])
                ->first();
            if (!$tier) {
                $tier = PriceTier::create([
                    'tenant_id'  => $tenantId,
                    'name'       => $sectionName,
                    'tier_code'  => $cfg['code'],
                    'currency'   => 'RON',
                    'price'      => $cfg['price'],
                    'color'      => $cfg['color'],
                    'is_active'  => true,
                    'sort_order' => $cfg['price'],
                ]);
            }
            $tiers[$sectionName] = $tier;
            $this->line("  price tier {$sectionName}: {$cfg['price']} RON (#{$tier->id})");
        }

        // 2) Layout (idempotent după venue + name)
        $layoutName = 'Sala Mare — Plan';
        $existing = SeatingLayout::withoutGlobalScopes()
            ->where('venue_id', $venueId)
            ->where('name', $layoutName)
            ->first();

        if ($existing && !$this->option('force')) {
            $this->warn("Layout '{$layoutName}' există deja (#{$existing->id}). Folosește --force pentru recreare.");
            $this->line("Assignează layout #{$existing->id} pe eveniment (seating_layout_id) sau lasă fallback-ul pe venue, apoi:  php artisan teatru:snapshot {eventId}");
            return self::SUCCESS;
        }
        if ($existing && $this->option('force')) {
            $existing->delete(); // cascade sections/rows/seats
            $this->warn("Layout vechi #{$existing->id} șters (--force).");
        }

        DB::transaction(function () use ($venue, $tenantId, $tiers, $layoutName) {
            $layout = SeatingLayout::create([
                'tenant_id' => $tenantId,
                'venue_id'  => $venue->id,
                'name'      => $layoutName,
                'status'    => 'published',
                'canvas_w'  => 1200,
                'canvas_h'  => 800,
                'version'   => 1,
            ]);

            $order = 0;
            foreach ($this->plan as $sectionName => $cfg) {
                $order++;
                $section = SeatingSection::create([
                    'layout_id'     => $layout->id,
                    'tenant_id'     => $tenantId,
                    'name'          => $sectionName,
                    'section_code'  => $cfg['code'],
                    'x_position'    => 150,
                    'y_position'    => $cfg['y'],
                    'width'         => 900,
                    'height'        => 320,
                    'display_order' => $order,
                    'color_hex'     => $cfg['color'],
                    'seat_color'    => $cfg['color'],
                    'meta'          => ['price_tier_id' => $tiers[$sectionName]->id],
                ]);

                $rowIndex = 0;
                foreach ($cfg['rows'] as $rowLabel => $seatCount) {
                    $rowY = $cfg['y'] + 40 + ($rowIndex * 40);
                    $row = SeatingRow::create([
                        'section_id'        => $section->id,
                        'label'             => $rowLabel,
                        'seat_start_number' => 1,
                        'y'                 => $rowY,
                        'rotation'          => 0,
                        'seat_count'        => $seatCount,
                    ]);

                    // centrare orizontală a rândului
                    $rowWidth = $seatCount * 28;
                    $startX = 600 - ($rowWidth / 2);
                    for ($n = 1; $n <= $seatCount; $n++) {
                        $seatLabel = (string) $n;
                        SeatingSeat::create([
                            'row_id'   => $row->id,
                            'label'    => $seatLabel,
                            'display_name' => $rowLabel . $seatLabel,
                            'x'        => $startX + (($n - 1) * 28),
                            'y'        => $rowY,
                            'angle'    => 0,
                            'shape'    => 'circle',
                            'status'   => 'active',
                            'seat_uid' => $section->generateSeatUid($rowLabel, $seatLabel),
                        ]);
                    }
                    $rowIndex++;
                }
                $this->line("  secțiune {$sectionName}: " . count($cfg['rows']) . " rânduri, " . array_sum($cfg['rows']) . " locuri");
            }

            $this->info("Layout creat: #{$layout->id} (status=published, venue #{$venue->id})");
        });

        $this->newLine();
        $this->info('Gata. Pași următori:');
        $this->line('  1. Creează evenimente în /tenant la acest venue (sau assignează layout-ul pe eveniment).');
        $this->line('  2. Pentru fiecare eveniment:  php artisan teatru:snapshot {eventId}');

        return self::SUCCESS;
    }
}
