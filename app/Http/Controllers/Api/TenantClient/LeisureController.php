<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\PhysicalResourceType;
use App\Models\TicketType;
use App\Services\Leisure\CapacityAvailabilityService;
use App\Services\Leisure\LeisurePricingResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public leisure surface for a tenant storefront.
 *
 * The leisure operator side (capacity, resources, rentals, POS) has existed
 * since E0-E3, but none of it was reachable from a tenant's own website: the
 * only public endpoints were /api/leisure/... keyed by tenant SLUG, and the
 * marketplace /activities family, which is Ambilet-shaped and not tenant-scoped.
 *
 * These endpoints mirror the rest of /tenant-client: scoped with ?tenant=ID
 * (or ?hostname=), read-only, cacheable, and shaped so the storefront kit can
 * consume them without a per-site adapter.
 *
 * All of them 404 unless the tenant is actually tenant_type=leisure, so nothing
 * changes for existing tenants.
 */
class LeisureController extends Controller
{
    use ResolvesTenant;

    /**
     * Resolve the tenant and assert it is a leisure venue.
     * Returns the Tenant, or a JsonResponse to send back as-is.
     */
    private function leisureTenant(Request $request)
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];
        $type = $tenant->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value
            : (string) $tenant->tenant_type;

        if ($type !== 'leisure') {
            return response()->json(['success' => false, 'error' => 'Tenant is not a leisure venue'], 404);
        }
        return $tenant;
    }

    /**
     * GET /tenant-client/leisure/bookables
     *
     * The catalogue a leisure storefront lists: every bookable ticket type of
     * the tenant's published events, with its duration variants and today's
     * resolved price. `service_category` separates access / rental / activity
     * so a template can render three different sections from one call.
     */
    public function bookables(Request $request, LeisurePricingResolver $pricing): JsonResponse
    {
        $tenant = $this->leisureTenant($request);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $category = $request->query('category');           // access | rental | activity
        $today = CarbonImmutable::today();

        $events = Event::where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->where('is_cancelled', false)->orWhereNull('is_cancelled'))
            ->with(['venue:id,name,city', 'ticketTypes' => fn ($q) => $q->where('status', 'active')])
            ->get();

        $items = [];
        foreach ($events as $event) {
            foreach ($event->ticketTypes as $tt) {
                $cat = $tt->service_category ?: 'access';
                if ($category && $cat !== $category) {
                    continue;
                }
                $items[] = $this->formatBookable($tt, $event, $pricing, $today);
            }
        }

        return response()->json(['success' => true, 'data' => ['bookables' => $items]])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * GET /tenant-client/leisure/availability?ticket_type=ID&month=YYYY-MM
     *
     * Month grid for the date picker. Without ticket_type it aggregates every
     * capacity row of the tenant for that month (the "is the venue open" view).
     * Shape: data.dates = { 'YYYY-MM-DD': {status, remaining, min_price_cents, slot_count} }
     */
    public function availability(Request $request, CapacityAvailabilityService $service): JsonResponse
    {
        $tenant = $this->leisureTenant($request);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $month = (string) $request->query('month', now()->format('Y-m'));
        try {
            $monthStart = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return response()->json(['success' => false, 'error' => 'Invalid month. Expected YYYY-MM.'], 422);
        }

        $ticketTypeId = $this->ticketTypeIdFor($request, $tenant->id);
        if ($ticketTypeId instanceof JsonResponse) {
            return $ticketTypeId;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'month'          => $monthStart->format('Y-m'),
                'ticket_type_id' => $ticketTypeId,
                'dates'          => $service->getAvailabilityForMonth($tenant->id, $monthStart, $ticketTypeId),
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * GET /tenant-client/leisure/slots?ticket_type=ID&date=YYYY-MM-DD
     *
     * Time slots inside one day. Shape:
     * data.slots = [{id, time_slot_start, time_slot_end, status, remaining, price_cents}]
     */
    public function slots(Request $request, CapacityAvailabilityService $service): JsonResponse
    {
        $tenant = $this->leisureTenant($request);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $ticketTypeId = $this->ticketTypeIdFor($request, $tenant->id);
        if ($ticketTypeId instanceof JsonResponse) {
            return $ticketTypeId;
        }
        if (! $ticketTypeId) {
            return response()->json(['success' => false, 'error' => 'ticket_type is required'], 422);
        }

        $date = (string) $request->query('date', now()->toDateString());
        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'error' => 'Invalid date. Expected YYYY-MM-DD.'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date'           => $day->toDateString(),
                'ticket_type_id' => $ticketTypeId,
                'slots'          => $service->getSlotsForDate($tenant->id, $ticketTypeId, $day),
            ],
        ])->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * GET /tenant-client/leisure/rentals
     *
     * Rental catalogue: the tenant's physical resource types (Kayak, MTB, …)
     * with live availability and the duration variants of every ticket type
     * they are linked to. This is the one leisure surface that had no HTTP
     * exposure at all — RentalService was reachable only from Filament.
     */
    public function rentals(Request $request, LeisurePricingResolver $pricing): JsonResponse
    {
        $tenant = $this->leisureTenant($request);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $today = CarbonImmutable::today();

        $types = PhysicalResourceType::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // A venue can own units without ever defining a type row (the units
        // carry a free-text `resource_type` instead). Synthesize the catalogue
        // from those so the storefront is not empty for such a tenant.
        if ($types->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['rentals' => $this->rentalsFromUnits($tenant->id, $pricing, $today)],
            ])->header('Cache-Control', 'public, max-age=60');
        }

        // Collect every linked ticket type once, then hand them out per resource.
        $linkedIds = $types->flatMap(fn ($t) => (array) ($t->linked_ticket_type_ids ?? []))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $ticketTypes = $linkedIds
            ? TicketType::whereIn('id', $linkedIds)
                ->where('status', 'active')
                ->with('event:id,tenant_id,slug')
                ->get()
                ->filter(fn ($tt) => $tt->event?->tenant_id === $tenant->id)
                ->keyBy('id')
            : collect();

        $data = $types->map(function ($type) use ($ticketTypes, $pricing, $today) {
            $options = collect((array) ($type->linked_ticket_type_ids ?? []))
                ->map(fn ($id) => $ticketTypes->get((int) $id))
                ->filter()
                ->map(fn ($tt) => $this->formatBookable($tt, $tt->event, $pricing, $today))
                ->values()->all();

            return [
                'id'          => $type->id,
                'slug'        => $type->slug,
                'name'        => $type->name,
                'description' => $type->description,
                'icon'        => $type->icon,
                'image_url'   => $this->publicUrl($type->image_url),
                'available'   => $type->availableCount(),
                'total'       => $type->totalCount(),
                'options'     => $options,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => ['rentals' => $data]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Fallback catalogue for venues that track units but no type rows: group
     * PhysicalResource by its free-text `resource_type`, count what is
     * available, and attach whatever ticket types those units are linked to.
     */
    private function rentalsFromUnits(int $tenantId, LeisurePricingResolver $pricing, CarbonImmutable $today): array
    {
        $units = PhysicalResource::where('tenant_id', $tenantId)
            ->whereIn('status', [PhysicalResource::STATUS_AVAILABLE, PhysicalResource::STATUS_IN_USE])
            ->get();
        if ($units->isEmpty()) {
            return [];
        }

        $linkedIds = $units->flatMap(fn ($u) => (array) ($u->linked_ticket_type_ids ?? []))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ticketTypes = $linkedIds
            ? TicketType::whereIn('id', $linkedIds)->where('status', 'active')
                ->with('event:id,tenant_id,slug')->get()
                ->filter(fn ($tt) => $tt->event?->tenant_id === $tenantId)->keyBy('id')
            : collect();

        $out = [];
        foreach ($units->groupBy('resource_type') as $type => $group) {
            $options = collect($group)
                ->flatMap(fn ($u) => (array) ($u->linked_ticket_type_ids ?? []))
                ->unique()
                ->map(fn ($id) => $ticketTypes->get((int) $id))
                ->filter()
                ->map(fn ($tt) => $this->formatBookable($tt, $tt->event, $pricing, $today))
                ->values()->all();

            $out[] = [
                'id'          => 0,
                'slug'        => \Illuminate\Support\Str::slug((string) $type),
                'name'        => \Illuminate\Support\Str::title(str_replace(['_', '-'], ' ', (string) $type)),
                'description' => null,
                'icon'        => null,
                'image_url'   => null,
                'available'   => $group->where('status', PhysicalResource::STATUS_AVAILABLE)->count(),
                'total'       => $group->count(),
                'options'     => $options,
            ];
        }
        return $out;
    }

    /**
     * Shared projection of a bookable ticket type. `price_cents` is resolved
     * through LeisurePricingResolver for today, so weekday rules and seasons
     * are already applied — a storefront must never re-implement that.
     */
    private function formatBookable(
        TicketType $tt,
        ?Event $event,
        LeisurePricingResolver $pricing,
        CarbonImmutable $onDate,
    ): array {
        $variants = $tt->getDurationVariantsCollection()
            ->map(fn ($v) => [
                'duration_minutes' => $v->duration_minutes !== null ? (int) $v->duration_minutes : null,
                'label'            => $v->label,
                'price_cents'      => $v->duration_minutes !== null
                    ? $pricing->resolvePrice($tt, $onDate, (int) $v->duration_minutes)
                    : $pricing->resolvePrice($tt, $onDate),
            ])->values()->all();

        return [
            'ticket_type_id'   => $tt->id,
            'name'             => $tt->name,
            'description'      => $tt->description ?? '',
            'service_category' => $tt->service_category ?: 'access',
            'price_cents'      => $pricing->resolvePrice($tt, $onDate),
            'currency'         => $tt->currency ?? 'RON',
            'available'        => $tt->available_quantity ?? null,
            'duration_variants' => $variants,
            'overtime' => $tt->leisure_is_overtime_chargeable ? [
                'surcharge_cents'  => (int) ($tt->leisure_overtime_surcharge_cents ?? 0),
                'interval_minutes' => (int) ($tt->leisure_overtime_interval_minutes ?? 0),
            ] : null,
            'event' => $event ? [
                'id'    => $event->id,
                'slug'  => $event->slug,
                'title' => $event->getTranslation('title', app()->getLocale()),
                'venue' => $event->venue?->getTranslation('name', app()->getLocale()),
                'city'  => $event->venue?->city,
            ] : null,
        ];
    }

    /**
     * Validate ?ticket_type= belongs to this tenant. Returns the int id, null
     * when the param is absent, or a JsonResponse when it is not ours — so a
     * guessed id can never leak another tenant's capacity.
     */
    private function ticketTypeIdFor(Request $request, int $tenantId)
    {
        $raw = $request->query('ticket_type');
        if ($raw === null || $raw === '') {
            return null;
        }
        $tt = TicketType::with('event:id,tenant_id')->find((int) $raw);
        if (! $tt || $tt->event?->tenant_id !== $tenantId) {
            return response()->json(['success' => false, 'error' => 'Ticket type not found'], 404);
        }
        return (int) $tt->id;
    }

    private function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        return preg_match('#^https?://#', $path)
            ? $path
            : \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
