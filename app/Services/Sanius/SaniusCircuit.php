<?php

namespace App\Services\Sanius;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Sanius\SaniusRental;
use App\Models\Sanius\SaniusSetting;
use App\Models\Sanius\SaniusSled;
use App\Models\Sanius\SaniusTab;
use App\Models\Sanius\SaniusTabTicket;
use App\Models\Sanius\SaniusTow;
use App\Models\Sanius\SaniusTowShift;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logica circuitului Sanius pe un eveniment. Scrie DOAR in tabelele sanius_*;
 * biletele si comenzile existente sunt doar citite.
 *
 * Tot ce se creeaza poarta is_test dupa modul curent (test / live), iar fiecare
 * operatie lucreaza numai cu randuri din acelasi mod: testele de pe productie nu
 * ating nimic real si invers.
 */
class SaniusCircuit
{
    private const VALID_ORDER_STATUSES = ['paid', 'confirmed', 'completed'];

    private const ACCESS_CATEGORIES = ['access', 'package'];

    /** @var array<int, float> */
    private array $priceCache = [];

    public function __construct(
        public readonly SaniusSetting $settings,
        public readonly Event $event,
        public readonly ?MarketplaceOrganizer $organizer = null,
    ) {}

    public function isTest(): bool
    {
        return $this->settings->isTestMode();
    }

    // ------------------------------------------------------------------
    // Preturi (din produsele organizatorului)
    // ------------------------------------------------------------------

    /** Acelasi pret ca in posSale: pos_price daca e setat, altfel pretul produsului. */
    public function unitPrice(?int $ticketTypeId): float
    {
        if (! $ticketTypeId) {
            return 0.0;
        }
        if (array_key_exists($ticketTypeId, $this->priceCache)) {
            return $this->priceCache[$ticketTypeId];
        }
        $tt = TicketType::where('id', $ticketTypeId)->where('event_id', $this->event->id)->first();
        $price = 0.0;
        if ($tt) {
            $posPrice = is_array($tt->meta) ? ($tt->meta['pos_price'] ?? null) : null;
            $price = ($posPrice !== null && $posPrice !== '')
                ? (float) $posPrice
                : (float) ($tt->price_max ?? $tt->price ?? 0);
        }

        return $this->priceCache[$ticketTypeId] = $price;
    }

    public function price(string $key): float
    {
        return $this->unitPrice($this->settings->productId($key));
    }

    /** Produsele obligatorii care lipsesc din setari (fara ele nu se poate taxa corect). */
    public function missingProducts(): array
    {
        $missing = [];
        foreach (SaniusSetting::PRODUCT_KEYS as $key) {
            $id = $this->settings->productId($key);
            if (! $id || ! TicketType::where('id', $id)->where('event_id', $this->event->id)->exists()) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    // ------------------------------------------------------------------
    // Sincronizarea soferilor
    // ------------------------------------------------------------------

    public function activeShifts(): Collection
    {
        return SaniusTowShift::query()
            ->where('event_id', $this->event->id)
            ->where('is_test', $this->isTest())
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get();
    }

    /** Turele care pot avea tractari netrimise (returnarile asteapta dupa ele). */
    public function blockingShifts(?Carbon $now = null): Collection
    {
        $now = $now ?? Carbon::now();
        $timeout = (int) ($this->settings->ping_timeout_seconds ?: 30);

        return $this->activeShifts()->filter(fn (SaniusTowShift $s) => $s->isBlocking($now, $timeout))->values();
    }

    public function isTowSyncBlocked(?Carbon $now = null): bool
    {
        return $this->blockingShifts($now)->isNotEmpty();
    }

    /**
     * Primeste un lot de scanari de la telefonul soferului. Idempotent pe
     * client_uuid: un lot retrimis nu dubleaza nimic.
     *
     * @param  array<int, array{uuid: string, sled_code: string, scanned_at: int|string}>  $scans
     * @return array<int, string> uuid-urile confirmate (inclusiv cele primite deja)
     */
    public function ingestTows(SaniusTowShift $shift, array $scans, ?int $deviceNowMs, int $pendingAfter): array
    {
        $now = Carbon::now();
        $offsetMs = $deviceNowMs ? ((int) round($now->valueOf()) - $deviceNowMs) : (int) $shift->clock_offset_ms;
        $acked = [];
        $seenInBatch = [];

        DB::transaction(function () use ($shift, $scans, $now, $offsetMs, &$acked, &$seenInBatch) {
            foreach ($scans as $scan) {
                $uuid = substr((string) ($scan['uuid'] ?? ''), 0, 64);
                $rawCode = (string) ($scan['sled_code'] ?? '');
                if ($uuid === '' || $rawCode === '') {
                    continue;
                }
                if (SaniusTow::where('client_uuid', $uuid)->exists()) {
                    $acked[] = $uuid;

                    continue;
                }

                $code = mb_substr(SaniusSled::normalizeCode($rawCode), 0, 32);
                $deviceAt = $this->deviceTime($scan['scanned_at'] ?? null) ?? $now->copy();
                $scannedAt = $deviceAt->copy()->addMilliseconds($offsetMs);
                if ($scannedAt->gt($now)) {
                    $scannedAt = $now->copy();
                }

                $sled = SaniusSled::where('code', $code)->where('event_id', $this->event->id)->first();

                $tow = new SaniusTow([
                    'event_id' => $this->event->id,
                    'shift_id' => $shift->id,
                    'sled_id' => $sled?->id,
                    'sled_code' => $code,
                    'client_uuid' => $uuid,
                    'scanned_at_device' => $deviceAt,
                    'scanned_at' => $scannedAt,
                    'received_at' => $now,
                    'status' => 'counted',
                    'is_test' => $shift->is_test,
                ]);

                if (! $sled || $sled->is_test !== $shift->is_test) {
                    $tow->status = 'anomaly';
                    $tow->anomaly_reason = $sled ? 'other_mode' : 'unknown_sled';
                } elseif (isset($seenInBatch[$sled->id])) {
                    // Aplicatia nu lasa rescanarea pana la confirmare; doua scanari
                    // ale aceleiasi sanii in acelasi lot nu se taxeaza de doua ori.
                    $tow->status = 'anomaly';
                    $tow->anomaly_reason = 'duplicate';
                } else {
                    $seenInBatch[$sled->id] = true;
                    $rental = $this->rentalAt($sled, $scannedAt);
                    if (! $rental) {
                        $tow->status = 'anomaly';
                        $tow->anomaly_reason = 'no_rental';
                    } else {
                        $tow->rental_id = $rental->id;
                        if (in_array($rental->status, ['closed', 'lost'], true)) {
                            // Tractare ajunsa dupa inchidere (doar dupa o deblocare fortata).
                            $tow->status = 'anomaly';
                            $tow->anomaly_reason = 'after_close';
                        }
                    }
                }

                $tow->save();
                $acked[] = $uuid;

                if ($tow->status === 'anomaly' && $tow->anomaly_reason === 'after_close' && $tow->rental_id) {
                    $this->recountClosedRental($tow->rental_id);
                }
            }
        });

        $shift->forceFill([
            'last_ping_at' => $now,
            'pending_count' => max(0, $pendingAfter),
            'clock_offset_ms' => $offsetMs,
        ])->save();

        return $acked;
    }

    private function deviceTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return is_numeric($value)
                ? Carbon::createFromTimestampMs((int) $value)
                : Carbon::parse((string) $value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Inchirierea saniei care era in curs la momentul dat. */
    private function rentalAt(SaniusSled $sled, Carbon $at): ?SaniusRental
    {
        return SaniusRental::query()
            ->where('sled_id', $sled->id)
            ->where('is_test', $sled->is_test)
            ->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('returned_at')->orWhere('returned_at', '>', $at))
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Inchirierea era deja inchisa (dupa o deblocare fortata) iar o tractare a
     * venit mai tarziu. Daca contul nu e platit, refacem suma; daca e platit,
     * tractarea ramane anomalie in raport.
     */
    private function recountClosedRental(int $rentalId): void
    {
        $rental = SaniusRental::with('tab')->find($rentalId);
        if (! $rental || $rental->status !== 'closed' || ! $rental->tab?->isOpen()) {
            return;
        }
        SaniusTow::where('rental_id', $rental->id)->where('anomaly_reason', 'after_close')
            ->update(['status' => 'counted', 'anomaly_reason' => null]);
        $this->computeAndClose($rental);
    }

    // ------------------------------------------------------------------
    // Conturi si bilete
    // ------------------------------------------------------------------

    /** Contul deschis pe tichet; il creeaza daca nu exista. */
    public function openTab(array $parsed, ?int $memberId): SaniusTab
    {
        return DB::transaction(function () use ($parsed, $memberId) {
            SaniusSetting::whereKey($this->settings->id)->lockForUpdate()->first();
            $existing = SaniusTab::query()
                ->where('event_id', $this->event->id)
                ->where('parking_code', $parsed['code'])
                ->where('is_test', $this->isTest())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            return SaniusTab::create([
                'event_id' => $this->event->id,
                'parking_code' => $parsed['code'],
                'parking_entry_at' => $parsed['entry_at'],
                'parking_number' => $parsed['number_suffix'],
                'status' => 'open',
                'is_test' => $this->isTest(),
                'opened_by_member_id' => $memberId,
            ]);
        });
    }

    public function findOpenTabByParking(string $code): ?SaniusTab
    {
        return SaniusTab::query()
            ->where('event_id', $this->event->id)
            ->where('parking_code', $code)
            ->where('is_test', $this->isTest())
            ->where('status', 'open')
            ->first();
    }

    /** Un bilet valid al evenimentului, dupa cod sau barcode (doar citire). */
    public function findTicket(string $rawCode): ?Ticket
    {
        $code = trim($rawCode);
        foreach (['#/v/([A-Za-z0-9_-]+)#', '#/t/([A-Za-z0-9_-]+)#', '#/verify/([A-Za-z0-9_-]+)#'] as $re) {
            if (preg_match($re, $code, $m)) {
                $code = $m[1];
                break;
            }
        }
        if ($code === '') {
            return null;
        }
        $variants = array_values(array_unique([$code, strtoupper($code), strtolower($code)]));
        $eventId = $this->event->id;

        return Ticket::with(['ticketType', 'order'])
            ->where(function ($q) use ($variants) {
                $q->whereIn('barcode', $variants)->orWhereIn('code', $variants);
            })
            ->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                    ->orWhereIn('ticket_type_id', TicketType::select('id')->where('event_id', $eventId));
            })
            ->first();
    }

    /** Ziua pentru care e biletul (Y-m-d) sau null daca nu are zi fixa. */
    public function ticketVisitDate(Ticket $ticket): ?string
    {
        $meta = is_array($ticket->meta) ? $ticket->meta : [];
        $visit = isset($meta['visit_date']) && $meta['visit_date'] !== '' ? substr((string) $meta['visit_date'], 0, 10) : null;

        return $visit ?: optional($ticket->ticketType?->valid_date)->toDateString();
    }

    /** Ziua contului (ora RO a intrarii in parcare). */
    public function tabDay(SaniusTab $tab): string
    {
        return Carbon::parse($tab->parking_entry_at ?? $tab->created_at)->setTimezone('Europe/Bucharest')->toDateString();
    }

    public function isAccessTicket(Ticket $ticket): bool
    {
        return in_array($ticket->ticketType?->effective_service_category ?? 'access', self::ACCESS_CATEGORIES, true);
    }

    public function ticketIsUsable(Ticket $ticket): bool
    {
        if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return false;
        }
        $isInvitation = is_array($ticket->meta) && ! empty($ticket->meta['is_invitation']);
        if ($isInvitation) {
            return true;
        }

        return $ticket->order && in_array($ticket->order->status, self::VALID_ORDER_STATUSES, true);
    }

    /**
     * Leaga biletul de cont, impreuna cu restul biletelor din aceeasi comanda
     * (familie / autocar). Un bilet sta pe un singur cont deschis.
     *
     * @return array{linked: int[], already_here: int[], elsewhere: int[]}
     */
    public function linkTicketWithOrder(SaniusTab $tab, Ticket $ticket, ?int $memberId, string $point): array
    {
        $tickets = collect([$ticket]);
        if ($ticket->order_id) {
            $tickets = Ticket::with(['ticketType', 'order'])
                ->where('order_id', $ticket->order_id)
                ->where(function ($q) {
                    $q->where('event_id', $this->event->id)
                        ->orWhereIn('ticket_type_id', TicketType::select('id')->where('event_id', $this->event->id));
                })
                ->get()
                ->filter(fn (Ticket $t) => $this->ticketIsUsable($t) && $this->isAccessTicket($t))
                ->values();
            if ($tickets->isEmpty()) {
                $tickets = collect([$ticket]);
            }
        }
        // Doar biletele de acces pentru ziua contului (o comanda poate avea si alte zile).
        $day = $this->tabDay($tab);
        $tickets = $tickets->filter(function (Ticket $t) use ($day) {
            $visit = $this->ticketVisitDate($t);

            return $visit === null || $visit === $day;
        })->values();

        $result = ['linked' => [], 'already_here' => [], 'elsewhere' => []];

        DB::transaction(function () use ($tab, $tickets, $memberId, $point, $day, &$result) {
            foreach ($tickets as $t) {
                // Un bilet sta pe un singur cont: deschis, sau achitat in aceeasi zi.
                $links = SaniusTabTicket::query()
                    ->where('ticket_id', $t->id)
                    ->whereHas('tab', fn ($q) => $q->where('is_test', $tab->is_test)->where(fn ($w) => $w
                        ->where('status', 'open')
                        ->orWhere(fn ($x) => $x->where('status', 'settled')->where('parking_entry_at', '>=', Carbon::parse($day, 'Europe/Bucharest')->startOfDay()->utc()))))
                    ->get();
                if ($links->contains(fn ($l) => (int) $l->tab_id === (int) $tab->id)) {
                    $result['already_here'][] = $t->id;

                    continue;
                }
                if ($links->isNotEmpty()) {
                    $result['elsewhere'][] = $t->id;

                    continue;
                }
                SaniusTabTicket::create([
                    'tab_id' => $tab->id,
                    'ticket_id' => $t->id,
                    'order_id' => $t->order_id,
                    'linked_by_member_id' => $memberId,
                    'linked_at_point' => $point,
                ]);
                $result['linked'][] = $t->id;
            }
        });

        return $result;
    }

    public function tabHasAccessTicket(SaniusTab $tab): bool
    {
        return $tab->tickets()->with('ticket.ticketType')->get()->contains(function (SaniusTabTicket $link) {
            $cat = $link->ticket?->ticketType?->effective_service_category ?? 'access';

            return in_array($cat, self::ACCESS_CATEGORIES, true);
        });
    }

    // ------------------------------------------------------------------
    // Inchiriere si returnare
    // ------------------------------------------------------------------

    public function findSled(string $raw): ?SaniusSled
    {
        return SaniusSled::query()
            ->where('event_id', $this->event->id)
            ->where('code', SaniusSled::normalizeCode($raw))
            ->first();
    }

    /** @throws SaniusException */
    public function startRental(SaniusTab $tab, SaniusSled $sled, ?int $memberId): SaniusRental
    {
        return DB::transaction(function () use ($tab, $sled, $memberId) {
            $tab = SaniusTab::whereKey($tab->id)->lockForUpdate()->first();
            $sled = SaniusSled::whereKey($sled->id)->lockForUpdate()->first();

            if (! $tab || ! $tab->isOpen()) {
                throw new SaniusException('Contul nu mai e deschis.');
            }
            if ($sled->is_test !== $this->isTest() || $tab->is_test !== $this->isTest()) {
                throw new SaniusException($this->isTest()
                    ? 'În modul Test se folosesc doar săniile de test.'
                    : 'Sania de test nu se poate închiria în modul Activ.');
            }
            if ($sled->status !== 'available') {
                throw new SaniusException($sled->displayName().' nu e disponibilă (status: '.$sled->status.').');
            }
            if (SaniusRental::where('sled_id', $sled->id)->whereIn('status', ['active', 'pending_sync'])->exists()) {
                throw new SaniusException($sled->displayName().' are deja o închiriere deschisă.');
            }
            if (! $this->tabHasAccessTicket($tab)) {
                throw new SaniusException('Leagă întâi biletul de acces de tichetul de parcare.');
            }

            $rental = SaniusRental::create([
                'event_id' => $this->event->id,
                'tab_id' => $tab->id,
                'sled_id' => $sled->id,
                'status' => 'active',
                'started_at' => Carbon::now(),
                'started_by_member_id' => $memberId,
                'is_test' => $this->isTest(),
            ]);
            $sled->update(['status' => 'rented']);

            return $rental;
        });
    }

    public function activeRentalForSled(SaniusSled $sled): ?SaniusRental
    {
        return SaniusRental::query()
            ->where('sled_id', $sled->id)
            ->whereIn('status', ['active', 'pending_sync'])
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Sania a fost predata la ghiseu. Se inchide pe loc doar daca niciun sofer
     * nu poate avea tractari netrimise; altfel asteapta sincronizarea.
     *
     * @throws SaniusException
     */
    public function requestReturn(SaniusRental $rental, string $condition, ?string $damageLevel, ?string $photoPath, ?int $memberId): SaniusRental
    {
        DB::transaction(function () use ($rental, $condition, $damageLevel, $photoPath, $memberId) {
            $locked = SaniusRental::whereKey($rental->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'active') {
                throw new SaniusException('Închirierea nu mai e activă.');
            }
            $locked->update([
                'status' => 'pending_sync',
                'returned_at' => Carbon::now(),
                'returned_by_member_id' => $memberId,
                'condition' => $condition,
                'damage_level' => $condition === 'damaged' ? $damageLevel : null,
                'damage_photo_path' => $condition === 'damaged' ? $photoPath : null,
            ]);
        });

        $this->closePendingReturns();

        return $rental->fresh(['sled', 'tab']);
    }

    /** Inchide toate returnarile care asteapta, daca niciun sofer nu blocheaza. */
    public function closePendingReturns(): int
    {
        if ($this->isTowSyncBlocked()) {
            return 0;
        }
        $closed = 0;
        $pending = SaniusRental::query()
            ->where('event_id', $this->event->id)
            ->where('is_test', $this->isTest())
            ->where('status', 'pending_sync')
            ->pluck('id');
        foreach ($pending as $id) {
            DB::transaction(function () use ($id, &$closed) {
                $rental = SaniusRental::whereKey($id)->lockForUpdate()->first();
                if ($rental && $rental->status === 'pending_sync') {
                    $this->computeAndClose($rental);
                    $closed++;
                }
            });
        }

        return $closed;
    }

    private function computeAndClose(SaniusRental $rental): void
    {
        $towsCount = SaniusTow::where('rental_id', $rental->id)->where('status', 'counted')->count();
        $damageKey = $rental->condition === 'damaged' && $rental->damage_level ? 'damage_'.$rental->damage_level : null;

        $amounts = SaniusPricing::rental(
            $this->price('rent'),
            $this->price('tow'),
            $towsCount,
            (int) $this->settings->included_tows,
            $damageKey ? $rental->damage_level : null,
            $damageKey ? $this->price($damageKey) : 0.0,
        );

        $rental->update([
            'status' => 'closed',
            'closed_at' => $rental->closed_at ?? Carbon::now(),
            'tows_count' => $amounts['tows_count'],
            'tows_included' => $amounts['tows_included'],
            'tows_paid' => $amounts['tows_paid'],
            'amounts' => $amounts,
        ]);

        SaniusSled::whereKey($rental->sled_id)->where('status', 'rented')
            ->update(['status' => $rental->condition === 'damaged' ? 'repair' : 'available']);
    }

    /** Sanie nereturnata: managerul o trece pierduta; se taxeaza chirie + tractari + sanie pierduta. */
    public function markLost(SaniusRental $rental): SaniusRental
    {
        DB::transaction(function () use ($rental) {
            $locked = SaniusRental::whereKey($rental->id)->lockForUpdate()->first();
            if (! $locked || ! in_array($locked->status, ['active', 'pending_sync'], true)) {
                throw new SaniusException('Închirierea nu mai e deschisă.');
            }
            $towsCount = SaniusTow::where('rental_id', $locked->id)->where('status', 'counted')->count();
            $amounts = SaniusPricing::rental($this->price('rent'), $this->price('tow'), $towsCount, (int) $this->settings->included_tows);
            $amounts['lost'] = round($this->price('lost'), 2);
            $amounts['total'] = round($amounts['total'] + $amounts['lost'], 2);
            $locked->update([
                'status' => 'lost',
                'closed_at' => Carbon::now(),
                'tows_count' => $amounts['tows_count'],
                'tows_included' => $amounts['tows_included'],
                'tows_paid' => $amounts['tows_paid'],
                'amounts' => $amounts,
            ]);
            SaniusSled::whereKey($locked->sled_id)->update(['status' => 'lost']);
        });

        return $rental->fresh(['sled']);
    }

    // ------------------------------------------------------------------
    // Casierie
    // ------------------------------------------------------------------

    /**
     * Ce se incaseaza: cantitati pe produsele circuitului, la pretul de acum al
     * produselor, cu acelasi calcul de comision ca posSale. Totalul de aici e exact
     * totalul comenzii pe care o creeaza posSale la incasarea reala.
     *
     * @return array{lines: array<int, array{key: string, ticket_type_id: ?int, qty: int, unit_price: float, line_total: float}>, subtotal: float, commission: float, commission_vat: float, total: float, missing: string[]}
     */
    public function chargeLines(SaniusTab $tab, array $parking): array
    {
        $qty = array_fill_keys(SaniusSetting::PRODUCT_KEYS, 0);
        foreach ($tab->rentals()->whereIn('status', ['closed', 'lost'])->get() as $r) {
            $qty['rent']++;
            $qty['tow'] += (int) $r->tows_paid;
            if ($r->status === 'lost') {
                $qty['lost']++;
            } elseif ($r->condition === 'damaged' && in_array($r->damage_level, SaniusRental::DAMAGE_LEVELS, true)) {
                $qty['damage_'.$r->damage_level]++;
            }
        }
        $qty[($parking['vehicle_type'] ?? 'car') === 'bus' ? 'parking_bus' : 'parking_car'] = (int) ($parking['units'] ?? 0);

        [$rate, $fixed, $onTop, $vatRate] = $this->commissionTerms();

        $lines = [];
        $missing = [];
        $subtotal = 0.0;
        $commission = 0.0;
        foreach ($qty as $key => $n) {
            if ($n <= 0) {
                continue;
            }
            $ttId = $this->settings->productId($key);
            if (! $ttId) {
                $missing[] = $key;
            }
            $unit = $this->unitPrice($ttId);
            // posSale accepta cel mult 100 bucati pe linie
            while ($n > 0) {
                $chunk = min(100, $n);
                $line = round($unit * $chunk, 2);
                $lines[] = ['key' => $key, 'ticket_type_id' => $ttId, 'qty' => $chunk, 'unit_price' => $unit, 'line_total' => $line];
                $subtotal += $line;
                if ($onTop) {
                    $commission += round(max($unit * $rate / 100, $fixed) * $chunk, 2);
                }
                $n -= $chunk;
            }
        }
        $subtotal = round($subtotal, 2);
        $commission = round($commission, 2);
        $vat = ($vatRate > 0 && $commission > 0) ? round($commission * $vatRate / 100, 2) : 0.0;

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'commission' => $commission,
            'commission_vat' => $vat,
            'total' => round($subtotal + $commission + $vat, 2),
            'missing' => $missing,
        ];
    }

    /** [rata %, fix per bilet, comision peste pret?, TVA marketplace %] — ca in posSale. */
    private function commissionTerms(): array
    {
        $o = $this->organizer;
        if (! $o) {
            return [0.0, 0.0, false, 0.0];
        }

        return [
            (float) $o->getEffectiveCommissionRate(),
            (float) ($o->fixed_commission_default ?? 0),
            $o->getEffectiveCommissionMode() === 'added_on_top',
            (float) ($o->marketplaceClient?->vatRate() ?? 0),
        ];
    }

    /**
     * Tot ce are contul de plata acum: inchirieri, parcare, ce blocheaza plata.
     */
    public function tabSummary(SaniusTab $tab, ?string $vehicleType = null, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $vehicleType = in_array($vehicleType, ['car', 'bus'], true) ? $vehicleType : ($tab->vehicle_type ?: 'car');

        $rentals = $tab->rentals()->with('sled')->orderBy('started_at')->get();
        $lines = [];
        $blockers = [];
        $rentalsTotal = 0.0;

        foreach ($rentals as $r) {
            $row = [
                'id' => $r->id,
                'sled' => $r->sled?->displayName(),
                'sled_number' => $r->sled?->number,
                'status' => $r->status,
                'started_at' => $r->started_at?->toIso8601String(),
                'returned_at' => $r->returned_at?->toIso8601String(),
                'condition' => $r->condition,
                'damage_level' => $r->damage_level,
                'has_photo' => (bool) $r->damage_photo_path,
                'amounts' => $r->amounts,
            ];
            if (in_array($r->status, ['closed', 'lost'], true)) {
                $rentalsTotal += (float) ($r->amounts['total'] ?? 0);
            } elseif ($r->status === 'active') {
                $blockers[] = $r->sled?->displayName().' nu a fost returnată.';
            } else {
                $blockers[] = $r->sled?->displayName().' așteaptă sincronizarea tractărilor.';
            }
            $lines[] = $row;
        }

        $hasAccess = $this->tabHasAccessTicket($tab);
        $minutes = $tab->parking_entry_at ? max(0, (int) floor($tab->parking_entry_at->diffInMinutes($now, false))) : 0;
        $parking = SaniusPricing::parking(
            $minutes,
            $hasAccess,
            $this->settings->parkingRules(),
            $this->price($vehicleType === 'bus' ? 'parking_bus' : 'parking_car'),
        );
        $parking['vehicle_type'] = $vehicleType;
        $parking['has_access_ticket'] = $hasAccess;
        $parking['entry_at'] = $tab->parking_entry_at?->toIso8601String();

        $charge = $this->chargeLines($tab, $parking);
        $total = $charge['total'];
        if ($tab->isOpen() && $charge['missing']) {
            $blockers[] = 'Lipsesc produsele circuitului pentru: '.implode(', ', $charge['missing']).'.';
        }
        if (! $tab->isOpen()) {
            $blockers[] = 'Contul a fost deja achitat.';
            // Dupa plata aratam ce s-a incasat, nu o parcare recalculata cu ora de acum.
            $paid = (array) $tab->settlement;
            if (isset($paid['parking']) && is_array($paid['parking'])) {
                $parking = $paid['parking'];
            }
            if (isset($paid['total'])) {
                $total = round((float) $paid['total'], 2);
            }
            if (isset($paid['charge']) && is_array($paid['charge'])) {
                $charge = $paid['charge'];
            }
        }

        return [
            'tab' => [
                'id' => $tab->id,
                'parking_code' => $tab->parking_code,
                'parking_number' => $tab->parking_number,
                'vehicle_plate' => $tab->vehicle_plate,
                'vehicle_type' => $tab->vehicle_type,
                'status' => $tab->status,
                'is_test' => $tab->is_test,
                'settled_at' => $tab->settled_at?->toIso8601String(),
                'settled_simulated' => $tab->settled_simulated,
                'order_id' => $tab->order_id,
                'order_number' => $tab->settlement['order_number'] ?? null,
                'payment_method' => $tab->settlement['payment_method'] ?? null,
            ],
            'tickets' => $tab->tickets()->with('ticket.ticketType')->get()->map(fn (SaniusTabTicket $l) => [
                'ticket_id' => $l->ticket_id,
                'code' => $l->ticket?->code,
                'type' => $this->ticketTypeName($l->ticket?->ticketType),
                'linked_at_point' => $l->linked_at_point,
            ])->values()->all(),
            'rentals' => $lines,
            'rentals_total' => round($rentalsTotal, 2),
            'parking' => $parking,
            'charge' => $charge,
            'total' => $total,
            'can_settle' => empty($blockers),
            'blockers' => $blockers,
        ];
    }

    private function ticketTypeName(?TicketType $tt): ?string
    {
        if (! $tt) {
            return null;
        }
        $name = $tt->name;

        return is_array($name) ? ($name['ro'] ?? (reset($name) ?: null)) : $name;
    }

    /**
     * Plata simulata (modul Test): nu creeaza comanda, nu tipareste, nu trimite
     * nimic in contabilitate. Doar inchide contul cu sumele de acum.
     *
     * @throws SaniusException
     */
    public function settleSimulated(SaniusTab $tab, string $vehicleType, string $paymentMethod, ?int $memberId): SaniusTab
    {
        return DB::transaction(function () use ($tab, $vehicleType, $paymentMethod, $memberId) {
            $locked = SaniusTab::whereKey($tab->id)->lockForUpdate()->first();
            $summary = $this->tabSummary($locked, $vehicleType);
            if (! $summary['can_settle']) {
                throw new SaniusException(implode(' ', $summary['blockers']));
            }
            $locked->update([
                'status' => 'settled',
                'vehicle_type' => $vehicleType,
                'settled_at' => Carbon::now(),
                'settled_by_member_id' => $memberId,
                'settled_simulated' => true,
                'settlement' => [
                    'payment_method' => $paymentMethod,
                    'rentals_total' => $summary['rentals_total'],
                    'parking' => $summary['parking'],
                    'charge' => $summary['charge'],
                    'total' => $summary['total'],
                ],
            ]);

            return $locked->fresh();
        });
    }
}
