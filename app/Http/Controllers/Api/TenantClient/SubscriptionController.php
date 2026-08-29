<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seating\EventSeat;
use App\Models\Seating\EventSeatingLayout;
use App\Models\TenantCustomerSubscription;
use App\Models\TenantSubscriptionPlan;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Achiziția + gestionarea abonamentelor de client (Faza 3).
 * Necesită cont (bearer token) — abonamentele sunt strict pentru clienți logați.
 */
class SubscriptionController extends Controller
{
    use ResolvesTenant;
    use ResolvesCustomer;

    /**
     * Cumpără un abonament: alege plan + locuri, plătește prin gateway demo.
     */
    public function store(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];

        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Trebuie să fii autentificat pentru a cumpăra un abonament.'], 401);
        }

        $validated = $request->validate([
            'plan_slug'        => 'required|string',
            'seat_uids'        => 'nullable|array',
            'seat_uids.*'      => 'string|max:64',
            'seat_labels'      => 'nullable|array',
            'event_seating_id' => 'nullable|integer',
            'success_url'      => 'nullable|url',
            'cancel_url'       => 'nullable|url',
        ]);

        $plan = TenantSubscriptionPlan::where('tenant_id', $tenant->id)
            ->where('slug', $validated['plan_slug'])
            ->where('is_active', true)
            ->first();
        if (! $plan) {
            return response()->json(['success' => false, 'error' => 'Abonament inexistent.'], 404);
        }

        $seatUids = $validated['seat_uids'] ?? [];
        $seatLabels = $validated['seat_labels'] ?? [];
        $need = max(1, (int) ($plan->tickets_included ?: 1));
        if (count($seatUids) !== $need) {
            return response()->json(['success' => false, 'error' => "Trebuie să alegi exact {$need} loc(uri) pentru acest abonament."], 422);
        }

        // Valabilitate
        [$validFrom, $validUntil] = $this->resolveValidity($plan);

        try {
            $result = DB::transaction(function () use ($tenant, $customer, $plan, $seatUids, $seatLabels, $validated, $validFrom, $validUntil) {
                $order = Order::create([
                    'tenant_id'      => $tenant->id,
                    'customer_id'    => $customer->id,
                    'customer_email' => $customer->email,
                    'total_cents'    => $plan->price_cents ?? 0,
                    'status'         => 'pending',
                    'meta'           => [
                        'customer_name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                        'payment'       => 'demo',
                        'payment_method' => 'card',
                        'kind'          => 'subscription',
                        'plan_slug'     => $plan->slug,
                    ],
                ]);

                $sub = TenantCustomerSubscription::create([
                    'tenant_id'            => $tenant->id,
                    'customer_id'          => $customer->id,
                    'subscription_plan_id' => $plan->id,
                    'order_id'             => $order->id,
                    'name'                 => $plan->name,
                    'status'               => 'pending',
                    'shows_included'       => $plan->shows_included,
                    'shows_used'           => 0,
                    'tickets_included'     => $plan->tickets_included ?: 1,
                    'seat_uids'            => array_values($seatUids),
                    'seat_labels'          => array_values($seatLabels),
                    'event_seating_id'     => $validated['event_seating_id'] ?? null,
                    'price_cents'          => $plan->price_cents ?? 0,
                    'currency'             => $plan->currency ?: 'RON',
                    'valid_from'           => $validFrom,
                    'valid_until'          => $validUntil,
                    'priority_access'      => (bool) $plan->priority_access,
                    'benefits'             => array_values($plan->benefits ?? []),
                ]);

                $order->update(['meta' => array_merge($order->meta ?? [], ['subscription_id' => $sub->id])]);

                return ['order' => $order, 'sub' => $sub];
            });

            $payment = PaymentProcessorFactory::makeFromArray('demo', [])->createPayment([
                'order_id'    => $result['order']->id,
                'amount'      => ($plan->price_cents ?? 0) / 100,
                'currency'    => $plan->currency ?: 'RON',
                'success_url' => $validated['success_url'] ?? '',
                'cancel_url'  => $validated['cancel_url'] ?? '',
            ]);

            return response()->json([
                'success'         => true,
                'subscription_id' => $result['sub']->id,
                'order_id'        => $result['order']->id,
                'redirect_url'    => $payment['redirect_url'],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Abonamentele clientului autentificat (pentru pagina de cont).
     */
    public function mine(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $subs = TenantCustomerSubscription::where('tenant_id', $resolved['tenant']->id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $subs->map(fn ($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'status'           => $s->status,
                'shows_included'   => $s->shows_included,
                'shows_used'       => $s->shows_used,
                'shows_remaining'  => $s->shows_remaining,
                'tickets_included' => $s->tickets_included,
                'seat_labels'      => array_values($s->seat_labels ?? []),
                'valid_from'       => $s->valid_from?->toDateString(),
                'valid_until'      => $s->valid_until?->toDateString(),
                'priority_access'  => (bool) $s->priority_access,
                'benefits'         => array_values($s->benefits ?? []),
                'redeemed_events'  => array_values($s->redeemed_events ?? []),
            ])->values(),
        ]);
    }

    /**
     * Consumă un spectacol din abonament: rezervă locul abonatului în acel
     * eveniment + emite biletele + incrementează consumul.
     */
    public function redeem(Request $request, int $id): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate(['event_id' => 'required|integer']);

        $sub = TenantCustomerSubscription::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->first();
        if (! $sub) {
            return response()->json(['success' => false, 'error' => 'Abonament inexistent.'], 404);
        }
        if (! $sub->isValid()) {
            return response()->json(['success' => false, 'error' => 'Abonamentul nu este activ.'], 422);
        }
        if ($sub->shows_included !== null && ($sub->shows_used ?? 0) >= $sub->shows_included) {
            return response()->json(['success' => false, 'error' => 'Ai folosit toate spectacolele incluse.'], 422);
        }

        $event = Event::where('tenant_id', $tenant->id)->find($validated['event_id']);
        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Spectacol inexistent.'], 404);
        }

        $already = collect($sub->redeemed_events ?? [])->pluck('event_id')->contains($event->id);
        if ($already) {
            return response()->json(['success' => false, 'error' => 'Ai folosit deja abonamentul la acest spectacol.'], 422);
        }

        try {
            DB::transaction(function () use ($tenant, $customer, $sub, $event) {
                $layout = EventSeatingLayout::where('event_id', $event->id)->published()->latest('published_at')->first();
                $seatUids = array_values($sub->seat_uids ?? []);

                $ticketTypes = TicketType::where('event_id', $event->id)->where('status', 'active')->get();
                $fallbackTtId = $ticketTypes->first()?->id;

                // Rezervă locurile abonatului în snapshot-ul evenimentului
                if ($layout && ! empty($seatUids)) {
                    EventSeat::where('event_seating_id', $layout->id)
                        ->whereIn('seat_uid', $seatUids)
                        ->whereIn('status', ['available', 'held'])
                        ->update(['status' => 'sold', 'version' => DB::raw('version + 1'), 'last_change_at' => now()]);
                }

                // Emite biletele (valabile) pentru locurile abonatului
                $labels = $sub->seat_labels ?? [];
                $qtyByType = [];
                foreach ($seatUids as $i => $uid) {
                    $seatRow = $layout ? EventSeat::where('event_seating_id', $layout->id)->where('seat_uid', $uid)->first() : null;
                    $priceCents = $seatRow?->price_cents_override ?? 0;
                    $ttId = $fallbackTtId;
                    if ($priceCents > 0 && $ticketTypes->isNotEmpty()) {
                        $ttId = $ticketTypes->sortBy(fn ($tt) => abs((int) ($tt->price_cents ?: 0) - $priceCents))->first()?->id ?? $fallbackTtId;
                    }
                    Ticket::create([
                        'ticket_type_id' => $ttId,
                        'code'           => $this->ticketCode(),
                        'status'         => 'valid',
                        'meta'           => array_filter([
                            'source'           => 'subscription',
                            'subscription_id'  => $sub->id,
                            'event_id'         => $event->id,
                            'event_seating_id' => $layout?->id,
                            'seat_uid'         => $uid,
                            'seat_label'       => $labels[$i] ?? null,
                            'customer_id'      => $customer->id,
                        ], fn ($v) => $v !== null),
                    ]);
                    if ($ttId) {
                        $qtyByType[$ttId] = ($qtyByType[$ttId] ?? 0) + 1;
                    }
                }

                // Sync quota_sold per ticket_type. Subscription redemption
                // issues real 'valid' tickets on the target event's TTs,
                // but never went through the checkout increment path — so
                // every redemption used to under-count quota_sold by the
                // seat count. Grouped so a multi-seat subscription is a
                // single UPDATE per TT.
                foreach ($qtyByType as $ttId => $qty) {
                    TicketType::where('id', $ttId)->increment('quota_sold', $qty);
                }

                // Consum
                $redeemed = $sub->redeemed_events ?? [];
                $redeemed[] = [
                    'event_id'    => $event->id,
                    'title'       => $event->getTranslation('title', 'ro'),
                    'date'        => $event->event_date?->toDateString(),
                    'redeemed_at' => now()->toIso8601String(),
                ];
                $sub->update([
                    'shows_used'      => ($sub->shows_used ?? 0) + 1,
                    'redeemed_events' => $redeemed,
                ]);
            });

            $sub->refresh();
            return response()->json([
                'success'         => true,
                'shows_used'      => $sub->shows_used,
                'shows_remaining' => $sub->shows_remaining,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function resolveValidity(TenantSubscriptionPlan $plan): array
    {
        if ($plan->validity_mode === 'date_range' && $plan->valid_from && $plan->valid_until) {
            return [$plan->valid_from, $plan->valid_until];
        }
        // Stagiune: dacă planul are date, folosește-le; altfel 12 luni de la azi.
        $from = $plan->valid_from ?: now()->startOfDay();
        $until = $plan->valid_until ?: now()->addYear()->endOfDay();
        return [$from, $until];
    }

    private function ticketCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }
}
