<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceTicketTransfer;
use App\Models\Ticket;
use App\Notifications\MarketplaceTicketTransferNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TicketTransferController extends BaseController
{
    /**
     * Initiate a ticket transfer
     */
    public function initiate(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'ticket_id' => 'required|integer',
            'to_email' => 'required|email|max:255',
            'to_name' => 'required|string|max:255',
            'message' => 'nullable|string|max:500',
        ]);

        // Find the ticket
        $ticket = Ticket::where('id', $validated['ticket_id'])
            ->where('status', 'valid')
            ->whereHas('order', function ($q) use ($client) {
                $q->where('marketplace_client_id', $client->id);
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found or not valid for transfer', 404);
        }

        // Check if customer owns this ticket
        if ($ticket->marketplace_customer_id !== $customer->id) {
            return $this->error('You do not own this ticket', 403);
        }

        // Check if ticket is not already being transferred
        $pendingTransfer = MarketplaceTicketTransfer::where('ticket_id', $ticket->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pendingTransfer) {
            return $this->error('This ticket already has a pending transfer', 400);
        }

        // Check if event hasn't started yet
        if ($ticket->event && $ticket->event->starts_at && $ticket->event->starts_at->isPast()) {
            return $this->error('Cannot transfer tickets for events that have already started', 400);
        }

        // Cannot transfer to self
        if (strtolower($validated['to_email']) === strtolower($customer->email)) {
            return $this->error('Cannot transfer ticket to yourself', 400);
        }

        try {
            DB::beginTransaction();

            // Create transfer record
            $transfer = MarketplaceTicketTransfer::create([
                'marketplace_client_id' => $client->id,
                'ticket_id' => $ticket->id,
                'from_customer_id' => $customer->id,
                'from_email' => $customer->email,
                'from_name' => $customer->full_name,
                'to_email' => $validated['to_email'],
                'to_name' => $validated['to_name'],
                'message' => $validated['message'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]);

            DB::commit();

            // Send notification to sender
            $customer->notify(new MarketplaceTicketTransferNotification($transfer, 'initiated'));

            // Send notification to recipient
            Notification::route('mail', $validated['to_email'])
                ->notify(new MarketplaceTicketTransferNotification($transfer, 'received'));

            return $this->success([
                'transfer' => $this->formatTransfer($transfer),
            ], 'Transfer initiated. The recipient will receive an email.', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to initiate transfer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List customer's outgoing transfers
     */
    public function outgoing(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->requireCustomer($request);

        $transfers = MarketplaceTicketTransfer::where('marketplace_client_id', $client->id)
            ->where('from_customer_id', $customer->id)
            ->with('ticket.event', 'ticket.ticketType')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'transfers' => $transfers->map(fn($t) => $this->formatTransfer($t)),
        ]);
    }

    /**
     * List customer's incoming transfers
     */
    public function incoming(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->requireCustomer($request);

        $transfers = MarketplaceTicketTransfer::where('marketplace_client_id', $client->id)
            ->where('to_email', $customer->email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('ticket.event', 'ticket.ticketType')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'transfers' => $transfers->map(fn($t) => $this->formatTransfer($t)),
        ]);
    }

    /**
     * Cancel an outgoing transfer
     */
    public function cancel(Request $request, int $transferId): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->requireCustomer($request);

        $transfer = MarketplaceTicketTransfer::where('id', $transferId)
            ->where('marketplace_client_id', $client->id)
            ->where('from_customer_id', $customer->id)
            ->first();

        if (!$transfer) {
            return $this->error('Transfer not found', 404);
        }

        if (!$transfer->canBeCancelled()) {
            return $this->error('This transfer cannot be cancelled', 400);
        }

        $transfer->cancel();

        // Notify recipient
        Notification::route('mail', $transfer->to_email)
            ->notify(new MarketplaceTicketTransferNotification($transfer, 'cancelled'));

        return $this->success(null, 'Transfer cancelled');
    }

    /**
     * Accept an incoming transfer (authenticated)
     */
    public function accept(Request $request, int $transferId): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->requireCustomer($request);

        $transfer = MarketplaceTicketTransfer::where('id', $transferId)
            ->where('marketplace_client_id', $client->id)
            ->where('to_email', $customer->email)
            ->with('ticket')
            ->first();

        if (!$transfer) {
            return $this->error('Transfer not found', 404);
        }

        if (!$transfer->canBeAccepted()) {
            return $this->error('This transfer cannot be accepted (expired or already processed)', 400);
        }

        $transfer->accept($customer);

        // Notify sender
        if ($transfer->fromCustomer) {
            $transfer->fromCustomer->notify(new MarketplaceTicketTransferNotification($transfer, 'accepted'));
        }

        return $this->success([
            'ticket' => [
                'id' => $transfer->ticket->id,
                'barcode' => $transfer->ticket->barcode,
                'event' => $transfer->ticket->event?->title,
            ],
        ], 'Transfer accepted! The ticket is now yours.');
    }

    /**
     * Accept transfer by token (guest)
     */
    public function acceptByToken(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        $transfer = MarketplaceTicketTransfer::where('token', $validated['token'])
            ->where('marketplace_client_id', $client->id)
            ->with('ticket')
            ->first();

        if (!$transfer) {
            return $this->error('Invalid transfer token', 404);
        }

        if (!$transfer->canBeAccepted()) {
            return $this->error('This transfer has expired or already been processed', 400);
        }

        // Check if recipient has an account
        $recipient = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $transfer->to_email)
            ->first();

        // Update name if provided
        if (!empty($validated['name'])) {
            $transfer->to_name = $validated['name'];
            $transfer->save();
        }

        $transfer->accept($recipient);

        // Notify sender
        if ($transfer->fromCustomer) {
            $transfer->fromCustomer->notify(new MarketplaceTicketTransferNotification($transfer, 'accepted'));
        }

        return $this->success([
            'ticket' => [
                'id' => $transfer->ticket->id,
                'barcode' => $transfer->ticket->barcode,
                'event' => $transfer->ticket->event?->title,
                'download_url' => route('api.marketplace-client.tickets.download', ['ticket' => $transfer->ticket->id]),
            ],
        ], 'Transfer accepted! The ticket is now yours.');
    }

    /**
     * Reject an incoming transfer
     */
    public function reject(Request $request, int $transferId): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->requireCustomer($request);

        $transfer = MarketplaceTicketTransfer::where('id', $transferId)
            ->where('marketplace_client_id', $client->id)
            ->where('to_email', $customer->email)
            ->first();

        if (!$transfer) {
            return $this->error('Transfer not found', 404);
        }

        if (!$transfer->canBeAccepted()) {
            return $this->error('This transfer cannot be rejected', 400);
        }

        $transfer->reject();

        // Notify sender
        if ($transfer->fromCustomer) {
            $transfer->fromCustomer->notify(new MarketplaceTicketTransferNotification($transfer, 'rejected'));
        }

        return $this->success(null, 'Transfer rejected');
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }

    /**
     * Format transfer for response
     */
    protected function formatTransfer(MarketplaceTicketTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'status' => $transfer->status,
            'from' => [
                'name' => $transfer->from_name,
                'email' => $transfer->from_email,
            ],
            'to' => [
                'name' => $transfer->to_name,
                'email' => $transfer->to_email,
            ],
            'message' => $transfer->message,
            'ticket' => [
                'id' => $transfer->ticket->id,
                'type' => $transfer->ticket->ticketType?->name,
                'event' => $transfer->ticket->event?->title,
                'event_date' => $transfer->ticket->event?->starts_at?->toIso8601String(),
            ],
            'expires_at' => $transfer->expires_at->toIso8601String(),
            'accepted_at' => $transfer->accepted_at?->toIso8601String(),
            'created_at' => $transfer->created_at->toIso8601String(),
            'can_cancel' => $transfer->canBeCancelled(),
            'can_accept' => $transfer->canBeAccepted(),
        ];
    }
}
