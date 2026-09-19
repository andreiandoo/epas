<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\ActivityVariant;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Turns the activity lines of a cart into staged order lines (validation,
 * seats, prices, commission) and then into order items, bookings and tickets.
 *
 * Cart line (from the site):
 *   {
 *     type: 'activity', activity: {id}, variant: {id},
 *     booking_date: 'Y-m-d',
 *     slot_start_time: 'HH:MM' | null         slot-mode products only
 *     quantity: N                              persons, or units (boats, cars)
 *     addons: [{id, qty}]                      optional
 *     meta: {vehicle_plate}                    parking
 *     components: [{item_id, slot_start_time}] packages: time of each timed component
 *   }
 *
 * The checkout controller keeps customer, order, loyalty and payment; this
 * class owns everything product-specific so it runs only for activity carts.
 * All checks run inside the checkout transaction, after the cart's activity
 * rows are locked.
 */
class ActivityOrderBuilder
{
    public function __construct(private ProductAvailability $availability)
    {
    }

    /**
     * Lock the rows of every product the cart touches (package components
     * included), in id order, until the transaction ends. Two checkouts for
     * the same product then run one after the other, even when there is no
     * booking row yet to lock; the fixed order keeps two carts from
     * deadlocking on each other.
     */
    public function lockProducts(array $items): void
    {
        $ids = collect($items)->pluck('activity_id')->map(fn ($id) => (int) $id)->filter()->unique();
        $components = \Illuminate\Support\Facades\DB::table('activity_package_items')
            ->whereIn('package_activity_id', $ids->all())
            ->pluck('component_activity_id');

        Activity::whereIn('id', $ids->merge($components)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * Validate and price the cart lines. Throws ActivityCartException with a
     * message for the customer on the first problem.
     *
     * @return array{lines: array, subtotal: float, commission: float, commission_on_top: float, organizer_ids: array<int, true>}
     */
    public function stage(array $items, MarketplaceClient $client): array
    {
        $lines = [];
        foreach (array_values($items) as $i => $item) {
            $lines[] = $this->stageLine($item, $client, $i + 1);
        }

        $this->checkAccessRequirements($lines);

        $subtotal = $commission = $onTop = 0.0;
        $organizers = [];
        foreach ($lines as $line) {
            $subtotal   += $line['line_total'];
            $commission += $line['commission'];
            if ($line['commission_mode'] === 'added_on_top') {
                $onTop += $line['commission'];
            }
            $organizers[(int) ($line['organizer_id'] ?? 0)] = true;
        }

        return [
            'lines'             => $lines,
            'subtotal'          => round($subtotal, 2),
            'commission'        => round($commission, 2),
            'commission_on_top' => round($onTop, 2),
            'organizer_ids'     => $organizers,
        ];
    }

    /**
     * Order items, bookings and tickets for the staged lines.
     *
     * @param array $beneficiaries [{name, email}] handed out to person tickets in order
     */
    public function persist(
        Order $order,
        array $staged,
        MarketplaceCustomer $customer,
        array $beneficiaries,
        bool $autoConfirmed,
        bool $isTestOrder,
        ?string $locale
    ): void {
        $bookingStatus = $autoConfirmed ? ActivityBooking::STATUS_PAID : ActivityBooking::STATUS_PENDING_PAYMENT;
        $ticketStatus  = $autoConfirmed ? 'valid' : 'pending';
        // Seats stay held for as long as the order can be paid.
        $heldUntil     = $autoConfirmed ? null : $order->expires_at;
        $nextName      = 0;

        foreach ($staged['lines'] as $line) {
            $activity = $line['activity'];
            $variant  = $line['variant'];

            $orderItem = $order->items()->create([
                'ticket_type_id' => null,
                'performance_id' => null,
                'name'           => $line['title'] . ' — ' . $line['variant_name'],
                'quantity'       => $line['quantity'],
                'unit_price'     => $line['unit_price'],
                'total'          => $line['line_total'],
                'meta'           => [
                    'order_type'      => 'activity',
                    'product_type'    => $activity->product_type,
                    'activity_id'     => $activity->id,
                    'variant_id'      => $variant->id,
                    'location_id'     => $line['location_id'],
                    'organizer_id'    => $line['organizer_id'],
                    'booking_date'    => $line['booking_date'],
                    'end_date'        => $line['end_date'],
                    'slot_start_time' => $line['slot_start'],
                    'slot_end_time'   => $line['slot_end'],
                    // legacy readers
                    'participants_count' => $line['quantity'],
                    'capacity_share'     => (int) ($variant->capacity_share ?: 1),
                    'addons'          => $line['addons'],
                    'components'      => array_map(fn ($c) => [
                        'activity_id'     => $c['activity']->id,
                        'variant_id'      => $c['variant']->id,
                        'title'           => $c['title'],
                        'variant'         => $c['variant_name'],
                        'quantity'        => $c['quantity'],
                        'slot_start_time' => $c['slot_start'],
                    ], $line['components']),
                ],
            ]);

            $booking = ActivityBooking::create([
                'marketplace_client_id'    => $order->marketplace_client_id,
                'activity_id'              => $activity->id,
                'variant_id'               => $variant->id,
                'location_id'              => $line['location_id'],
                'marketplace_organizer_id' => $line['organizer_id'],
                'marketplace_customer_id'  => $customer->id,
                'order_id'                 => $order->id,
                'booking_date'             => $line['booking_date'],
                'end_date'                 => $line['end_date'],
                'slot_start_time'          => $line['slot_start'],
                'slot_end_time'            => $line['slot_end'],
                'quantity'                 => $line['quantity'],
                // A package holds no seats itself; its components do.
                'participants_count'       => $line['kind'] === 'package' ? 0 : $line['consume'],
                'unit_price_cents'         => (int) round($line['unit_price'] * 100),
                'total_cents'              => (int) round($line['line_total'] * 100),
                'commission_cents'         => (int) round($line['commission'] * 100),
                'currency'                 => $order->currency,
                'addons'                   => $line['addons'] ?: null,
                'meta'                     => $line['meta'] ?: null,
                'status'                   => $bookingStatus,
                'held_until'               => $heldUntil,
            ]);

            $ticketBase = [
                'marketplace_client_id'      => $order->marketplace_client_id,
                'tenant_id'                  => null,
                'order_id'                   => $order->id,
                'order_item_id'              => $orderItem->id,
                'event_id'                   => null,
                'marketplace_event_id'       => null,
                'ticket_type_id'             => null,
                'marketplace_ticket_type_id' => null,
                'performance_id'             => null,
                'marketplace_customer_id'    => $customer->id,
                'locale'                     => $locale,
                'status'                     => $ticketStatus,
            ];

            if ($line['kind'] === 'package') {
                foreach ($line['components'] as $component) {
                    $componentBooking = ActivityBooking::create([
                        'marketplace_client_id'    => $order->marketplace_client_id,
                        'activity_id'              => $component['activity']->id,
                        'variant_id'               => $component['variant']->id,
                        'location_id'              => $component['activity']->location_id,
                        'marketplace_organizer_id' => $component['activity']->marketplace_organizer_id,
                        'marketplace_customer_id'  => $customer->id,
                        'order_id'                 => $order->id,
                        'package_booking_id'       => $booking->id,
                        'booking_date'             => $line['booking_date'],
                        'end_date'                 => $component['end_date'],
                        'slot_start_time'          => $component['slot_start'],
                        'slot_end_time'            => $component['slot_end'],
                        'quantity'                 => $component['quantity'],
                        'participants_count'       => $component['consume'],
                        'unit_price_cents'         => intdiv($component['total_cents'], max(1, $component['quantity'])),
                        'total_cents'              => $component['total_cents'],
                        'commission_cents'         => 0,
                        'currency'                 => $order->currency,
                        'status'                   => $bookingStatus,
                        'held_until'               => $heldUntil,
                    ]);
                    // Ticket prices add up to the component's share exactly:
                    // the first tickets carry the leftover cents.
                    $base = intdiv($component['total_cents'], max(1, $component['quantity']));
                    $rest = $component['total_cents'] - $base * $component['quantity'];
                    for ($n = 0; $n < $component['quantity']; $n++) {
                        $this->ticket($ticketBase, $componentBooking, $component['activity'], $component['variant'], [
                            'price'    => $isTestOrder ? 0 : ($base + ($n < $rest ? 1 : 0)) / 100,
                            'package'  => $line['title'],
                            'end_date' => $component['end_date'],
                        ], $beneficiaries, $nextName);
                    }
                }
                continue;
            }

            for ($n = 0; $n < $line['quantity']; $n++) {
                $this->ticket($ticketBase, $booking, $activity, $variant, [
                    'price'    => $isTestOrder ? 0 : $line['unit_price'],
                    'end_date' => $line['end_date'],
                ], $beneficiaries, $nextName);
            }
            if ($line['companion']) {
                $this->ticket($ticketBase, $booking, $activity, $variant, [
                    'price'     => 0,
                    'companion' => $line['companion'],
                    'end_date'  => $line['end_date'],
                ], [], $nextName);
            }
        }
    }

    // ================================================================
    // Staging one line
    // ================================================================

    private function stageLine(array $item, MarketplaceClient $client, int $n): array
    {
        $activity = Activity::with([
            'variants', 'schedules', 'scheduleExceptions', 'location', 'addons',
            'packageItems.component.variants', 'packageItems.component.schedules',
            'packageItems.component.scheduleExceptions', 'packageItems.component.location',
            'organizer.marketplaceClient',
        ])->find((int) ($item['activity_id'] ?? 0));

        if (!$activity || (int) $activity->marketplace_client_id !== (int) $client->id) {
            throw new ActivityCartException('Un produs din coș nu mai există.');
        }
        $title = self::ro($activity->title, 'Activitate');
        if (!$this->isSellable($activity)) {
            throw new ActivityCartException("„{$title}” nu mai e disponibil online.");
        }

        $variant = $activity->variants->firstWhere('id', (int) ($item['variant_id'] ?? 0));
        if (!$variant || !$variant->is_active || $variant->pos_only) {
            throw new ActivityCartException("Varianta aleasă la „{$title}” nu mai e disponibilă.");
        }
        $variantName = self::ro($variant->name, 'Bilet');

        $quantity = (int) ($item['quantity'] ?? $item['participants_count'] ?? 0);
        $this->checkQuantity($title, $variant, $quantity);

        $date = (string) ($item['booking_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !CarbonImmutable::createFromFormat('Y-m-d', $date)) {
            throw new ActivityCartException("Alege data pentru „{$title}”.");
        }

        $meta = [];
        if ($activity->requires_vehicle_info) {
            $plate = strtoupper(trim((string) ($item['meta']['vehicle_plate'] ?? $item['meta']['vehicle_info'] ?? '')));
            if ($plate === '' || strlen($plate) > 20) {
                throw new ActivityCartException("Completează numărul de înmatriculare pentru „{$title}”.");
            }
            $meta['vehicle_plate'] = $plate;
        }

        $line = [
            'kind'         => $activity->isPackage() ? 'package' : 'single',
            'activity'     => $activity,
            'variant'      => $variant,
            'title'        => $title,
            'variant_name' => $variantName,
            'quantity'     => $quantity,
            'booking_date' => $date,
            'end_date'     => null,
            'slot_start'   => null,
            'slot_end'     => null,
            'consume'      => 0,
            'components'   => [],
            'companion'    => null,
            'location_id'  => $activity->location_id,
            'organizer_id' => $activity->marketplace_organizer_id,
            'meta'         => $meta,
            'addons'       => [],
            'addons_total' => 0.0,
        ];

        if ($line['kind'] === 'package') {
            $line['components'] = $this->stagePackage($activity, $quantity, $date, (array) ($item['components'] ?? []), $title, $variant);
        } else {
            $validity = $activity->isDayMode() ? max(1, (int) ($variant->validity_days ?: 1)) : 1;
            $consume  = $quantity * max(1, (int) ($variant->capacity_share ?: 1));
            $start    = $activity->isDayMode() ? null : ($item['slot_start_time'] ?? null);

            $check = $this->availability->check($activity, $variant, $date, $start, $consume, $validity);
            if ($check['error']) {
                throw new ActivityCartException("„{$title}”: " . $check['error']);
            }
            $line['end_date']   = $check['end_date'];
            $line['slot_start'] = $start ? ProductAvailability::time($start) : null;
            $line['slot_end']   = $check['slot_end'];
            $line['consume']    = $consume;
            $this->availability->stage($activity, $date, $check['end_date'], $line['slot_start'], $line['slot_end'], $consume);

            if ($variant->companion_label && $quantity >= max(1, (int) $variant->min_per_order)) {
                $line['companion'] = $variant->companion_label;
            }
        }

        [$line['addons'], $line['addons_total']] = $this->stageAddons($activity, $quantity, (array) ($item['addons'] ?? []), $title);

        $line['unit_price'] = round(((int) $variant->price_cents) / 100, 2);
        $line['line_total'] = round($line['unit_price'] * $quantity + $line['addons_total'], 2);

        // Commission: variant override, else the operator's effective rate.
        // The mode (included / added on top) is the operator's.
        $organizer = $activity->organizer;
        $rate = $variant->commission_rate !== null
            ? (float) $variant->commission_rate
            : ($organizer ? (float) $organizer->getEffectiveCommissionRate() : 0.0);
        $line['commission_rate'] = $rate;
        $line['commission_mode'] = $organizer ? $organizer->getEffectiveCommissionMode() : 'included';
        $line['commission']      = round($line['line_total'] * $rate / 100, 2);

        return $line;
    }

    private function isSellable(Activity $activity): bool
    {
        if (!$activity->is_published || $activity->pos_only) {
            return false;
        }
        if ($activity->review_status !== null && $activity->review_status !== 'approved') {
            return false;
        }
        $location = $activity->location;
        if ($location && (!$location->is_published || $location->review_status !== 'approved')) {
            return false;
        }
        return true;
    }

    private function checkQuantity(string $title, ActivityVariant $variant, int $quantity): void
    {
        $min  = max(1, (int) $variant->min_per_order);
        $max  = (int) ($variant->max_per_order ?: 0);
        $step = max(1, (int) ($variant->step_qty ?: 1));

        if ($quantity < $min) {
            throw new ActivityCartException($min > 1
                ? "La „{$title}” se cumpără minim {$min} bilete."
                : "Alege câte bilete vrei la „{$title}”.");
        }
        if ($max > 0 && $quantity > $max) {
            throw new ActivityCartException("La „{$title}” se pot cumpăra cel mult {$max} bilete într-o comandă.");
        }
        if ($step > 1 && ($quantity - $min) % $step !== 0) {
            throw new ActivityCartException("La „{$title}” biletele se cumpără câte {$step}.");
        }
    }

    /**
     * @return array{0: array, 1: float} [addons chosen, their total]
     */
    private function stageAddons(Activity $activity, int $quantity, array $requested, string $title): array
    {
        $chosen = [];
        $total  = 0.0;
        foreach ($requested as $req) {
            $qty = (int) ($req['qty'] ?? $req['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $addon = $activity->addons->firstWhere('id', (int) ($req['id'] ?? 0));
            if (!$addon || !$addon->is_active) {
                throw new ActivityCartException("Un supliment ales la „{$title}” nu mai e disponibil.");
            }
            $included = $addon->included_qty * $quantity;
            $maxQty   = ($addon->included_qty + $addon->max_per_unit) * $quantity;
            if ($qty > $maxQty) {
                throw new ActivityCartException(sprintf('La „%s” poți lua cel mult %d × %s.', $title, $maxQty, self::ro($addon->name, 'supliment')));
            }
            $paid      = max(0, $qty - $included);
            $unitPrice = round($addon->price_cents / 100, 2);
            $lineTotal = round($paid * $unitPrice, 2);
            $total    += $lineTotal;
            $chosen[] = [
                'id'         => $addon->id,
                'name'       => self::ro($addon->name, 'Supliment'),
                'qty'        => $qty,
                'included'   => min($qty, $included),
                'paid_qty'   => $paid,
                'unit_price' => $unitPrice,
                'total'      => $lineTotal,
            ];
        }
        return [$chosen, round($total, 2)];
    }

    /**
     * Components of a package line: each one checked against its own product's
     * seats on the package date; timed components need the chosen start time.
     */
    private function stagePackage(Activity $package, int $quantity, string $date, array $times, string $title, ActivityVariant $packageVariant): array
    {
        $items = $package->packageItems;
        if ($items->isEmpty()) {
            throw new ActivityCartException("Pachetul „{$title}” nu mai e disponibil.");
        }
        $timeFor = [];
        foreach ($times as $t) {
            if (isset($t['item_id'])) {
                $timeFor[(int) $t['item_id']] = $t['slot_start_time'] ?? null;
            }
        }

        // Share of the package price per component (per package unit): the
        // operator's allocation, or split pro rata by the components' own
        // prices with the largest remainder, so the shares add up exactly.
        $packageCents = (int) $packageVariant->price_cents;
        $shares = [];
        if ($items->every(fn ($i) => $i->allocated_price_cents !== null)) {
            foreach ($items as $item) {
                $shares[$item->id] = (int) $item->allocated_price_cents;
            }
        } else {
            $weights = [];
            foreach ($items as $item) {
                $v = $item->componentVariant ?: $item->component?->variants->where('is_active', true)->sortBy('sort_order')->first();
                $weights[$item->id] = max(0, (int) ($v?->price_cents ?? 0)) * max(1, (int) $item->quantity);
            }
            if (array_sum($weights) <= 0) {
                $weights = array_fill_keys(array_keys($weights), 1);   // free components: equal split
            }
            $weightSum = array_sum($weights);
            $fractions = [];
            foreach ($weights as $id => $w) {
                $raw            = $packageCents * $w / $weightSum;
                $shares[$id]    = (int) floor($raw);
                $fractions[$id] = $raw - floor($raw);
            }
            // The cents lost to rounding (fewer than the number of components)
            // go to the largest remainders.
            arsort($fractions);
            $left = $packageCents - array_sum($shares);
            foreach (array_keys($fractions) as $id) {
                if ($left-- <= 0) {
                    break;
                }
                $shares[$id]++;
            }
        }

        $components = [];
        foreach ($items as $item) {
            $product = $item->component;
            if (!$product || (int) $product->marketplace_client_id !== (int) $package->marketplace_client_id) {
                throw new ActivityCartException("Pachetul „{$title}” nu mai e disponibil.");
            }
            $variant = $item->component_variant_id
                ? $product->variants->firstWhere('id', (int) $item->component_variant_id)
                : $product->variants->where('is_active', true)->sortBy('sort_order')->first();
            if (!$variant) {
                throw new ActivityCartException("Pachetul „{$title}” nu mai e disponibil.");
            }
            $productTitle = self::ro($product->title, 'Produs');
            $qty      = max(1, (int) $item->quantity) * $quantity;
            $consume  = $qty * max(1, (int) ($variant->capacity_share ?: 1));
            $validity = $product->isDayMode() ? max(1, (int) ($variant->validity_days ?: 1)) : 1;
            $start    = $product->isDayMode() ? null : ($timeFor[$item->id] ?? null);

            $check = $this->availability->check($product, $variant, $date, $start, $consume, $validity);
            if ($check['error']) {
                throw new ActivityCartException("„{$title}” ({$productTitle}): " . $check['error']);
            }
            $slotStart = $start ? ProductAvailability::time($start) : null;
            $this->availability->stage($product, $date, $check['end_date'], $slotStart, $check['slot_end'], $consume);

            $components[] = [
                'item_id'      => $item->id,
                'activity'     => $product,
                'variant'      => $variant,
                'title'        => $productTitle,
                'variant_name' => self::ro($variant->name, 'Bilet'),
                'quantity'     => $qty,
                'consume'      => $consume,
                'end_date'     => $check['end_date'],
                'slot_start'   => $slotStart,
                'slot_end'     => $check['slot_end'],
                // this component's part of the package price, for all packages bought
                'total_cents'  => $shares[$item->id] * $quantity,
            ];
        }
        return $components;
    }

    /**
     * Products that need an access ticket ("any" or "adult") must have enough
     * access tickets of the same location and date in the cart: one per unit
     * bought (a boat needs one adult, a ride one person). Package components
     * count on both sides.
     */
    private function checkAccessRequirements(array $lines): void
    {
        $access = [];   // "location|date" => ['any' => n, 'adult' => n]
        $needs  = [];   // "location|date|rule" => [n, title, location name]

        $add = function (Activity $product, ActivityVariant $variant, int $qty, string $date) use (&$access, &$needs) {
            if (!$product->location_id) {
                return;
            }
            $key = $product->location_id . '|' . $date;
            if ($product->isAccess()) {
                $persons = $variant->price_type === ActivityVariant::PRICE_PER_UNIT
                    ? $qty * max(1, (int) ($variant->persons_max ?: 1))
                    : $qty;
                $access[$key]['any'] = ($access[$key]['any'] ?? 0) + $persons;
                if (!$variant->is_child) {
                    $access[$key]['adult'] = ($access[$key]['adult'] ?? 0) + $persons;
                }
                return;
            }
            $rule = $product->access_requirement;
            if (in_array($rule, [Activity::REQUIRES_ANY, Activity::REQUIRES_ADULT], true)) {
                $k = $key . '|' . $rule;
                $needs[$k] = [($needs[$k][0] ?? 0) + $qty, self::ro($product->title, 'Produs'), self::ro($product->location?->name, 'locație')];
            }
        };

        foreach ($lines as $line) {
            if ($line['kind'] === 'package') {
                foreach ($line['components'] as $c) {
                    $add($c['activity'], $c['variant'], $c['quantity'], $line['booking_date']);
                }
            } else {
                $add($line['activity'], $line['variant'], $line['quantity'], $line['booking_date']);
            }
        }

        foreach ($needs as $k => [$count, $title, $locationName]) {
            [$locationId, $date, $rule] = explode('|', $k);
            $have = $access[$locationId . '|' . $date][$rule === Activity::REQUIRES_ADULT ? 'adult' : 'any'] ?? 0;
            if ($have < $count) {
                throw new ActivityCartException($rule === Activity::REQUIRES_ADULT
                    ? "Pentru „{$title}” ai nevoie în coș și de câte un bilet de acces pentru adult la {$locationName}, în aceeași zi."
                    : "Pentru „{$title}” ai nevoie în coș și de bilete de acces la {$locationName}, în aceeași zi.");
            }
        }
    }

    // ================================================================
    // Tickets
    // ================================================================

    private function ticket(array $base, ActivityBooking $booking, Activity $activity, ActivityVariant $variant, array $extra, array $beneficiaries, int &$nextName): void
    {
        $companion = $extra['companion'] ?? null;
        $holder = null;
        if (!$companion && $variant->price_type !== ActivityVariant::PRICE_PER_UNIT) {
            $holder = $beneficiaries[$nextName] ?? null;
            $nextName++;
        }

        Ticket::create($base + [
            'activity_booking_id' => $booking->id,
            'code'                => strtoupper(Str::random(8)),
            'barcode'             => Str::uuid()->toString(),
            'price'               => $extra['price'],
            'attendee_name'       => $companion ?: ($holder['name'] ?? null),
            'attendee_email'      => $holder['email'] ?? null,
            'meta'                => array_filter([
                'activity_id'     => $activity->id,
                'variant_id'      => $variant->id,
                'product_type'    => $activity->product_type,
                'location_id'     => $activity->location_id,
                'booking_date'    => $booking->booking_date?->toDateString(),
                'end_date'        => $extra['end_date'] ?? null,
                'slot_start_time' => $booking->getRawOriginal('slot_start_time') ?: null,
                'slot_end_time'   => $booking->getRawOriginal('slot_end_time') ?: null,
                'package'         => $extra['package'] ?? null,
                'companion'       => $companion ? true : null,
            ], fn ($v) => $v !== null),
        ]);
    }

    /** Romanian text of a translatable value (array or string). */
    public static function ro($value, string $fallback = ''): string
    {
        if (is_array($value)) {
            $value = $value['ro'] ?? $value['en'] ?? (array_values(array_filter($value))[0] ?? null);
        }
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : $fallback;
    }
}
