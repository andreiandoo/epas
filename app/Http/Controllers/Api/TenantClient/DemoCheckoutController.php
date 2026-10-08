<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Coupon\CouponCode;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Leisure\CapacityAvailabilityService;
use App\Services\Leisure\LeisurePricingResolver;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Checkout DEMO pentru site-urile tenant de test: creează o comandă reală
 * (pending) + bilete, apoi inițiază "plata" prin DemoProcessor și întoarce
 * URL-ul paginii demo de plată. La confirmare, comanda devine paid și biletele
 * valid (via observer-ul Order), iar locurile sunt marcate vândute.
 */
class DemoCheckoutController extends Controller
{
    use ResolvesTenant;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id'          => 'required|integer',
            'event_seating_id'  => 'nullable|integer',
            'customer.first_name' => 'nullable|string|max:255',
            'customer.last_name'  => 'nullable|string|max:255',
            'customer.email'      => 'required|email|max:255',
            'customer.phone'      => 'nullable|string|max:50',
            'seats'             => 'nullable|array',
            'seats.*.seat_uid'  => 'required_with:seats|string|max:64',
            'seats.*.price'     => 'nullable|numeric|min:0',
            'seats.*.label'     => 'nullable|string|max:190',
            'items'             => 'nullable|array',
            'items.*.ticket_type_id' => 'required_with:items|integer',
            'items.*.quantity'  => 'required_with:items|integer|min:1',
            // Leisure booking (tenant_type=leisure). All nullable: a non-leisure
            // checkout never sends them and behaves exactly as before.
            'items.*.capacity_id'      => 'nullable|integer',
            'items.*.visit_date'       => 'nullable|date_format:Y-m-d',
            'items.*.slot_time'        => 'nullable|date_format:H:i',
            'items.*.duration_minutes' => 'nullable|integer|min:1',
            'beneficiaries'     => 'nullable|array',
            'beneficiaries.*.name'  => 'nullable|string|max:190',
            'beneficiaries.*.email' => 'nullable|email|max:190',
            'create_account'    => 'nullable|boolean',
            'password'          => 'nullable|string|min:8',
            'newsletter'        => 'nullable|boolean',
            'payment_method'    => 'nullable|string|max:40',
            // Cod de reducere (coupon_codes al tenantului) — validat și calculat pe server.
            'coupon_code'       => 'nullable|string|max:50',
            'success_url'       => 'nullable|url',
            'cancel_url'        => 'nullable|url',
        ]);

        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];

        $event = Event::where('tenant_id', $tenant->id)->find($validated['event_id']);
        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Event not found'], 404);
        }

        $seats = $validated['seats'] ?? [];
        $items = $validated['items'] ?? [];
        if (empty($seats) && empty($items)) {
            return response()->json(['success' => false, 'error' => 'Coș gol'], 422);
        }

        try {
            $result = DB::transaction(function () use ($validated, $tenant, $event, $seats, $items) {
                $email = strtolower(trim($validated['customer']['email']));
                $firstName = $validated['customer']['first_name'] ?? null;
                $lastName = $validated['customer']['last_name'] ?? null;

                $customer = Customer::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'email' => $email],
                    [
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'phone'      => $validated['customer']['phone'] ?? null,
                        'primary_tenant_id' => $tenant->id,
                    ]
                );

                // Creare cont la comandă. Parola se pune direct doar pe un client creat chiar acum.
                // Dacă adresa avea deja comenzi (client existent, fără parolă), oricine i-ar putea
                // lua contul și biletele scriindu-i emailul la plată — așa că acolo trimitem un
                // link de setare a parolei pe adresa respectivă.
                $accountState = null;
                if (! empty($validated['create_account']) && ! empty($validated['password']) && empty($customer->password)) {
                    if ($customer->wasRecentlyCreated) {
                        $customer->update(['password' => Hash::make($validated['password'])]);
                        $accountState = 'created';
                    } else {
                        $accountState = 'link';
                    }
                }

                // Tipuri de bilete active pentru maparea locurilor
                $ticketTypes = TicketType::where('event_id', $event->id)->where('status', 'active')->get();
                $fallbackTtId = $ticketTypes->first()?->id;

                $priceToTt = function (int $priceCents) use ($ticketTypes, $fallbackTtId): ?int {
                    if ($ticketTypes->isEmpty()) {
                        return null;
                    }
                    // Alege tipul de bilet cu prețul cel mai apropiat de prețul locului
                    $best = $ticketTypes->sortBy(function ($tt) use ($priceCents) {
                        $ttPrice = (int) ($tt->price_cents ?: (($tt->price_max ?? 0) * 100));
                        return abs($ttPrice - $priceCents);
                    })->first();
                    return $best?->id ?? $fallbackTtId;
                };

                $totalCents = 0;
                $ticketRows = [];       // [ticket_type_id, price_cents, meta]
                $seatUids = [];
                $reservedCapacity = []; // [[capacity_id, qty], …] — leisure day/slot holds
                $eventSeatingId = $validated['event_seating_id'] ?? null;

                $beneficiaries = $validated['beneficiaries'] ?? [];
                if (! empty($seats)) {
                    foreach ($seats as $idx => $seat) {
                        $priceCents = (int) round(((float) ($seat['price'] ?? 0)) * 100);
                        $totalCents += $priceCents;
                        $seatUids[] = $seat['seat_uid'];
                        $ben = $beneficiaries[$idx] ?? null;
                        $ticketRows[] = [
                            'ticket_type_id' => $priceToTt($priceCents),
                            'price_cents'    => $priceCents,
                            'meta'           => array_filter([
                                'seat_uid'         => $seat['seat_uid'],
                                'seat_label'       => $seat['label'] ?? null,
                                'event_seating_id' => $eventSeatingId,
                                'beneficiary'      => ($ben && ! empty($ben['name'])) ? [
                                    'name'  => $ben['name'],
                                    'email' => $ben['email'] ?? null,
                                ] : null,
                            ], fn ($v) => $v !== null),
                        ];
                    }
                } else {
                    foreach ($items as $item) {
                        $tt = $ticketTypes->firstWhere('id', $item['ticket_type_id']) ?? TicketType::find($item['ticket_type_id']);
                        $qty = (int) $item['quantity'];

                        // Leisure: price through the resolver (weekday rules,
                        // seasons, duration variant) instead of the flat price,
                        // and hold the day/slot so the venue cannot oversell.
                        $isLeisure = ! empty($item['visit_date']) || ! empty($item['capacity_id']);
                        if ($isLeisure && $tt) {
                            $visitDate = CarbonImmutable::parse($item['visit_date'] ?? 'today');
                            $priceCents = app(LeisurePricingResolver::class)->resolvePrice(
                                $tt,
                                $visitDate,
                                isset($item['duration_minutes']) ? (int) $item['duration_minutes'] : null,
                            );
                            if (! empty($item['capacity_id'])) {
                                $ok = app(CapacityAvailabilityService::class)
                                    ->reserve((int) $item['capacity_id'], $qty);
                                if (! $ok) {
                                    throw new \RuntimeException('Intervalul ales nu mai are locuri disponibile.');
                                }
                                $reservedCapacity[] = [(int) $item['capacity_id'], $qty];
                            }
                        } else {
                            $priceCents = (int) ($tt?->price_cents ?: (($tt?->price_max ?? 0) * 100));
                        }

                        for ($i = 0; $i < $qty; $i++) {
                            $totalCents += $priceCents;
                            $ticketRows[] = [
                                'ticket_type_id' => $tt?->id ?? $fallbackTtId,
                                'price_cents'    => $priceCents,
                                'meta'           => array_filter([
                                    'visit_date'       => $item['visit_date'] ?? null,
                                    'slot_time'        => $item['slot_time'] ?? null,
                                    'duration_minutes' => $item['duration_minutes'] ?? null,
                                    'capacity_id'      => $item['capacity_id'] ?? null,
                                ], fn ($v) => $v !== null),
                            ];
                        }
                    }
                }

                // Cod de reducere: se aplică pe subtotalul biletelor, înainte de taxe.
                $subtotalCents = $totalCents;
                $coupon = null;
                $discountCents = 0;
                if (! empty($validated['coupon_code'])) {
                    $resolved = $this->resolveCoupon($tenant, $event, $validated['coupon_code'], $ticketRows);
                    if (! $resolved['valid']) {
                        throw new \RuntimeException($resolved['message']);
                    }
                    $coupon = $resolved['coupon'];
                    $discountCents = $resolved['discount_cents'];
                    $totalCents -= $discountCents;
                }

                // Taxa procesatorului de plăți, dacă tenantul a ales să o mute la cumpărător.
                $fee = $this->processingFee($tenant, $totalCents);
                if ($fee['fee_cents'] > 0) {
                    $totalCents += $fee['fee_cents'];
                }

                // Comision card cultural (dacă e activat pe tenant, via Netopia)
                $surchargeCents = 0;
                if (($validated['payment_method'] ?? '') === 'card_cultural') {
                    $pivot = $tenant->microservices()->where('slug', 'payment-netopia')->wherePivot('is_active', true)->first();
                    $settings = $pivot?->pivot?->settings ?? [];
                    if (is_string($settings)) {
                        $settings = json_decode($settings, true) ?: [];
                    }
                    if (! empty($settings['cultural_card_enabled'])) {
                        $pct = (float) ($settings['cultural_card_surcharge_percent'] ?? 4);
                        $surchargeCents = (int) round($totalCents * $pct / 100);
                        $totalCents += $surchargeCents;
                    }
                }

                $seatedItems = [];
                if ($eventSeatingId && ! empty($seatUids)) {
                    $seatedItems[] = ['event_seating_id' => (int) $eventSeatingId, 'seat_uids' => $seatUids];
                }

                // Câmpurile de reducere / taxă se scriu doar când se aplică, ca o comandă
                // obișnuită să rămână exact ca înainte.
                $extra = [];
                $extraMeta = [];
                if ($coupon) {
                    $extra['promo_code'] = $coupon->code;
                    $extra['promo_discount'] = $discountCents / 100;
                    $extraMeta['subtotal_cents'] = $subtotalCents;
                    $extraMeta['discount_cents'] = $discountCents;
                    $extraMeta['promo_code'] = [
                        'code'   => $coupon->code,
                        'type'   => $coupon->discount_type,
                        'value'  => (float) $coupon->discount_value,
                        'source' => 'coupon',
                        'id'     => $coupon->id,
                    ];
                }
                if ($fee['fee_cents'] > 0) {
                    $extra['processing_fee_cents'] = $fee['fee_cents'];
                    $extra['processing_fee_passed'] = true;
                    $extra['processing_fee_provider'] = $fee['provider'];
                    $extra['processing_fee_percent_rate'] = $fee['percent_rate'];
                    $extra['processing_fee_fixed_cents'] = $fee['fixed_cents'];
                    $extraMeta['processing_fee_cents'] = $fee['fee_cents'];
                }

                $order = Order::create($extra + [
                    'tenant_id'      => $tenant->id,
                    'customer_id'    => $customer->id,
                    'customer_email' => $email,
                    'total_cents'    => $totalCents,
                    'status'         => 'pending',
                    'meta'           => $extraMeta + [
                        'customer_name'  => trim(($firstName ?? '') . ' ' . ($lastName ?? '')),
                        'customer_first_name' => $firstName,
                        'customer_last_name'  => $lastName,
                        'customer_phone' => $validated['customer']['phone'] ?? null,
                        'event_id'       => $event->id,
                        'payment'        => 'demo',
                        'payment_method' => $validated['payment_method'] ?? 'card',
                        'surcharge_cents' => $surchargeCents,
                        'newsletter'     => (bool) ($validated['newsletter'] ?? false),
                        'seated_items'   => $seatedItems,
                        // Leisure holds, so a cancel/expire can hand the places
                        // back with CapacityAvailabilityService::release().
                        'leisure_capacity' => $reservedCapacity,
                    ],
                ]);

                foreach ($ticketRows as $row) {
                    Ticket::create([
                        'order_id'       => $order->id,
                        'ticket_type_id' => $row['ticket_type_id'],
                        'code'           => $this->generateTicketCode(),
                        'status'         => 'pending',
                        'meta'           => $row['meta'],
                    ]);
                }

                // Consumă o utilizare a codului (și îl epuizează la limită).
                if ($coupon) {
                    $coupon->incrementUsage();
                }

                return ['order' => $order, 'total_cents' => $totalCents, 'customer' => $customer, 'account' => $accountState];
            });

            $order = $result['order'];

            if (($result['account'] ?? null) === 'link') {
                DemoStorefrontController::sendPasswordLink($tenant, $result['customer'], $resolved['domain_id'] ?? null);
            }

            // Inițiază "plata" demo → URL pagină de plată
            $payment = PaymentProcessorFactory::makeFromArray('demo', [])->createPayment([
                'order_id'    => $order->id,
                'amount'      => $result['total_cents'] / 100,
                'currency'    => 'RON',
                'success_url' => $validated['success_url'] ?? '',
                'cancel_url'  => $validated['cancel_url'] ?? '',
            ]);

            return response()->json([
                'success'      => true,
                'order_id'     => $order->id,
                'total'        => $result['total_cents'] / 100,
                'redirect_url' => $payment['redirect_url'],
                // Cu el se descarcă biletele după plată (vezi DemoStorefrontController::ticketsPdf)
                'access_token' => DemoStorefrontController::orderToken($order),
                // 'created' = contul există de acum; 'link' = am trimis pe email linkul de setare a parolei
                'account'      => $result['account'] ?? null,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Calculul coșului pentru site-urile demo: subtotal, reducere din cod, taxa de
     * procesare (dacă tenantul o mută la cumpărător) și totalul de plată. Nu creează
     * nimic — coșul și pagina de plată îl cheamă ca să afișeze sumele reale.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id'               => 'required|integer',
            'items'                  => 'nullable|array',
            'items.*.ticket_type_id' => 'required_with:items|integer',
            'items.*.quantity'       => 'required_with:items|integer|min:1|max:50',
            // Locuri numerotate: prețul vine din hartă, la fel ca în store()
            'seats'                  => 'nullable|array|max:50',
            'seats.*.seat_uid'       => 'required_with:seats|string|max:64',
            'seats.*.price'          => 'nullable|numeric|min:0',
            'coupon_code'            => 'nullable|string|max:50',
        ]);

        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];

        $event = Event::where('tenant_id', $tenant->id)->find($validated['event_id']);
        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Event not found'], 404);
        }

        $ticketTypes = TicketType::where('event_id', $event->id)->where('status', 'active')->get()->keyBy('id');
        $rows = [];
        $subtotalCents = 0;
        if (empty($validated['items']) && empty($validated['seats'])) {
            return response()->json(['success' => false, 'error' => 'Coș gol'], 422);
        }
        foreach (($validated['seats'] ?? []) as $seat) {
            $priceCents = (int) round(((float) ($seat['price'] ?? 0)) * 100);
            // Același tip de bilet pe care îl va primi locul la comandă: cel cu prețul cel mai apropiat
            $tt = $ticketTypes->sortBy(fn ($t) => abs(((int) ($t->price_cents ?: (($t->price_max ?? 0) * 100))) - $priceCents))->first();
            $subtotalCents += $priceCents;
            $rows[] = ['ticket_type_id' => $tt?->id, 'price_cents' => $priceCents];
        }
        foreach (($validated['items'] ?? []) as $item) {
            $tt = $ticketTypes->get($item['ticket_type_id']);
            if (! $tt) {
                return response()->json(['success' => false, 'error' => 'Un tip de bilet din coș nu mai este disponibil.'], 422);
            }
            $priceCents = (int) ($tt->price_cents ?: (($tt->price_max ?? 0) * 100));
            for ($i = 0; $i < (int) $item['quantity']; $i++) {
                $subtotalCents += $priceCents;
                $rows[] = ['ticket_type_id' => $tt->id, 'price_cents' => $priceCents];
            }
        }

        $discountCents = 0;
        $couponData = null;
        $couponError = null;
        if (! empty($validated['coupon_code'])) {
            $res = $this->resolveCoupon($tenant, $event, $validated['coupon_code'], $rows);
            if ($res['valid']) {
                $discountCents = $res['discount_cents'];
                $couponData = [
                    'code'  => $res['coupon']->code,
                    'type'  => $res['coupon']->discount_type,
                    'value' => (float) $res['coupon']->discount_value,
                ];
            } else {
                $couponError = $res['message'];
            }
        }

        $fee = $this->processingFee($tenant, $subtotalCents - $discountCents);

        return response()->json([
            'success' => true,
            'data' => [
                'subtotal'       => $subtotalCents / 100,
                'discount'       => $discountCents / 100,
                'coupon'         => $couponData,
                'coupon_error'   => $couponError,
                'processing_fee' => $fee['fee_cents'] / 100,
                'fee'            => [
                    'pass_to_customer' => $fee['pass_to_customer'],
                    'percent_rate'     => $fee['percent_rate'],
                    'fixed'            => $fee['fixed_cents'] / 100,
                    'provider'         => $fee['provider'],
                ],
                'total'          => ($subtotalCents - $discountCents + $fee['fee_cents']) / 100,
                'currency'       => 'RON',
            ],
        ]);
    }

    /**
     * Validează un cod din coupon_codes pentru tenant + eveniment și calculează reducerea.
     *
     * @param  array<int, array{ticket_type_id: int|null, price_cents: int}>  $ticketRows
     * @return array{valid: bool, message: string, coupon: ?CouponCode, discount_cents: int}
     */
    private function resolveCoupon(Tenant $tenant, Event $event, string $code, array $ticketRows): array
    {
        $fail = fn (string $message) => ['valid' => false, 'message' => $message, 'coupon' => null, 'discount_cents' => 0];

        $code = trim($code);
        $coupon = $code !== '' ? CouponCode::where('tenant_id', $tenant->id)->byCode($code)->first() : null;
        if (! $coupon) {
            return $fail('Codul de reducere nu a fost găsit.');
        }
        if (! $coupon->isValid() || ! $coupon->isValidAtTime()) {
            return $fail('Codul de reducere nu mai este valabil.');
        }

        $asArray = function ($value): array {
            if (is_string($value)) {
                $value = json_decode($value, true);
            }

            return is_array($value) ? array_values(array_filter($value, fn ($v) => $v !== null && $v !== '')) : [];
        };

        $events = array_map('intval', $asArray($coupon->applicable_events));
        if ($events && ! in_array((int) $event->id, $events, true)) {
            return $fail('Codul de reducere nu se aplică acestei competiții.');
        }

        $subtotalCents = (int) array_sum(array_column($ticketRows, 'price_cents'));

        // Baza reducerii: doar biletele eligibile, dacă e restrâns pe tipuri de bilet.
        $types = array_map('intval', $asArray($coupon->applicable_ticket_types));
        $baseCents = $subtotalCents;
        if ($types) {
            $baseCents = 0;
            foreach ($ticketRows as $row) {
                if (in_array((int) $row['ticket_type_id'], $types, true)) {
                    $baseCents += (int) $row['price_cents'];
                }
            }
            if ($baseCents === 0) {
                return $fail('Codul de reducere nu se aplică biletelor alese.');
            }
        }

        if (! $coupon->isValidForAmount($subtotalCents / 100)) {
            return $fail('Comanda este sub valoarea minimă pentru acest cod.');
        }
        if ($coupon->min_quantity && count($ticketRows) < (int) $coupon->min_quantity) {
            return $fail('Codul se aplică de la ' . (int) $coupon->min_quantity . ' bilete în sus.');
        }

        $discountCents = (int) round($coupon->calculateDiscount($baseCents / 100) * 100);
        if ($discountCents <= 0) {
            return $fail('Codul de reducere nu se aplică acestei comenzi.');
        }

        return ['valid' => true, 'message' => '', 'coupon' => $coupon, 'discount_cents' => min($discountCents, $subtotalCents)];
    }

    /**
     * Taxa procesatorului de plăți mutată la cumpărător, din setările tenantului:
     * settings.payment_fees = {pass_to_customer, percent_rate, fixed_cents, provider}.
     * Lipsa setării sau pass_to_customer = false înseamnă taxă zero (comportamentul vechi).
     * Aceeași formulă ca la marketplace (ProcessingFeeCalculator): floor(bază × %) + fix.
     *
     * @return array{fee_cents: int, pass_to_customer: bool, percent_rate: float, fixed_cents: int, provider: ?string}
     */
    private function processingFee(Tenant $tenant, int $baseCents): array
    {
        $cfg = is_array($tenant->settings) ? ($tenant->settings['payment_fees'] ?? null) : null;
        $pass = is_array($cfg) && ! empty($cfg['pass_to_customer']);
        $rate = $pass ? max(0.0, (float) ($cfg['percent_rate'] ?? 0)) : 0.0;
        $fixed = $pass ? max(0, (int) ($cfg['fixed_cents'] ?? 0)) : 0;

        $feeCents = ($pass && $baseCents > 0) ? (int) floor($baseCents * $rate / 100) + $fixed : 0;

        return [
            'fee_cents'        => $feeCents,
            'pass_to_customer' => $pass,
            'percent_rate'     => $rate,
            'fixed_cents'      => $fixed,
            'provider'         => $pass ? ($cfg['provider'] ?? ($tenant->payment_processor ?: null)) : null,
        ];
    }

    private function generateTicketCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }
}
