<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\TenantCustomerSubscription;
use App\Models\Ticket;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Contul clientului tenant (bilete, comenzi, statistici, profil) — echivalentul
 * endpoint-urilor /customer/* din marketplace, adaptate la scop tenant +
 * autentificare prin CustomerToken (bearer).
 */
class CustomerAccountController extends Controller
{
    use ResolvesTenant;
    use ResolvesCustomer;

    private array $paidStatuses = ['paid', 'confirmed', 'completed'];

    /** Rezolvă (tenant, customer) sau întoarce un răspuns de eroare. */
    private function ctx(Request $request): array|JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }
        return [$resolved['tenant'], $customer];
    }

    /** Rezumat pentru panoul principal. */
    public function stats(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $paidOrders = Order::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->whereIn('status', $this->paidStatuses)->get(['id', 'total_cents']);

        $ticketsCount = Ticket::whereIn('order_id', $paidOrders->pluck('id'))->where('status', 'valid')->count();
        $subs = TenantCustomerSubscription::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->where('status', 'active')->count();

        $points = 0;
        try {
            $gami = app(GamificationService::class);
            if ($gami->isEnabled($tenant->id)) {
                $points = (int) ($gami->getCustomerPoints($tenant->id, $customer->id)->current_balance ?? 0);
            }
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'data' => [
            'upcoming_tickets' => $ticketsCount,
            'active_subscriptions' => $subs,
            'favorites' => 0,
            'points' => $points,
            'total_spent' => $paidOrders->sum('total_cents') / 100,
            'orders_count' => Order::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->count(),
        ]]);
    }

    /** Comenzile clientului. */
    public function orders(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $orders = Order::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->withCount('tickets')->latest()->get();

        $eventIds = $orders->pluck('meta.event_id')->filter()->unique()->all();
        $events = Event::whereIn('id', $eventIds)->get()->keyBy('id');

        $data = $orders->map(fn ($o) => $this->formatOrder($o, $events));

        $paid = $orders->whereIn('status', $this->paidStatuses);

        return response()->json([
            'success' => true,
            'data' => $data->values(),
            'stats' => [
                'total_orders' => $orders->count(),
                'total_spent'  => $paid->sum('total_cents') / 100,
                'saved'        => 0,
            ],
        ]);
    }

    /** Detaliul unei comenzi. */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $order = Order::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->withCount('tickets')->find($orderId);
        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Order not found'], 404);
        }

        $events = collect();
        if (! empty($order->meta['event_id'])) {
            $ev = Event::find($order->meta['event_id']);
            if ($ev) { $events = collect([$ev->id => $ev]); }
        }

        $tickets = $order->tickets()->with('ticketType')->get()->map(fn ($t) => [
            'code' => $t->code,
            'type' => $t->ticketType?->name,
            'seat_label' => $t->meta['seat_label'] ?? null,
            'status' => $t->status,
        ])->values();

        return response()->json(['success' => true, 'data' => array_merge(
            $this->formatOrder($order, $events),
            ['tickets' => $tickets]
        )]);
    }

    /** Biletele clientului (din comenzi + abonamente). */
    public function tickets(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $orderIds = Order::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->whereIn('status', $this->paidStatuses)->pluck('id', 'id');
        $orders = Order::whereIn('id', $orderIds)->get()->keyBy('id');

        $tickets = Ticket::with('ticketType')
            ->where(function ($q) use ($orderIds, $customer) {
                $q->whereIn('order_id', $orderIds)
                    ->orWhere('meta->customer_id', $customer->id);
            })
            ->where('status', 'valid')
            ->get();

        // Evenimente (din meta comandă sau meta bilet)
        $eventIds = collect();
        foreach ($tickets as $t) {
            $eid = $t->meta['event_id'] ?? ($orders[$t->order_id]->meta['event_id'] ?? null);
            if ($eid) { $eventIds->push($eid); }
        }
        $events = Event::with('venue')->whereIn('id', $eventIds->unique()->all())->get()->keyBy('id');

        $today = now()->toDateString();
        $out = $tickets->map(function ($t) use ($orders, $events, $today) {
            $eid = $t->meta['event_id'] ?? ($orders[$t->order_id]->meta['event_id'] ?? null);
            $ev = $eid ? ($events[$eid] ?? null) : null;
            $date = $ev?->event_date?->toDateString();
            return [
                'code'       => $t->code,
                'type'       => $t->ticketType?->name,
                'seat_label' => $t->meta['seat_label'] ?? null,
                'event_id'   => $eid,
                'event'      => $ev?->getTranslation('title', 'ro'),
                'venue'      => $ev?->venue?->getTranslation('name', 'ro'),
                'date'       => $ev?->event_date?->toIso8601String(),
                'time'       => $ev?->start_time,
                'is_upcoming' => $date ? ($date >= $today) : true,
                'order_id'   => $t->order_id,
                'is_subscription' => ($t->meta['source'] ?? null) === 'subscription',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming' => $out->where('is_upcoming', true)->values(),
                'past'     => $out->where('is_upcoming', false)->values(),
            ],
        ]);
    }

    /** Actualizare profil (nume, telefon). */
    public function updateProfile(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:190',
            'last_name'  => 'nullable|string|max:190',
            'phone'      => 'nullable|string|max:50',
        ]);
        $customer->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'data' => [
            'name' => $customer->full_name, 'email' => $customer->email, 'phone' => $customer->phone,
        ]]);
    }

    /** Schimbare parolă. */
    public function changePassword(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);
        if (! $customer->password || ! Hash::check($validated['current_password'], $customer->password)) {
            return response()->json(['success' => false, 'error' => 'Parola curentă este incorectă.'], 422);
        }
        $customer->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['success' => true]);
    }

    /** Ștergere cont (dezactivare — marchează + anonimizează la nevoie). */
    public function deleteAccount(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $validated = $request->validate(['password' => 'required|string']);
        if (! $customer->password || ! Hash::check($validated['password'], $customer->password)) {
            return response()->json(['success' => false, 'error' => 'Parolă incorectă.'], 422);
        }
        // Nu ștergem definitiv (comenzi/obligații) — marcăm cererea în meta.
        $customer->update(['meta' => array_merge($customer->meta ?? [], [
            'deletion_requested_at' => now()->toIso8601String(),
        ])]);

        return response()->json(['success' => true]);
    }

    private function formatOrder(Order $order, $events): array
    {
        $meta = $order->meta ?? [];
        $ev = ! empty($meta['event_id']) ? ($events[$meta['event_id']] ?? null) : null;

        return [
            'id'            => $order->id,
            'order_number'  => $order->order_number ?: ('TC-' . $order->id),
            'status'        => $order->status,
            'is_paid'       => in_array($order->status, $this->paidStatuses),
            'created_at'    => $order->created_at?->toIso8601String(),
            'total'         => ($order->total_cents ?? 0) / 100,
            'currency'      => $order->currency ?? 'RON',
            'tickets_count' => $order->tickets_count ?? 0,
            'payment_method' => ($meta['payment_method'] ?? 'card') === 'card_cultural' ? 'Card cultural' : 'Card',
            'kind'          => $meta['kind'] ?? 'tickets',
            'event'         => $ev?->getTranslation('title', 'ro'),
            'event_date'    => $ev?->event_date?->toIso8601String(),
        ];
    }
}
