<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\SupportDepartment;
use App\Models\SupportProblemType;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer-facing support tickets.
 *
 * GET    /customer/support-tickets             — list current customer's tickets
 * GET    /customer/support-tickets/{ticket}    — single ticket + thread
 * POST   /customer/support-tickets             — create new ticket
 * POST   /customer/support-tickets/{ticket}/messages — append a reply
 * GET    /customer/support-meta                — departments + problem types for the form
 *
 * SupportTicket uses a polymorphic `opener()` relation. Customer tickets
 * are stored with opener_type = MarketplaceCustomer::class + opener_id =
 * the authenticated customer's id (mirrors how organizers open tickets).
 */
class SupportController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client   = $this->requireClient($request);

        $tickets = SupportTicket::where('marketplace_client_id', $client->id)
            ->where('opener_type', MarketplaceCustomer::class)
            ->where('opener_id', $customer->id)
            ->with(['department', 'problemType'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $data = $tickets->map(fn ($t) => $this->shape($t));

        $counts = [
            'open'   => $tickets->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS, SupportTicket::STATUS_AWAITING_ORGANIZER])->count(),
            'closed' => $tickets->whereIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])->count(),
            'total'  => $tickets->count(),
        ];

        return $this->success(['tickets' => $data, 'counts' => $counts]);
    }

    public function show(Request $request, int $ticket): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client   = $this->requireClient($request);

        $t = SupportTicket::with(['department', 'problemType', 'publicMessages'])
            ->where('id', $ticket)
            ->where('marketplace_client_id', $client->id)
            ->where('opener_type', MarketplaceCustomer::class)
            ->where('opener_id', $customer->id)
            ->first();

        if (! $t) return $this->error('Ticket inexistent', 404);

        return $this->success([
            'ticket'   => $this->shape($t),
            'messages' => $t->publicMessages->map(fn ($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'is_staff'   => (bool) ($m->is_staff ?? false),
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client   = $this->requireClient($request);

        $validated = $request->validate([
            'subject'                 => 'required|string|max:200',
            'message'                 => 'required|string|max:5000',
            'support_department_id'   => 'nullable|integer',
            'support_problem_type_id' => 'nullable|integer',
            'priority'                => 'nullable|in:low,normal,high,urgent',
            'context'                 => 'nullable|array',
        ]);

        $ticket = DB::transaction(function () use ($validated, $customer, $client) {
            $t = SupportTicket::create([
                'marketplace_client_id'   => $client->id,
                'opener_type'             => MarketplaceCustomer::class,
                'opener_id'               => $customer->id,
                'support_department_id'   => $validated['support_department_id'] ?? null,
                'support_problem_type_id' => $validated['support_problem_type_id'] ?? null,
                'subject'                 => $validated['subject'],
                'status'                  => SupportTicket::STATUS_OPEN,
                'priority'                => $validated['priority'] ?? 'normal',
                'context'                 => $validated['context'] ?? null,
                'opened_at'               => now(),
                'last_activity_at'        => now(),
            ]);

            SupportTicketMessage::create([
                'support_ticket_id'     => $t->id,
                'sender_type'           => MarketplaceCustomer::class,
                'sender_id'             => $customer->id,
                'body'                  => $validated['message'],
                'is_internal_note'      => false,
                'is_staff'              => false,
            ]);

            return $t;
        });

        $ticket->refresh();
        return $this->success(['ticket' => $this->shape($ticket)], 'Tichetul a fost trimis.', 201);
    }

    public function reply(Request $request, int $ticket): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client   = $this->requireClient($request);

        $validated = $request->validate(['message' => 'required|string|max:5000']);

        $t = SupportTicket::where('id', $ticket)
            ->where('marketplace_client_id', $client->id)
            ->where('opener_type', MarketplaceCustomer::class)
            ->where('opener_id', $customer->id)
            ->first();

        if (! $t) return $this->error('Ticket inexistent', 404);
        if ($t->status === SupportTicket::STATUS_CLOSED) {
            return $this->error('Tichetul este închis. Deschide unul nou pentru o solicitare nouă.', 400);
        }

        $msg = SupportTicketMessage::create([
            'support_ticket_id' => $t->id,
            'sender_type'       => MarketplaceCustomer::class,
            'sender_id'         => $customer->id,
            'body'              => $validated['message'],
            'is_internal_note'  => false,
            'is_staff'          => false,
        ]);

        $t->update(['last_activity_at' => now(), 'status' => SupportTicket::STATUS_OPEN]);

        return $this->success([
            'message' => ['id' => $msg->id, 'body' => $msg->body, 'created_at' => $msg->created_at?->toIso8601String()],
            'ticket'  => $this->shape($t->fresh()),
        ], 'Mesajul a fost trimis.', 201);
    }

    /**
     * Form bootstrap: departments + problem types open to customers.
     */
    public function meta(Request $request): JsonResponse
    {
        $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $deps = SupportDepartment::where('marketplace_client_id', $client->id)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug']);
        $types = SupportProblemType::where('marketplace_client_id', $client->id)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug', 'support_department_id']);

        return $this->success([
            'departments'   => $deps,
            'problem_types' => $types,
        ]);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }
        return $customer;
    }

    protected function shape(SupportTicket $t): array
    {
        return [
            'id'             => $t->id,
            'ticket_number'  => $t->ticket_number,
            'subject'        => $t->subject,
            'status'         => $t->status,
            'priority'       => $t->priority,
            'department'     => $t->department  ? ['id' => $t->department->id,  'name' => $t->department->name]  : null,
            'problem_type'   => $t->problemType ? ['id' => $t->problemType->id, 'name' => $t->problemType->name] : null,
            'opened_at'      => $t->opened_at?->toIso8601String(),
            'last_activity'  => $t->last_activity_at?->toIso8601String(),
            'is_closed'      => in_array($t->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true),
        ];
    }
}
