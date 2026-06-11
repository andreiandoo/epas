<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Services\Shop\ShopEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopEventProductController extends Controller
{
    public function __construct(
        protected ShopEventService $eventService
    ) {}

    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();
            return $domain?->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function hasShopMicroservice(Tenant $tenant): bool
    {
        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function getLanguage(Request $request, Tenant $tenant): string
    {
        return $request->query('lang', $tenant->language ?? 'en');
    }

    /**
     * Get upsell products for an event
     */
    public function upsells(Request $request, int $eventId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $event = Event::where('tenant_id', $tenant->id)->find($eventId);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $language = $this->getLanguage($request, $tenant);
        $upsells = $this->eventService->getUpsellsForEvent($eventId, $language);

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $eventId,
                'upsells' => $upsells,
            ],
        ]);
    }

    /**
     * Get upsell products for a specific ticket type
     */
    public function ticketTypeUpsells(Request $request, int $ticketTypeId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $ticketType = TicketType::whereHas('event', fn($q) => $q->where('tenant_id', $tenant->id))
            ->find($ticketTypeId);

        if (!$ticketType) {
            return response()->json(['success' => false, 'message' => 'Ticket type not found'], 404);
        }

        $language = $this->getLanguage($request, $tenant);
        $upsells = $this->eventService->getUpsellsForTicketType($ticketTypeId, $language);

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_type_id' => $ticketTypeId,
                'upsells' => $upsells,
            ],
        ]);
    }

    /**
     * Get bundled products for a ticket type
     */
    public function bundles(Request $request, int $ticketTypeId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $ticketType = TicketType::whereHas('event', fn($q) => $q->where('tenant_id', $tenant->id))
            ->find($ticketTypeId);

        if (!$ticketType) {
            return response()->json(['success' => false, 'message' => 'Ticket type not found'], 404);
        }

        $language = $this->getLanguage($request, $tenant);
        $bundles = $this->eventService->getBundlesForTicketType($ticketTypeId, $language);

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_type_id' => $ticketTypeId,
                'bundles' => $bundles,
            ],
        ]);
    }
}
