<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns\FormatsVenueOwnerTicket;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendeesController extends BaseController
{
    use FormatsVenueOwnerTicket;

    /**
     * List tickets (attendees) for a given event at the venue owner's venue.
     * Middleware has already validated the event belongs to the tenant's venue.
     *
     * Query params:
     *  - search: matches customer first_name/last_name/full-name OR exact order_number
     *  - status: filter by ticket status (valid|used|cancelled|refunded|all), default all
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

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = Ticket::query()
            ->where('event_id', $eventModel->id)
            ->with([
                'order:id,order_number,customer_name,customer_id,marketplace_customer_id,paid_at,created_at,status,total,currency',
                'order.customer:id,first_name,last_name',
                'order.marketplaceCustomer:id,first_name,last_name',
                'ticketType:id,name',
            ]);

        if ($status !== 'all' && in_array($status, ['valid', 'used', 'cancelled', 'refunded', 'pending'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($search, $like) {
                $q->whereHas('order', function ($oq) use ($search, $like) {
                    $oq->where('customer_name', 'ilike', $like)
                       ->orWhere('order_number', $search);
                })
                ->orWhereHas('order.marketplaceCustomer', function ($mq) use ($like) {
                    $mq->where('first_name', 'ilike', $like)
                       ->orWhere('last_name', 'ilike', $like);
                })
                ->orWhereHas('order.customer', function ($cq) use ($like) {
                    $cq->where('first_name', 'ilike', $like)
                       ->orWhere('last_name', 'ilike', $like);
                });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $items = collect($paginator->items())->map(fn (Ticket $t) => $this->formatTicket($t, null, includeEvent: false))->values()->toArray();

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

}
