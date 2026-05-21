<?php

namespace App\Http\Controllers\Api\Leisure;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Services\Leisure\CapacityAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public availability endpoints for leisure tenants. Read-only — used by
 * calendar pickers on the public website / embed widget. Aggressively
 * cacheable (60s) since data only changes when admin edits capacity or a
 * sale completes (both invalidations handled at the service layer).
 */
class AvailabilityController extends Controller
{
    /**
     * GET /api/leisure/tenants/{tenant:slug}/ticket-types/{ticketType}/availability?month=YYYY-MM
     */
    public function month(
        Tenant $tenant,
        TicketType $ticketType,
        Request $request,
        CapacityAvailabilityService $service,
    ): JsonResponse {
        $this->ensureLeisure($tenant);
        $this->ensureBelongsToTenant($ticketType, $tenant);

        $month = $request->input('month', now()->format('Y-m'));
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return response()->json(['error' => 'Invalid month format. Expected YYYY-MM.'], 422);
        }

        return response()
            ->json([
                'tenant_id' => $tenant->id,
                'ticket_type_id' => $ticketType->id,
                'month' => $monthStart->format('Y-m'),
                'dates' => $service->getAvailabilityForMonth($tenant->id, $monthStart, $ticketType->id),
            ])
            ->setMaxAge(60)
            ->setPublic();
    }

    /**
     * GET /api/leisure/tenants/{tenant:slug}/ticket-types/{ticketType}/slots?date=YYYY-MM-DD
     */
    public function slots(
        Tenant $tenant,
        TicketType $ticketType,
        Request $request,
        CapacityAvailabilityService $service,
    ): JsonResponse {
        $this->ensureLeisure($tenant);
        $this->ensureBelongsToTenant($ticketType, $tenant);

        $date = $request->input('date', now()->toDateString());
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return response()->json(['error' => 'Invalid date format. Expected YYYY-MM-DD.'], 422);
        }

        return response()
            ->json([
                'tenant_id' => $tenant->id,
                'ticket_type_id' => $ticketType->id,
                'date' => $dateObj->toDateString(),
                'slots' => $service->getSlotsForDate($tenant->id, $ticketType->id, $dateObj),
            ])
            ->setMaxAge(30)
            ->setPublic();
    }

    protected function ensureLeisure(Tenant $tenant): void
    {
        $type = $tenant->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value
            : (string) $tenant->tenant_type;
        abort_unless($type === 'leisure', 404, 'Tenant is not a leisure venue.');
    }

    protected function ensureBelongsToTenant(TicketType $ticketType, Tenant $tenant): void
    {
        // ticket_types belong to events; events belong to tenant
        $tenantId = $ticketType->event?->tenant_id;
        abort_unless($tenantId === $tenant->id, 404, 'Ticket type not found for this tenant.');
    }
}
