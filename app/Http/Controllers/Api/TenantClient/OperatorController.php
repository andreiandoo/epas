<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\ResourceRental;
use App\Models\Leisure\TenantCashierSession;
use App\Models\Leisure\TenantTeamMember;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Leisure\CapacityAvailabilityService;
use App\Services\Leisure\ChannelPricingResolver;
use App\Services\Leisure\LeisurePricingResolver;
use App\Services\Leisure\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Operator API for a leisure tenant's OWN site.
 *
 * The equivalent of Ambilet's /organizator/leisure-* panel, but tenant-scoped
 * and reachable from the tenant's storefront (parc.tixello.ro/operator) instead
 * of the platform's Filament panel on core. Same reason the storefront exists:
 * the venue's staff should never have to log into core.tixello.com.
 *
 * Auth: email + password against a User who has an ACTIVE TenantTeamMember,
 * exchanged for a Sanctum token (same pattern as the marketplace organizer
 * flow). The token is the operator's identity for every other call here; the
 * tenant is derived from it, never from a query parameter, so an operator can
 * only ever touch their own venue.
 *
 * Capability gating mirrors the panel: leisure_role decides who may sell, scan
 * or hand out equipment.
 */
class OperatorController extends Controller
{
    use ResolvesTenant;

    private const ROLES_POS     = ['pos_cashier', 'pos_manager', 'admin'];
    private const ROLES_CHECKIN = ['check_in', 'pos_manager', 'admin'];
    private const ROLES_RENTAL  = ['rental_operator', 'pos_manager', 'admin'];

    /* ================================================================ auth */

    /**
     * POST /tenant-client/operator/login   {email, password}
     * Scoped by ?tenant= or ?hostname= so an operator of venue A cannot sign in
     * on venue B's site with valid credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $v = $request->validate([
            'email' => 'required|email|max:190',
            'password' => 'required|string|max:190',
        ]);

        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return $this->fail('Tenant not found', 404);
        }
        $tenant = $resolved['tenant'];

        $user = User::where('email', mb_strtolower(trim($v['email'])))->first();
        if (! $user || ! Hash::check($v['password'], $user->password)) {
            return $this->fail('Date de autentificare incorecte.', 422);
        }

        $member = TenantTeamMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', TenantTeamMember::STATUS_ACTIVE)
            ->first();
        if (! $member) {
            return $this->fail('Nu ai drepturi de operator pentru această locație.', 403);
        }

        $token = $user->createToken('operator-' . $tenant->id)->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => ['token' => $token, 'operator' => $this->operatorPayload($user, $member, $tenant)],
        ]);
    }

    /** GET /tenant-client/operator/me */
    public function me(Request $request): JsonResponse
    {
        $ctx = $this->context($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $member, $user] = $ctx;

        return response()->json([
            'success' => true,
            'data' => $this->operatorPayload($user, $member, $tenant),
        ]);
    }

    /** POST /tenant-client/operator/logout */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user->currentAccessToken(), 'delete')) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['success' => true]);
    }

    /* ============================================================== cashier */

    /** GET /tenant-client/operator/cashier — the open shift, or null. */
    public function cashierCurrent(Request $request): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_POS);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant] = $ctx;

        $session = TenantCashierSession::currentFor($tenant->id);
        return response()->json(['success' => true, 'data' => $session ? $this->sessionPayload($session) : null]);
    }

    /** POST /tenant-client/operator/cashier/open  {label?, float?, notes?} */
    public function cashierOpen(Request $request): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_POS);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $member, $user] = $ctx;

        if ($open = TenantCashierSession::currentFor($tenant->id)) {
            return response()->json(['success' => true, 'data' => $this->sessionPayload($open),
                                     'message' => 'O casă este deja deschisă.']);
        }

        $v = $request->validate([
            'label' => 'nullable|string|max:128',
            'float' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $session = TenantCashierSession::create([
            'tenant_id' => $tenant->id,
            'event_id' => $this->umbrellaEvent($tenant)?->id,
            'team_member_id' => $member->id,
            'opened_at' => now(),
            'opened_label' => $v['label'] ?? $user->name,
            'opening_float_cents' => (int) round(((float) ($v['float'] ?? 0)) * 100),
            'opening_notes' => $v['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $this->sessionPayload($session)]);
    }

    /** POST /tenant-client/operator/cashier/close  {counted?, notes?} */
    public function cashierClose(Request $request): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_POS);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant] = $ctx;

        $session = TenantCashierSession::currentFor($tenant->id);
        if (! $session) {
            return $this->fail('Nu există nicio casă deschisă.', 409);
        }

        $v = $request->validate([
            'counted' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $totals = $this->sessionTotals($session);
        $countedCents = isset($v['counted']) ? (int) round(((float) $v['counted']) * 100) : null;

        $session->update([
            'closed_at' => now(),
            'closing_notes' => $v['notes'] ?? null,
            'closing_snapshot' => array_merge($totals, [
                'counted_cents' => $countedCents,
                'expected_cash_cents' => $totals['cash_cents'] + $session->opening_float_cents,
                'difference_cents' => $countedCents === null
                    ? null
                    : $countedCents - ($totals['cash_cents'] + $session->opening_float_cents),
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $this->sessionPayload($session->fresh())]);
    }

    /* ================================================================== POS */

    /**
     * GET /tenant-client/operator/catalog
     * What the till shows. Prices come from ChannelPricingResolver for the POS
     * channel, so counter prices can differ from online without the operator
     * doing arithmetic.
     */
    public function catalog(Request $request, ChannelPricingResolver $channels): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_POS);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant] = $ctx;

        $channel = (string) $request->query('channel', ChannelPricingResolver::CHANNEL_POS_FIXED);
        if (! $channels->isValidChannel($channel)) {
            $channel = ChannelPricingResolver::CHANNEL_POS_FIXED;
        }

        $types = TicketType::whereHas('event', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->where('status', 'active')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $items = $types->map(fn ($tt) => [
            'ticket_type_id' => $tt->id,
            'name' => $tt->name,
            'category' => $tt->service_category ?: 'access',
            'price_cents' => $channels->basePriceForChannel($tt, $channel),
            'currency' => $tt->currency ?? 'RON',
            'variants' => $tt->getDurationVariantsCollection()->map(fn ($v) => [
                'duration_minutes' => $v->duration_minutes !== null ? (int) $v->duration_minutes : null,
                'label' => $v->label,
            ])->values(),
        ])->values();

        return response()->json(['success' => true, 'data' => [
            'channel' => $channel,
            'channels' => ChannelPricingResolver::CHANNELS,
            'items' => $items,
        ]]);
    }

    /**
     * POST /tenant-client/operator/sale
     *   {items:[{ticket_type_id, qty, duration_minutes?, visit_date?}],
     *    payment_method: cash|card, customer?:{name,email,phone}}
     *
     * Creates a real paid Order plus its Tickets, holds and immediately
     * confirms the day's capacity, and stamps the open cashier shift.
     */
    public function sale(Request $request, LeisurePricingResolver $pricing, ChannelPricingResolver $channels): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_POS);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $member] = $ctx;

        $v = $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.ticket_type_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'items.*.duration_minutes' => 'nullable|integer|min:1',
            'items.*.visit_date' => 'nullable|date_format:Y-m-d',
            'payment_method' => 'required|in:cash,card',
            'channel' => 'nullable|string|max:32',
            'customer.name' => 'nullable|string|max:190',
            'customer.email' => 'nullable|email|max:190',
            'customer.phone' => 'nullable|string|max:50',
        ]);

        $channel = $channels->isValidChannel((string) ($v['channel'] ?? ''))
            ? $v['channel']
            : ChannelPricingResolver::CHANNEL_POS_FIXED;

        $session = TenantCashierSession::currentFor($tenant->id);
        if (! $session) {
            return $this->fail('Deschide casa înainte de a vinde.', 409);
        }

        // Only this tenant's ticket types, fetched once.
        $ids = collect($v['items'])->pluck('ticket_type_id')->unique()->all();
        $types = TicketType::whereIn('id', $ids)
            ->with('event:id,tenant_id')
            ->get()
            ->filter(fn ($tt) => $tt->event?->tenant_id === $tenant->id)
            ->keyBy('id');
        if ($types->count() !== count($ids)) {
            return $this->fail('Unul dintre produse nu aparține acestei locații.', 422);
        }

        try {
            $result = DB::transaction(function () use ($v, $tenant, $types, $pricing, $channels, $channel, $session, $member) {
                $capacity = app(CapacityAvailabilityService::class);
                $today = CarbonImmutable::today();

                $totalCents = 0;
                $rows = [];
                $eventId = null;

                foreach ($v['items'] as $item) {
                    $tt = $types[$item['ticket_type_id']];
                    $eventId = $eventId ?: $tt->event_id;
                    $qty = (int) $item['qty'];
                    $date = isset($item['visit_date']) ? CarbonImmutable::parse($item['visit_date']) : $today;

                    // Counter price: the channel base, then the leisure rules
                    // (weekday, season, duration) applied on top of it.
                    $base = $channels->basePriceForChannel($tt, $channel);
                    $resolved = $pricing->resolvePrice($tt, $date, $item['duration_minutes'] ?? null);
                    $unit = $base > 0 && $resolved > 0
                        ? (int) round($resolved * ($base / max(1, (int) ($tt->price_cents ?: $base))))
                        : ($resolved ?: $base);

                    // Hold + confirm the day's places in one step: the customer
                    // is standing at the counter, there is no pending phase.
                    $capacityRow = $this->capacityRowFor($tenant->id, $tt->id, $date);
                    if ($capacityRow) {
                        if (! $capacity->reserve($capacityRow->id, $qty)) {
                            throw new \RuntimeException("Nu mai sunt locuri pentru „{$tt->name}” în ziua aleasă.");
                        }
                        $capacity->confirm($capacityRow->id, $qty);
                    }

                    for ($i = 0; $i < $qty; $i++) {
                        $totalCents += $unit;
                        $rows[] = ['tt' => $tt, 'price_cents' => $unit, 'meta' => array_filter([
                            'visit_date' => $date->toDateString(),
                            'duration_minutes' => $item['duration_minutes'] ?? null,
                            'capacity_id' => $capacityRow?->id,
                            'channel' => $channel,
                            'pos' => true,
                        ], fn ($x) => $x !== null)];
                    }
                }

                $email = $v['customer']['email'] ?? null;
                $customer = $email
                    ? Customer::updateOrCreate(
                        ['tenant_id' => $tenant->id, 'email' => mb_strtolower(trim($email))],
                        ['first_name' => $v['customer']['name'] ?? null, 'phone' => $v['customer']['phone'] ?? null,
                         'primary_tenant_id' => $tenant->id]
                    )
                    : null;

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer?->id,
                    'customer_email' => $email,
                    'total_cents' => $totalCents,
                    'status' => 'paid',            // money changed hands at the counter
                    'source' => 'pos_tenant',
                    'meta' => [
                        'event_id' => $eventId,
                        'customer_name' => $v['customer']['name'] ?? null,
                        'customer_phone' => $v['customer']['phone'] ?? null,
                        'payment' => 'pos',
                        'payment_method' => $v['payment_method'],
                        'channel' => $channel,
                        'cashier_session_id' => $session->id,
                        'operator_team_member_id' => $member->id,
                    ],
                ]);

                foreach ($rows as $row) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'ticket_type_id' => $row['tt']->id,
                        'event_id' => $row['tt']->event_id,
                        'tenant_id' => $tenant->id,
                        'code' => $this->ticketCode(),
                        'status' => 'valid',       // paid at the counter, usable now
                        'price' => $row['price_cents'] / 100,
                        'meta' => $row['meta'],
                    ]);
                }

                // Sync quota_sold once per ticket_type. Counter sales don't
                // go through the atomic reservation loop that online checkout
                // uses (they go through per-day CapacityAvailabilityService
                // instead), so quota_sold would drift down under real active
                // tickets on every counter sale. Grouped so a POS sale of
                // multiple tickets on the same TT is a single UPDATE.
                $qtyByType = [];
                foreach ($rows as $row) {
                    $ttId = $row['tt']->id;
                    $qtyByType[$ttId] = ($qtyByType[$ttId] ?? 0) + 1;
                }
                foreach ($qtyByType as $ttId => $qty) {
                    \App\Models\TicketType::where('id', $ttId)->increment('quota_sold', $qty);
                }

                return $order;
            });
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $order = $result->fresh(['tickets']);

        return response()->json(['success' => true, 'data' => [
            'order_id' => $order->id,
            'total' => $order->total_cents / 100,
            'currency' => $order->currency ?? 'RON',
            'payment_method' => $v['payment_method'],
            'tickets' => $order->tickets->map(fn ($t) => [
                'code' => $t->code,
                'type' => $t->ticketType?->name,
                'price' => (float) $t->price,
            ])->values(),
        ]], 201);
    }

    /* ============================================================= check-in */

    /** POST /tenant-client/operator/scan  {code} */
    public function scan(Request $request): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_CHECKIN);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $member, $user] = $ctx;

        $v = $request->validate(['code' => 'required|string|max:64']);
        $code = trim($v['code']);

        // Scope through the ticket type's event: tickets bought online carry a
        // NULL tenant_id (DemoCheckoutController never sets it), so filtering on
        // the column alone would silently refuse every web-sold ticket.
        $ticket = Ticket::with('ticketType')
            ->whereHas('ticketType.event', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->where(fn ($q) => $q->where('code', $code)->orWhere('barcode', $code))
            ->first();

        if (! $ticket) {
            return response()->json(['success' => false, 'result' => 'not_found',
                                     'error' => 'Bilet inexistent.'], 404);
        }
        if ($ticket->status !== 'valid') {
            return response()->json(['success' => false, 'result' => 'invalid',
                                     'error' => 'Bilet ' . $ticket->status . '.',
                                     'data' => $this->ticketPayload($ticket)]);
        }
        if ($ticket->scanned_at) {
            return response()->json(['success' => false, 'result' => 'already_scanned',
                                     'error' => 'Bilet deja scanat la ' . $ticket->scanned_at->format('H:i'),
                                     'data' => $this->ticketPayload($ticket)]);
        }

        $ticket->forceFill(['scanned_at' => now(), 'scanned_by_user_id' => $user->id])->save();

        return response()->json(['success' => true, 'result' => 'ok',
                                 'data' => $this->ticketPayload($ticket->fresh())]);
    }

    /* ============================================================== rentals */

    /** GET /tenant-client/operator/rentals */
    public function rentals(Request $request): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_RENTAL);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant] = $ctx;

        $active = ResourceRental::where('tenant_id', $tenant->id)
            ->whereNull('ended_at')
            ->with('resource')
            ->latest('started_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'resource' => $r->resource?->name,
                'label' => $r->resource?->label,
                'started_at' => $r->started_at?->toIso8601String(),
                'planned_end_at' => $r->planned_end_at?->toIso8601String(),
                'overdue' => $r->planned_end_at ? now()->gt($r->planned_end_at) : false,
            ])->values();

        $available = PhysicalResource::where('tenant_id', $tenant->id)
            ->where('status', PhysicalResource::STATUS_AVAILABLE)
            ->orderBy('resource_type')->orderBy('name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'label' => $u->label,
                              'type' => $u->resource_type, 'qr' => $u->qr_code])
            ->values();

        return response()->json(['success' => true, 'data' => ['active' => $active, 'available' => $available]]);
    }

    /** POST /tenant-client/operator/rentals/start  {ticket_code, resource_id} */
    public function rentalStart(Request $request, RentalService $rentals): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_RENTAL);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $member, $user] = $ctx;

        $v = $request->validate([
            'ticket_code' => 'required|string|max:64',
            'resource_id' => 'required|integer',
        ]);

        $ticket = Ticket::whereHas('ticketType.event', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->where('code', trim($v['ticket_code']))->first();
        if (! $ticket) {
            return $this->fail('Bilet inexistent.', 404);
        }
        $resource = PhysicalResource::where('tenant_id', $tenant->id)->find($v['resource_id']);
        if (! $resource) {
            return $this->fail('Echipament inexistent.', 404);
        }

        try {
            $rental = $rentals->start($ticket, $resource, $user->id);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $rental->id,
            'resource' => $resource->name,
            'started_at' => $rental->started_at?->toIso8601String(),
            'planned_end_at' => $rental->planned_end_at?->toIso8601String(),
        ]], 201);
    }

    /** POST /tenant-client/operator/rentals/{rental}/end */
    public function rentalEnd(Request $request, int $rental, RentalService $rentals): JsonResponse
    {
        $ctx = $this->context($request, self::ROLES_RENTAL);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $member, $user] = $ctx;

        $row = ResourceRental::where('tenant_id', $tenant->id)->find($rental);
        if (! $row) {
            return $this->fail('Închiriere inexistentă.', 404);
        }

        try {
            $ended = $rentals->end($row, $user->id);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $ended->id,
            'ended_at' => $ended->ended_at?->toIso8601String(),
            'overtime_minutes' => $ended->overtime_minutes ?? 0,
            'surcharge' => ($ended->overtime_surcharge_cents ?? 0) / 100,
        ]]);
    }

    /* ============================================================ dashboard */

    /** GET /tenant-client/operator/dashboard — today at a glance. */
    public function dashboard(Request $request): JsonResponse
    {
        $ctx = $this->context($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant] = $ctx;

        $today = CarbonImmutable::today();
        $orders = Order::where('tenant_id', $tenant->id)
            ->whereDate('created_at', $today->toDateString())
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->get(['id', 'total_cents', 'meta', 'source']);

        $pos = $orders->filter(fn ($o) => ($o->meta['payment'] ?? null) === 'pos');

        $session = TenantCashierSession::currentFor($tenant->id);

        return response()->json(['success' => true, 'data' => [
            'date' => $today->toDateString(),
            'orders_today' => $orders->count(),
            'revenue_today' => $orders->sum('total_cents') / 100,
            'pos_orders_today' => $pos->count(),
            'pos_revenue_today' => $pos->sum('total_cents') / 100,
            'tickets_today' => Ticket::whereIn('order_id', $orders->pluck('id'))->count(),
            'scanned_today' => Ticket::whereHas('ticketType.event', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->whereDate('scanned_at', $today->toDateString())->count(),
            'rentals_active' => ResourceRental::where('tenant_id', $tenant->id)->whereNull('ended_at')->count(),
            'cashier_open' => (bool) $session,
            'cashier' => $session ? $this->sessionPayload($session) : null,
        ]]);
    }

    /* ============================================================== helpers */

    /**
     * Resolve (tenant, team member, user) from the bearer token, optionally
     * requiring one of $roles. The tenant comes from the membership, never from
     * the request, so a token cannot be pointed at another venue.
     *
     * @return array{0:Tenant,1:TenantTeamMember,2:User}|JsonResponse
     */
    private function context(Request $request, array $roles = [])
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->fail('Unauthenticated', 401);
        }

        $member = TenantTeamMember::where('user_id', $user->id)
            ->where('status', TenantTeamMember::STATUS_ACTIVE)
            ->latest('id')
            ->first();
        if (! $member) {
            return $this->fail('Nu ai drepturi de operator.', 403);
        }

        $tenant = Tenant::find($member->tenant_id);
        if (! $tenant) {
            return $this->fail('Tenant not found', 404);
        }

        if ($roles && ! in_array($member->leisure_role, $roles, true)) {
            return $this->fail('Rolul tău nu permite această acțiune.', 403);
        }

        return [$tenant, $member, $user];
    }

    private function operatorPayload(User $user, TenantTeamMember $member, Tenant $tenant): array
    {
        $role = (string) $member->leisure_role;
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'role_label' => TenantTeamMember::LEISURE_ROLES[$role] ?? $role,
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->public_name ?? $tenant->name],
            'can' => [
                'pos' => in_array($role, self::ROLES_POS, true),
                'checkin' => in_array($role, self::ROLES_CHECKIN, true),
                'rentals' => in_array($role, self::ROLES_RENTAL, true),
            ],
        ];
    }

    private function sessionPayload(TenantCashierSession $s): array
    {
        return [
            'id' => $s->id,
            'label' => $s->opened_label,
            'opened_at' => $s->opened_at?->toIso8601String(),
            'closed_at' => $s->closed_at?->toIso8601String(),
            'is_open' => $s->isOpen(),
            'opening_float' => $s->opening_float_cents / 100,
            'totals' => $s->isOpen() ? $this->sessionTotals($s) : ($s->closing_snapshot ?? []),
        ];
    }

    /** Money taken during a shift, split by method — the basis of the Z report. */
    private function sessionTotals(TenantCashierSession $s): array
    {
        $orders = Order::where('tenant_id', $s->tenant_id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('created_at', '>=', $s->opened_at)
            ->when($s->closed_at, fn ($q) => $q->where('created_at', '<=', $s->closed_at))
            ->get(['total_cents', 'meta']);

        $mine = $orders->filter(fn ($o) => (int) ($o->meta['cashier_session_id'] ?? 0) === $s->id);

        $by = fn (string $m) => $mine->filter(fn ($o) => ($o->meta['payment_method'] ?? null) === $m)
            ->sum('total_cents');

        return [
            'orders' => $mine->count(),
            'total_cents' => (int) $mine->sum('total_cents'),
            'cash_cents' => (int) $by('cash'),
            'card_cents' => (int) $by('card'),
        ];
    }

    private function ticketPayload(Ticket $t): array
    {
        return [
            'code' => $t->code,
            'type' => $t->ticketType?->name,
            'status' => $t->status,
            'scanned_at' => $t->scanned_at?->format('H:i:s'),
        ];
    }

    /** The capacity row governing a ticket type on a date, if the venue keeps one. */
    private function capacityRowFor(int $tenantId, int $ticketTypeId, CarbonImmutable $date)
    {
        return \App\Models\Leisure\TicketTypeCapacity::where('tenant_id', $tenantId)
            ->where('ticket_type_id', $ticketTypeId)
            ->whereDate('capacity_date', $date->toDateString())
            ->whereNull('time_slot_start')
            ->first();
    }

    private function umbrellaEvent(Tenant $tenant): ?Event
    {
        return Event::where('tenant_id', $tenant->id)->orderBy('id')->first();
    }

    private function ticketCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Ticket::where('code', $code)->exists());
        return $code;
    }

    private function fail(string $error, int $code): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $error], $code);
    }
}
