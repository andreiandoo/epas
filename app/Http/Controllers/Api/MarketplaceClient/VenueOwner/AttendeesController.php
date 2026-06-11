<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns\FormatsVenueOwnerTicket;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\VenueOwnerNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendeesController extends BaseController
{
    use FormatsVenueOwnerTicket;

    /**
     * List valid attendees for an event at the venue owner's venue. Only tickets
     * with status valid/used on a paid/confirmed/completed order are included —
     * cancelled / refunded tickets and customers never show here.
     *
     * Query params:
     *  - search: matches customer first/last name, phone, OR order_number (exact)
     *  - page, per_page
     */
    public function index(Request $request, int $event): JsonResponse
    {
        /** @var Event|null $eventModel */
        $eventModel = $request->attributes->get('venue_owner_event');
        if (!$eventModel instanceof Event) {
            $eventModel = Event::find($event);
        }

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('venue_owner_tenant');

        $search = trim((string) $request->query('search', ''));
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = Ticket::query()
            ->where('event_id', $eventModel->id)
            // Only valid/used tickets — cancelled/refunded never appear in the list
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'confirmed', 'completed']))
            ->with([
                'order:id,order_number,customer_name,customer_phone,customer_id,marketplace_customer_id,paid_at,created_at,status,total,currency',
                'order.customer:id,first_name,last_name,phone',
                'order.marketplaceCustomer:id,first_name,last_name,phone',
                'ticketType:id,name',
            ]);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $digits = preg_replace('/\D+/', '', $search);
            $phoneLike = $digits !== '' ? '%' . $digits . '%' : null;

            $query->where(function ($q) use ($search, $like, $phoneLike) {
                $q->whereHas('order', function ($oq) use ($search, $like, $phoneLike) {
                    $oq->where('customer_name', 'ilike', $like)
                       ->orWhere('order_number', $search);
                    if ($phoneLike) {
                        $oq->orWhere('customer_phone', 'ilike', $phoneLike);
                    }
                })
                ->orWhereHas('order.marketplaceCustomer', function ($mq) use ($like, $phoneLike) {
                    $mq->where('first_name', 'ilike', $like)
                       ->orWhere('last_name', 'ilike', $like);
                    if ($phoneLike) {
                        $mq->orWhere('phone', 'ilike', $phoneLike);
                    }
                })
                ->orWhereHas('order.customer', function ($cq) use ($like, $phoneLike) {
                    $cq->where('first_name', 'ilike', $like)
                       ->orWhere('last_name', 'ilike', $like);
                    if ($phoneLike) {
                        $cq->orWhere('phone', 'ilike', $phoneLike);
                    }
                });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $tickets = collect($paginator->items());
        $notesMap = $tenant instanceof Tenant
            ? $this->buildHasNotesMap($tenant->id, $tickets)
            : collect();

        $items = $tickets->map(function (Ticket $t) use ($notesMap) {
            $data = $this->formatTicket($t, null, includeEvent: false);
            $data['has_notes'] = (bool) $notesMap->get($t->id, false);
            return $data;
        })->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
          ->header('Pragma', 'no-cache');
    }

    /**
     * For each ticket in the page, determine whether any note exists for that
     * ticket (by ticket id, its order, or its customer identity). Done in a
     * single aggregate query so the payload stays cheap regardless of page size.
     *
     * @return \Illuminate\Support\Collection<int,bool> keyed by ticket id
     */
    protected function buildHasNotesMap(int $tenantId, \Illuminate\Support\Collection $tickets): \Illuminate\Support\Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $ticketIds = $tickets->pluck('id')->filter()->unique()->values();
        $orderIds = $tickets->pluck('order_id')->filter()->unique()->values();
        $customerIds = $tickets->map(function ($t) {
            return $t->order?->marketplace_customer_id ?? $t->order?->customer_id ?? null;
        })->filter()->unique()->values();

        $matchedTickets = VenueOwnerNote::where('tenant_id', $tenantId)
            ->where('target_type', VenueOwnerNote::TARGET_TICKET)
            ->whereIn('target_id', $ticketIds)
            ->pluck('target_id')
            ->unique()
            ->flip();

        $matchedOrders = $orderIds->isEmpty()
            ? collect()
            : VenueOwnerNote::where('tenant_id', $tenantId)
                ->where('target_type', VenueOwnerNote::TARGET_ORDER)
                ->whereIn('target_id', $orderIds)
                ->pluck('target_id')
                ->unique()
                ->flip();

        $matchedCustomers = $customerIds->isEmpty()
            ? collect()
            : VenueOwnerNote::where('tenant_id', $tenantId)
                ->where('target_type', VenueOwnerNote::TARGET_CUSTOMER)
                ->whereIn('target_id', $customerIds)
                ->pluck('target_id')
                ->unique()
                ->flip();

        return $tickets->mapWithKeys(function (Ticket $t) use ($matchedTickets, $matchedOrders, $matchedCustomers) {
            $cid = $t->order?->marketplace_customer_id ?? $t->order?->customer_id ?? null;
            $has = $matchedTickets->has($t->id)
                || ($t->order_id && $matchedOrders->has($t->order_id))
                || ($cid && $matchedCustomers->has($cid));
            return [$t->id => $has];
        });
    }
}
