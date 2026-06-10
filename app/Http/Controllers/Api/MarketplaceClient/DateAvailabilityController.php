<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceEventDateCapacity;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DateAvailabilityController extends BaseController
{
    /**
     * GET /events/{identifier}/date-availability
     *
     * Query params:
     *   ?date=2026-07-15          → single date availability with full ticket type details
     *   ?month=2026-07            → month summary for calendar view
     */
    public function __invoke(Request $request, string $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Postgres: orWhere('id', $identifier) cu un slug string crash-uieste
        // ("invalid input syntax for type bigint"). Aplica orWhere id-ul DOAR
        // cand identifier-ul e numeric.
        $query = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier);
                if (is_numeric($identifier)) {
                    $q->orWhere('id', (int) $identifier);
                }
            });

        // ?preview=1 ocoleste filtrul is_published — necesar pentru evenimente
        // draft (cazul tipic in dev/test). Pe public, fara preview, sunt vizibile
        // doar evenimentele publicate.
        if (!$request->boolean('preview')) {
            $query->where('is_published', true);
        }

        $event = $query->first();

        if (!$event || !$event->isLeisureVenue()) {
            return response()->json(['error' => 'Event not found or not a leisure venue'], 404);
        }

        $date = $request->query('date');
        $month = $request->query('month');

        if ($date) {
            return $this->singleDateAvailability($event, $date);
        }

        if ($month) {
            return $this->monthAvailability($event, $month);
        }

        return response()->json(['error' => 'Provide either ?date=YYYY-MM-DD or ?month=YYYY-MM'], 400);
    }

    /**
     * Full ticket type details for a single date.
     */
    private function singleDateAvailability(Event $event, string $date): JsonResponse
    {
        $dateStr = $date;

        // Locale efectiv pentru randarea publica a paginii leisure. Vine din
        // request->query('lang') (selectorul de pe leisure-venue.php), validat
        // strict la lista de locale disponibile. Fallback 'ro' (backward compat).
        // Gating extra: e activ DOAR pentru leisure_venue (controller-ul intoarce
        // 404 mai sus pentru orice alt tip de event), deci zero risc de scurgere
        // pe alte flow-uri.
        $availableLocales = config('locales.available', ['ro']);
        $requestedLocale = request()->query('lang');
        $publicLocale = (is_string($requestedLocale) && in_array($requestedLocale, $availableLocales, true))
            ? $requestedLocale
            : 'ro';

        // Validate date is within event range. Event uses range_start_date / range_end_date
        // for leisure venues with seasonal/recurring schedule, instead of starts_at/ends_at.
        $rangeStart = $event->range_start_date ?? $event->event_date ?? null;
        $rangeEnd = $event->range_end_date ?? null;

        if ($rangeStart && Carbon::parse($dateStr)->lt(Carbon::parse($rangeStart)->startOfDay())) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'before_season']);
        }
        if ($rangeEnd && Carbon::parse($dateStr)->gt(Carbon::parse($rangeEnd)->endOfDay())) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'after_season']);
        }

        // Check if date is open per venue schedule
        if (!$event->isDateOpen($dateStr)) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'closed']);
        }

        // Check if past last entry time (only relevant for today)
        $pastLastEntry = $event->isPastLastEntry($dateStr);

        $operatingHours = $event->getOperatingHours($dateStr);
        $season = $event->getSeasonForDate($dateStr);

        // Event model: ticketTypes() returnează TicketType (table ticket_types).
        // Status logic: TicketType nu are status='on_sale' ca MarketplaceTicketType.
        // Folosim is_active (mutator) si filtram out is_entry_ticket / is_invitation.
        $ticketTypes = $event->ticketTypes()
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($tt) {
                if ($tt->is_entry_ticket) return false;
                if (!empty($tt->meta['is_invitation'] ?? false)) return false;
                // Leisure: produsele marcate "POS only" sunt ascunse de pe pagina publica.
                if (!empty($tt->meta['pos_only'] ?? false)) return false;
                return true;
            });

        $ticketData = [];

        foreach ($ticketTypes as $tt) {
            $available = null;
            $effectivePrice = (float) ($tt->price_max ?? $tt->price ?? 0);

            if ($tt->daily_capacity) {
                // Daily capacity tracking via MarketplaceEventDateCapacity ramane disponibil
                // doar daca event-ul are si un MarketplaceEvent corespunzator. Pentru
                // Event direct, facem static fallback la daily_capacity (fara live tracking
                // de sold per zi — va fi adaugat cu F4/F5 cand avem cart cu visit_date).
                $available = (int) $tt->daily_capacity;
                $effectivePrice = $event->getEffectivePrice($tt, $dateStr);
            } else {
                // available_quantity = PHP_INT_MAX cand quota_total e NULL sau <0 (unlimited).
                // Frontend leisure-venue.js trateaza `null` ca unlimited (NU buton dezactivat).
                // Convertim PHP_INT_MAX → null ca să afișăm corect produsele cu stoc nelimitat.
                $rawAvail = $tt->available_quantity;
                $available = ($rawAvail === null || $rawAvail >= PHP_INT_MAX) ? null : $rawAvail;
                $effectivePrice = $event->getEffectivePrice($tt, $dateStr);
            }

            // Tour slot support: check if this ticket type has guided tour slots
            $meta = $tt->meta ?? [];
            $hasTourSlots = (bool) ($meta['has_tour_slots'] ?? false);
            $tourSlots = null;

            if ($hasTourSlots) {
                $slotTimes = $meta['slot_times'] ?? [];
                $maxPerSlot = (int) ($meta['max_per_slot'] ?? 20);
                $seasonalAvail = $meta['seasonal_availability'] ?? null;

                // Check seasonal availability (e.g. "summer" = only in summer season)
                if ($seasonalAvail && $season) {
                    $seasonName = strtolower($season['name'] ?? '');
                    if ($seasonalAvail === 'summer' && !str_contains($seasonName, 'var')) {
                        continue; // Skip this ticket type — not available in current season
                    }
                    if ($seasonalAvail === 'winter' && !str_contains($seasonName, 'iarn')) {
                        continue;
                    }
                }

                // Build slot availability (could track per-slot sales via date_capacities notes or meta)
                $tourSlots = array_map(fn ($time) => [
                    'time' => $time,
                    'available' => true, // TODO: track per-slot capacity when needed
                    'max' => $maxPerSlot,
                ], $slotTimes);
            }

            // Image din meta (setat prin FileUpload pe meta.image in Filament).
            // Storage::disk('public')->url() pentru URL absolut accesibil.
            $imagePath = $tt->meta['image'] ?? null;
            $imageUrl = null;
            if ($imagePath) {
                if (str_starts_with($imagePath, 'http')) {
                    $imageUrl = $imagePath;
                } else {
                    $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($imagePath);
                }
            }

            $iconEmoji = $tt->meta['icon'] ?? null;
            $unitLabel = $tt->meta['unit_label'] ?? null;
            $includes = $tt->meta['includes'] ?? null;
            if ($includes && !is_array($includes)) {
                $includes = is_string($includes) ? array_filter(array_map('trim', explode(',', $includes))) : null;
            }

            // Variante de preț/durată (F1 — Bărci 30m/1h, Sanii etc.)
            $rawVariants = $tt->meta['variants'] ?? null;
            $variants = [];
            if (is_array($rawVariants)) {
                foreach ($rawVariants as $v) {
                    if (!is_array($v) || empty($v['label'])) continue;
                    $variants[] = [
                        'id' => $v['id'] ?? \Illuminate\Support\Str::slug($v['label']),
                        'label' => $v['label'],
                        'duration_minutes' => isset($v['duration_minutes']) ? (int) $v['duration_minutes'] : null,
                        'price' => isset($v['price']) ? (float) $v['price'] : (float) ($tt->price_max ?? $tt->price ?? 0),
                    ];
                }
            }

            // Slot-uri pe oră (F3 — Vaporașe etc.)
            $slotsConfig = null;
            $rawSlotsConfig = $tt->meta['slots_config'] ?? null;
            if (is_array($rawSlotsConfig) && !empty($rawSlotsConfig['enabled'])) {
                $slotsConfig = [
                    'enabled' => true,
                    'first_slot' => (string) ($rawSlotsConfig['first_slot'] ?? '09:00'),
                    'last_slot' => (string) ($rawSlotsConfig['last_slot'] ?? '18:00'),
                    'interval_minutes' => max(5, (int) ($rawSlotsConfig['interval_minutes'] ?? 30)),
                    'duration_minutes' => max(5, (int) ($rawSlotsConfig['duration_minutes'] ?? 30)),
                    'capacity_per_slot' => max(1, (int) ($rawSlotsConfig['capacity_per_slot'] ?? 1)),
                    'unit_pricing' => in_array(($rawSlotsConfig['unit_pricing'] ?? 'per_person'), ['per_person', 'per_slot'], true)
                        ? $rawSlotsConfig['unit_pricing']
                        : 'per_person',
                ];
            }

            // Inventar fizic (F5 — Bărci etc.)
            $physicalInventory = null;
            $rawPhysical = $tt->meta['physical_inventory'] ?? null;
            if (is_array($rawPhysical) && !empty($rawPhysical['enabled'])) {
                $physicalInventory = [
                    'enabled' => true,
                    'count' => max(1, (int) ($rawPhysical['count'] ?? 1)),
                ];
            }

            // F6/F8/F9 — POS price, child marker, access requirement
            $posPrice = $tt->meta['pos_price'] ?? null;
            $posPrice = ($posPrice !== null && $posPrice !== '') ? (float) $posPrice : null;
            $isChildTicket = (bool) ($tt->meta['is_child_ticket'] ?? false);
            $accessRequirement = $tt->meta['access_requirement'] ?? null;
            if (!in_array($accessRequirement, ['none', 'any', 'adult_only'], true)) {
                // Fallback compatibilitate: dacă boolean vechi e true și nu există enum, tratează ca 'any'
                $accessRequirement = ($tt->requires_access_ticket ?? false) ? 'any' : 'none';
            }

            // F10 — blocked time ranges (informativ, filtrat pe data curentă)
            $blockedRangesAll = is_array($tt->meta['blocked_time_ranges'] ?? null) ? $tt->meta['blocked_time_ranges'] : [];
            $blockedToday = [];
            foreach ($blockedRangesAll as $r) {
                if (!is_array($r)) continue;
                if (($r['date'] ?? null) === $dateStr) {
                    $blockedToday[] = [
                        'start_time' => $r['start_time'] ?? null,
                        'end_time' => $r['end_time'] ?? null,
                        'reason' => $r['reason'] ?? null,
                    ];
                }
            }

            // Add-ons inline (F2: ex. Tractare suplimentară pe Sanii)
            $rawAddons = $tt->meta['addons'] ?? null;
            $addons = [];
            if (is_array($rawAddons)) {
                foreach ($rawAddons as $a) {
                    if (!is_array($a) || empty($a['label'])) continue;
                    $addons[] = [
                        'id' => $a['id'] ?? \Illuminate\Support\Str::slug($a['label']),
                        'label' => $a['label'],
                        'price' => (float) ($a['price'] ?? 0),
                        'included_qty' => max(0, (int) ($a['included_qty'] ?? 0)),
                        'max_per_unit' => max(0, (int) ($a['max_per_unit'] ?? 5)),
                    ];
                }
            }

            // Pachete (F4): expune componentele + suma componentelor pentru "Economisești X"
            $rawPackageOutputs = $tt->meta['package_outputs'] ?? null;
            $packageOutputs = [];
            $packageSumComponents = 0.0;
            if (is_array($rawPackageOutputs) && ($tt->service_category ?? null) === 'package') {
                $componentIds = collect($rawPackageOutputs)->pluck('ticket_type_id')->filter()->unique();
                $components = \App\Models\TicketType::query()->whereIn('id', $componentIds)->get()->keyBy('id');
                foreach ($rawPackageOutputs as $row) {
                    if (!is_array($row) || empty($row['ticket_type_id'])) continue;
                    $compTt = $components->get($row['ticket_type_id']);
                    if (!$compTt) continue;
                    $compPrice = (float) ($compTt->price_max ?? $compTt->price ?? 0);
                    // Dacă specificată variantă, folosește prețul ei
                    if (!empty($row['variant_id']) && is_array($compTt->meta['variants'] ?? null)) {
                        foreach ($compTt->meta['variants'] as $cv) {
                            if (!is_array($cv) || empty($cv['label'])) continue;
                            $cvid = $cv['id'] ?? \Illuminate\Support\Str::slug($cv['label']);
                            if ($cvid === $row['variant_id']) {
                                $compPrice = (float) ($cv['price'] ?? $compPrice);
                                break;
                            }
                        }
                    }
                    $qtyPerPkg = (int) ($row['qty'] ?? 1);
                    $packageSumComponents += $compPrice * $qtyPerPkg;
                    $packageOutputs[] = [
                        'ticket_type_id' => (int) $row['ticket_type_id'],
                        'variant_id' => $row['variant_id'] ?? null,
                        'qty' => $qtyPerPkg,
                        'component_name' => is_array($compTt->name) ? ($compTt->name['ro'] ?? reset($compTt->name)) : $compTt->name,
                        'component_unit_price' => $compPrice,
                    ];
                }
            }

            // Multi-locale: aplica traduceri opt-in din meta.translations. Daca
            // organizatorul nu a completat traduceri pentru locale-ul cerut,
            // fallback-ul intoarce valoarea actuala (string original RO) → zero
            // regresie pe organizatorii care nu folosesc traduceri.
            $translations = is_array($tt->meta['translations'] ?? null) ? $tt->meta['translations'] : [];
            $name = $this->pickTranslation($translations, 'name', $publicLocale, $tt->name);
            $description = $this->pickTranslation($translations, 'description', $publicLocale, $tt->description);
            $productDescription = $this->pickTranslation($translations, 'product_description', $publicLocale, $tt->product_description);
            $usageTerms = $this->pickTranslation($translations, 'usage_terms', $publicLocale, $tt->usage_terms);
            $unitLabel = $this->pickTranslation($translations, 'unit_label', $publicLocale, $unitLabel);
            $includes = $this->pickTranslation($translations, 'includes', $publicLocale, $includes);
            // Variants & addons: traducerile sunt indexate dupa id ({"id":{"locale":"label"}})
            $variants = $this->localizeArrayItems($variants, $translations['variants'] ?? null, $publicLocale);
            $addons = $this->localizeArrayItems($addons, $translations['addons'] ?? null, $publicLocale);

            $ttData = [
                'id' => $tt->id,
                'name' => $name,
                'description' => $description,
                'group' => $tt->ticket_group,
                'base_price' => (float) ($tt->price_max ?? $tt->price ?? 0),
                'effective_price' => $effectivePrice,
                'currency' => $tt->currency ?? 'RON',
                'available' => $available,
                'capacity' => $tt->daily_capacity ?? $tt->quota_total ?? null,
                'min_per_order' => $tt->min_per_order ?? 1,
                'max_per_order' => $tt->max_per_order ?? 10,
                'is_parking' => (bool) $tt->is_parking,
                'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                'is_refundable' => (bool) $tt->is_refundable,
                // Leisure venue: issuer + service category fields (NULL fallback la 'primary' / 'access')
                'issuing_company' => $tt->issuing_company ?: 'primary',
                'service_category' => $tt->service_category ?: 'access',
                'service_duration_minutes' => $tt->service_duration_minutes,
                'product_description' => $productDescription,
                'usage_terms' => $usageTerms,
                'requires_access_ticket' => (bool) ($tt->requires_access_ticket ?? false),
                'image_url' => $imageUrl,
                'icon' => $iconEmoji,
                'unit_label' => $unitLabel,
                'includes' => $includes ?: [],
                'variants' => $variants,
                'addons' => $addons,
                'slots_config' => $slotsConfig,
                'physical_inventory' => $physicalInventory,
                'pos_price' => $posPrice,
                'is_child_ticket' => $isChildTicket,
                'access_requirement' => $accessRequirement,
                'blocked_time_ranges_today' => $blockedToday,
                'package_outputs' => $packageOutputs,
                'package_components_sum' => round($packageSumComponents, 2),
                'package_savings' => $packageOutputs ? round($packageSumComponents - $effectivePrice, 2) : 0,
            ];

            if ($hasTourSlots) {
                $ttData['has_tour_slots'] = true;
                $ttData['tour_slots'] = $tourSlots;
            }

            $ticketData[] = $ttData;
        }

        // Comision la nivel de organizator (folosit la cart pentru calcul live)
        $commission = ['rate' => 0.0, 'fixed' => 0.0, 'mode' => 'included'];
        if ($event->marketplace_organizer_id) {
            $org = \App\Models\MarketplaceOrganizer::find($event->marketplace_organizer_id);
            if ($org) {
                $commission = [
                    'rate' => (float) $org->getEffectiveCommissionRate(),
                    'fixed' => (float) ($org->fixed_commission_default ?? 0),
                    'mode' => $org->getEffectiveCommissionMode(),
                ];
            }
        }

        return response()->json([
            'date' => $dateStr,
            'is_open' => true,
            'past_last_entry' => $pastLastEntry,
            'operating_hours' => $operatingHours,
            'season' => $season ? ['name' => $season['name'] ?? null] : null,
            'commission' => $commission,
            'ticket_types' => $ticketData,
        ]);
    }

    /**
     * Month summary for calendar — returns status per date.
     */
    private function monthAvailability(Event $event, string $month): JsonResponse
    {
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Clamp to event range (range_start_date / range_end_date pe Event leisure_venue)
        $rangeStart = $event->range_start_date ?? $event->event_date ?? null;
        $rangeEnd = $event->range_end_date ?? null;
        if ($rangeStart && $start->lt(Carbon::parse($rangeStart)->startOfDay())) {
            $start = Carbon::parse($rangeStart)->startOfDay();
        }
        if ($rangeEnd && $end->gt(Carbon::parse($rangeEnd)->endOfDay())) {
            $end = Carbon::parse($rangeEnd)->endOfDay();
        }

        // Capacitati per zi: pentru moment lasam $existingCapacities goal — Event nu se
        // sincronizeaza in MarketplaceEventDateCapacity. F4/F5 va aduce live tracking.
        $existingCapacities = collect();

        // Ticket types cu daily_capacity (pentru pretul minim afisat in calendar)
        $ticketTypes = $event->ticketTypes()
            ->whereNotNull('daily_capacity')
            ->get();

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();

            if (!$event->isDateOpen($dateStr)) {
                $dates[$dateStr] = ['status' => 'closed'];
                $cursor->addDay();
                continue;
            }

            // Check if past
            if ($cursor->lt(now()->startOfDay())) {
                $dates[$dateStr] = ['status' => 'past'];
                $cursor->addDay();
                continue;
            }

            // Calculate availability
            $existing = $existingCapacities->get($dateStr);

            if ($existing && $existing->isNotEmpty()) {
                // Use existing capacity rows
                $totalCapacity = $existing->sum('capacity');
                $totalUsed = $existing->sum('sold') + $existing->sum('reserved');
                $allClosed = $existing->every(fn ($r) => $r->is_closed);

                if ($allClosed) {
                    $dates[$dateStr] = ['status' => 'closed'];
                } elseif ($totalCapacity > 0 && $totalUsed >= $totalCapacity) {
                    $dates[$dateStr] = ['status' => 'sold_out'];
                } elseif ($totalCapacity > 0 && (($totalCapacity - $totalUsed) / $totalCapacity) < 0.3) {
                    $dates[$dateStr] = ['status' => 'limited'];
                } else {
                    $dates[$dateStr] = ['status' => 'available'];
                }
            } else {
                // No capacity rows yet — derive from defaults
                $totalDefault = $ticketTypes->sum('daily_capacity');
                $dates[$dateStr] = ['status' => $totalDefault > 0 ? 'available' : 'available'];
            }

            // Add min price for available dates
            if (in_array($dates[$dateStr]['status'], ['available', 'limited'])) {
                $minPrice = $ticketTypes->map(fn ($tt) => $event->getEffectivePrice($tt, $dateStr))->min();
                if ($minPrice !== null) {
                    $dates[$dateStr]['min_price'] = $minPrice;
                }
            }

            $cursor->addDay();
        }

        return response()->json([
            'month' => $month,
            'event_id' => $event->id,
            'dates' => $dates,
        ]);
    }

    /**
     * Selecteaza traducerea unui camp din meta.translations.{field}[locale] cu
     * fallback la valoarea actuala (de obicei RO). Folosit pentru name,
     * description, product_description, usage_terms, unit_label, includes.
     *
     * Stocarea conventionala:
     *   meta.translations: {
     *     "name":        {"hu": "Felnőtt jegy", "en": "Adult ticket"},
     *     "description": {"hu": "...",          "en": "..."},
     *     "includes":    {"hu": ["...","..."],  "en": ["...","..."]},
     *     ...
     *   }
     *
     * RO ramane in coloanele/meta-urile originale (descrierea sursa = $tt->X)
     * si nu se duplica in translations. Daca translations[field][locale] e gol
     * sau lipseste, intoarcem $default.
     */
    private function pickTranslation(array $translations, string $field, string $locale, mixed $default): mixed
    {
        if ($locale === 'ro') return $default; // RO = sursa, fara dublare
        if (!isset($translations[$field]) || !is_array($translations[$field])) return $default;
        $value = $translations[$field][$locale] ?? null;
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return $default;
        }
        return $value;
    }

    /**
     * Aplica traduceri pe array de item-uri cu id (variants[] / addons[]).
     * Schema asteptata in meta.translations.variants / .addons:
     *   {"<id>": {"hu": "...", "en": "..."}}
     *
     * Pentru fiecare item din $items (cu cheia 'id' + 'label'), daca exista
     * o traducere pentru locale-ul cerut, suprascrie labelul. Daca nu, las
     * labelul actual neatins → backward compat 100%.
     */
    private function localizeArrayItems(array $items, ?array $translationsForGroup, string $locale): array
    {
        if ($locale === 'ro' || !is_array($translationsForGroup) || empty($translationsForGroup)) {
            return $items;
        }
        return array_map(function (array $item) use ($translationsForGroup, $locale) {
            $id = $item['id'] ?? null;
            if (!$id || !isset($translationsForGroup[$id]) || !is_array($translationsForGroup[$id])) {
                return $item;
            }
            $translatedLabel = $translationsForGroup[$id][$locale] ?? null;
            if ($translatedLabel !== null && $translatedLabel !== '') {
                $item['label'] = (string) $translatedLabel;
            }
            return $item;
        }, $items);
    }
}
