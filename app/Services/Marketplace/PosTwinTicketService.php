<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\Log;

/**
 * Biletul „POS <nume>”: geamănul unui tip de bilet, vândut doar din aplicație
 * (is_entry_ticket), cu comisionul mereu inclus în preț. Legătura cu originalul
 * stă în meta.pos_source_ticket_type_id. Folosit de butonul din core (Filament)
 * și de contul organizatorului.
 */
class PosTwinTicketService
{
    public const PREFIX = 'POS ';

    /** „POS ” + nume, fără dublarea prefixului. */
    public static function posName(?string $name): string
    {
        $name = trim((string) $name);
        if (mb_strtolower(mb_substr($name, 0, 4)) === 'pos ') {
            $name = trim(mb_substr($name, 4));
        }

        return self::PREFIX . $name;
    }

    /** Numele fără prefix (ce editează organizatorul). */
    public static function stripPrefix(?string $name): string
    {
        $name = trim((string) $name);

        return mb_strtolower(mb_substr($name, 0, 4)) === 'pos ' ? trim(mb_substr($name, 4)) : $name;
    }

    public static function plainName(TicketType $tt): string
    {
        $name = $tt->name;

        return is_array($name) ? (string) ($name['ro'] ?? $name['en'] ?? reset($name)) : (string) $name;
    }

    /**
     * Comisionul geamănului: aceeași valoare ca originalul, dar mereu „inclus în preț”.
     * Pe „Moștenește” modul s-ar lua de la eveniment/organizator, deci procentul
     * efectiv al evenimentului se copiază explicit.
     *
     * @param  array{commission_type?: ?string, commission_rate?: mixed, commission_fixed?: mixed}  $source
     */
    public static function commission(Event $event, array $source): array
    {
        $type = $source['commission_type'] ?? null;
        if (in_array($type, ['percentage', 'fixed', 'both'], true)) {
            return [
                'commission_type' => $type,
                'commission_rate' => $source['commission_rate'] ?? null,
                'commission_fixed' => $source['commission_fixed'] ?? null,
                'commission_mode' => 'included',
            ];
        }

        return [
            'commission_type' => 'percentage',
            'commission_rate' => $event->getEffectiveCommissionRate(),
            'commission_fixed' => null,
            'commission_mode' => 'included',
        ];
    }

    /** Geamănul POS al unui tip de bilet (inclusiv cel oprit). */
    public static function findTwin(TicketType $source): ?TicketType
    {
        return TicketType::where('event_id', $source->event_id)
            ->where('meta->pos_source_ticket_type_id', (string) $source->id) // ->> intoarce text pe PostgreSQL
            ->orderBy('id')
            ->first();
    }

    public static function isTwin(TicketType $tt): bool
    {
        return !empty(($tt->meta ?? [])['pos_source_ticket_type_id']);
    }

    /**
     * Creează geamănul POS. Numele (fără prefix), prețul și stocul pot fi date;
     * altfel se iau de la original. Alocarea pe hartă se copiază pe loc.
     */
    public static function createTwin(TicketType $source, ?string $name = null, $price = null, $quantity = null, bool $quantityGiven = false): TicketType
    {
        $event = $source->event;
        $meta = ['pos_source_ticket_type_id' => (int) $source->id];
        if (($event->display_template ?? 'standard') === 'leisure_venue') {
            $meta['pos_only'] = true;
        }

        $twin = TicketType::create(array_merge([
            'event_id' => $source->event_id,
            'name' => self::posName(filled($name) ? $name : self::plainName($source)),
            'description' => $source->description,
            'currency' => $source->currency ?? 'RON',
            'price_max' => $price !== null ? (float) $price : ((int) $source->price_cents) / 100,
            'capacity' => $quantityGiven ? $quantity : $source->quota_total,
            'min_per_order' => 1,
            'max_per_order' => $source->max_per_order ?: 10,
            'quota_sold' => 0,
            'is_active' => true, // status nu e fillable; mutatorul scrie status = active
            'is_entry_ticket' => true,
            'is_declarable' => $source->is_declarable ?? true,
            'is_independent_stock' => (bool) $source->is_independent_stock,
            'ticket_group' => $source->ticket_group,
            'valid_date' => $source->valid_date,
            'color' => $source->color,
            'sort_order' => $source->sort_order,
            'meta' => $meta,
        ], self::commission($event, [
            'commission_type' => $source->commission_type,
            'commission_rate' => $source->commission_rate,
            'commission_fixed' => $source->commission_fixed,
        ])));

        self::copySeatingFromSource($twin);

        return $twin;
    }

    /**
     * Copiază o singură dată alocarea pe hartă a originalului (rânduri, secțiuni,
     * acces general). Dacă geamănul are deja alocare proprie, nu o atinge.
     * Fără alocare la original → reîncearcă la următorul apel.
     */
    public static function copySeatingFromSource(TicketType $twin): void
    {
        try {
            $meta = is_array($twin->meta) ? $twin->meta : [];
            if (!empty($meta['pos_seating_copied']) || empty($meta['pos_source_ticket_type_id'])) {
                return;
            }
            $source = TicketType::where('event_id', $twin->event_id)->find((int) $meta['pos_source_ticket_type_id']);
            if (!$source) {
                return;
            }

            $rowIds = $source->seatingRows()->pluck('seating_rows.id')->all();
            $sectionIds = $source->seatingSections()->pluck('seating_sections.id')->all();
            $gaIds = $source->generalAccessSections()->pluck('seating_sections.id')->all();
            if (!$rowIds && !$sectionIds && !$gaIds) {
                return;
            }

            $hasOwn = $twin->seatingRows()->exists()
                || $twin->seatingSections()->exists()
                || $twin->generalAccessSections()->exists();
            if (!$hasOwn) {
                $twin->seatingRows()->syncWithoutDetaching($rowIds);
                $twin->seatingSections()->syncWithoutDetaching($sectionIds);
                $twin->generalAccessSections()->syncWithoutDetaching($gaIds);
            }

            $meta['pos_seating_copied'] = true;
            $twin->meta = $meta;
            $twin->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('POS twin seating copy failed', ['ticket_type_id' => $twin->id, 'error' => $e->getMessage()]);
        }
    }

    /** Rulează copierea pentru toți gemenii evenimentului (după salvarea din core). */
    public static function copyPendingSeating(Event $event): void
    {
        $twins = TicketType::where('event_id', $event->id)
            ->whereNotNull('meta->pos_source_ticket_type_id')
            ->get();
        foreach ($twins as $twin) {
            self::copySeatingFromSource($twin);
        }
    }

    /** Bilete valide/folosite vândute pe tipul dat. */
    public static function soldCount(TicketType $tt): int
    {
        return \App\Models\Ticket::where('ticket_type_id', $tt->id)
            ->whereIn('status', ['valid', 'used'])
            ->count();
    }
}
