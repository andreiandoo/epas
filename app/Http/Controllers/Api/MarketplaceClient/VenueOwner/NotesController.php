<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VenueOwnerNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotesController extends BaseController
{
    /**
     * List notes for a target (optionally filtered). Notes belong to the tenant
     * of the venue owner — they cannot see other tenants' notes.
     *
     * Query params:
     *  - target_type (required): ticket | order | customer
     *  - target_id   (required)
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($request);

        $validated = $request->validate([
            'target_type' => ['required', Rule::in(VenueOwnerNote::TARGET_TYPES)],
            'target_id'   => 'required|integer|min:1',
        ]);

        $this->authorizeTarget($request, $tenant, $validated['target_type'], (int) $validated['target_id']);

        $notes = VenueOwnerNote::where('tenant_id', $tenant->id)
            ->where('target_type', $validated['target_type'])
            ->where('target_id', (int) $validated['target_id'])
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'notes' => $notes->map(fn ($n) => $this->formatNote($n))->values()->toArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($request);
        $user = $this->requireUser($request);

        $validated = $request->validate([
            'target_type' => ['required', Rule::in(VenueOwnerNote::TARGET_TYPES)],
            'target_id'   => 'required|integer|min:1',
            'note'        => 'required|string|max:4000',
        ]);

        $this->authorizeTarget($request, $tenant, $validated['target_type'], (int) $validated['target_id']);

        $note = VenueOwnerNote::create([
            'tenant_id'   => $tenant->id,
            'user_id'     => $user->id,
            'target_type' => $validated['target_type'],
            'target_id'   => (int) $validated['target_id'],
            'note'        => $validated['note'],
        ]);

        $note->load('author:id,name');

        return $this->success(['note' => $this->formatNote($note)], 'Note created', 201);
    }

    public function update(Request $request, int $note): JsonResponse
    {
        $tenant = $this->requireTenant($request);
        $user = $this->requireUser($request);

        $model = VenueOwnerNote::where('id', $note)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$model) {
            return $this->error('Note not found', 404);
        }

        if ((int) $model->user_id !== (int) $user->id) {
            return $this->error('You can only edit your own notes', 403);
        }

        $validated = $request->validate([
            'note' => 'required|string|max:4000',
        ]);

        $model->update(['note' => $validated['note']]);
        $model->load('author:id,name');

        return $this->success(['note' => $this->formatNote($model)], 'Note updated');
    }

    public function destroy(Request $request, int $note): JsonResponse
    {
        $tenant = $this->requireTenant($request);
        $user = $this->requireUser($request);

        $model = VenueOwnerNote::where('id', $note)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$model) {
            return $this->error('Note not found', 404);
        }

        if ((int) $model->user_id !== (int) $user->id) {
            return $this->error('You can only delete your own notes', 403);
        }

        $model->delete();

        return $this->success(null, 'Note deleted');
    }

    // ====================================================
    // Helpers
    // ====================================================

    protected function requireTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('venue_owner_tenant');
        if (!$tenant instanceof Tenant) {
            abort(500, 'Venue owner tenant not resolved');
        }
        return $tenant;
    }

    protected function requireUser(Request $request): User
    {
        $user = $request->user();
        if (!$user instanceof User) {
            abort(401, 'Unauthorized');
        }
        return $user;
    }

    /**
     * Authorize that the target (ticket/order/customer) is actually at this
     * tenant's venue. Prevents someone from reading or writing notes on
     * entities they shouldn't see.
     */
    protected function authorizeTarget(Request $request, Tenant $tenant, string $type, int $id): void
    {
        $client = $this->requireClient($request);

        switch ($type) {
            case VenueOwnerNote::TARGET_TICKET:
                $ticket = Ticket::find($id);
                if (!$ticket) abort(404, 'Ticket not found');
                $this->assertEventAtVenue($tenant, $client->id, $ticket->event_id);
                return;

            case VenueOwnerNote::TARGET_ORDER:
                $order = Order::find($id);
                if (!$order) abort(404, 'Order not found');
                $authorized = Ticket::where('order_id', $order->id)
                    ->whereHas('order', fn ($q) => $q->where('marketplace_client_id', $client->id))
                    ->whereExists(function ($sub) use ($tenant) {
                        $sub->select(DB::raw(1))
                            ->from('events')
                            ->join('venues', 'venues.id', '=', 'events.venue_id')
                            ->whereColumn('events.id', 'tickets.event_id')
                            ->where('venues.tenant_id', $tenant->id);
                    })
                    ->exists();
                if (!$authorized) abort(403, 'Order is not linked to an event at your venue');
                return;

            case VenueOwnerNote::TARGET_CUSTOMER:
                // Allow customer-level note only if the customer has at least one
                // ticket on an event at our venue (same marketplace).
                $authorized = Ticket::whereHas('order', function ($q) use ($id, $client) {
                        $q->where('marketplace_client_id', $client->id)
                          ->where(function ($sub) use ($id) {
                              $sub->where('marketplace_customer_id', $id)
                                  ->orWhere('customer_id', $id);
                          });
                    })
                    ->whereExists(function ($sub) use ($tenant) {
                        $sub->select(DB::raw(1))
                            ->from('events')
                            ->join('venues', 'venues.id', '=', 'events.venue_id')
                            ->whereColumn('events.id', 'tickets.event_id')
                            ->where('venues.tenant_id', $tenant->id);
                    })
                    ->exists();
                if (!$authorized) abort(403, 'Customer has no tickets at your venue');
                return;
        }

        abort(422, 'Invalid target_type');
    }

    protected function assertEventAtVenue(Tenant $tenant, int $clientId, ?int $eventId): void
    {
        if (!$eventId) abort(404, 'Event not found');
        $event = Event::with('venue:id,tenant_id')->find($eventId);
        if (!$event) abort(404, 'Event not found');
        if ((int) $event->marketplace_client_id !== (int) $clientId) abort(403, 'Event is not on this marketplace');
        if (!$event->venue || (int) $event->venue->tenant_id !== (int) $tenant->id) abort(403, 'Event is not at your venue');
    }

    protected function formatNote(VenueOwnerNote $note): array
    {
        $author = $note->author;
        return [
            'id' => (string) $note->id,
            'target_type' => $note->target_type,
            'target_id' => (string) $note->target_id,
            'note' => $note->note,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
            'author' => $author ? [
                'id' => (string) $author->id,
                'name' => $author->name,
            ] : null,
        ];
    }
}
