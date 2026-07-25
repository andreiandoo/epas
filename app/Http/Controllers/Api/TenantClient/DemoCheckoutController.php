<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
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
            'beneficiaries'     => 'nullable|array',
            'beneficiaries.*.name'  => 'nullable|string|max:190',
            'beneficiaries.*.email' => 'nullable|email|max:190',
            'create_account'    => 'nullable|boolean',
            'password'          => 'nullable|string|min:8',
            'newsletter'        => 'nullable|boolean',
            'payment_method'    => 'nullable|string|max:40',
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

                // Creare cont automat: setează parola (dacă nu are deja una)
                if (! empty($validated['create_account']) && ! empty($validated['password']) && empty($customer->password)) {
                    $customer->update(['password' => Hash::make($validated['password'])]);
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
                        $priceCents = (int) ($tt?->price_cents ?: (($tt?->price_max ?? 0) * 100));
                        for ($i = 0; $i < $item['quantity']; $i++) {
                            $totalCents += $priceCents;
                            $ticketRows[] = [
                                'ticket_type_id' => $tt?->id ?? $fallbackTtId,
                                'price_cents'    => $priceCents,
                                'meta'           => [],
                            ];
                        }
                    }
                }

                $seatedItems = [];
                if ($eventSeatingId && ! empty($seatUids)) {
                    $seatedItems[] = ['event_seating_id' => (int) $eventSeatingId, 'seat_uids' => $seatUids];
                }

                $order = Order::create([
                    'tenant_id'      => $tenant->id,
                    'customer_id'    => $customer->id,
                    'customer_email' => $email,
                    'total_cents'    => $totalCents,
                    'status'         => 'pending',
                    'meta'           => [
                        'customer_name'  => trim(($firstName ?? '') . ' ' . ($lastName ?? '')),
                        'customer_first_name' => $firstName,
                        'customer_last_name'  => $lastName,
                        'customer_phone' => $validated['customer']['phone'] ?? null,
                        'event_id'       => $event->id,
                        'payment'        => 'demo',
                        'payment_method' => $validated['payment_method'] ?? 'card',
                        'newsletter'     => (bool) ($validated['newsletter'] ?? false),
                        'seated_items'   => $seatedItems,
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

                return ['order' => $order, 'total_cents' => $totalCents];
            });

            $order = $result['order'];

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
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function generateTicketCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }
}
