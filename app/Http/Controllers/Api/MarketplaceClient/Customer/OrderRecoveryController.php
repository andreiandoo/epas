<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\PaymentController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order recovery for the storefront /recuperare-comanda page.
 *
 * A customer who bought as a guest (or lost the confirmation email) enters
 * their order number + the email used at checkout. We verify the pair,
 * re-send the tickets email, and — if they're logged in — let them attach
 * the order to their account so it shows up in "Comenzile mele".
 *
 * Security:
 *   - The order_number + email must BOTH match the same order. We never
 *     reveal which half was wrong (generic message) to avoid enumeration.
 *   - Endpoints are rate-limited (see routes) on top of the marketplace
 *     throttle to blunt brute-force of order numbers.
 *   - We only ever act on orders scoped to the requesting marketplace.
 */
class OrderRecoveryController extends BaseController
{
    private const PAID_STATUSES = ['paid', 'confirmed', 'completed'];

    /**
     * POST /customer/recover-order   (public)
     *
     * Verify order_number + email, re-send the confirmation/tickets email,
     * and return a small summary the page can display.
     */
    public function recover(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'order_number' => 'required|string|max:64',
            'email'        => 'required|email|max:255',
            'resend'       => 'nullable|boolean',
        ]);

        $order = $this->findOrder($client->id, $validated['order_number'], $validated['email']);

        if (! $order) {
            // Generic — do not leak which field was wrong.
            return $this->error('Nu am găsit o comandă cu aceste date. Verifică numărul comenzii și emailul folosit la cumpărare.', 404);
        }

        // Re-send the tickets email (default true). Best-effort — a mail
        // failure shouldn't make recovery look broken to the user.
        $resent = false;
        if ($validated['resend'] ?? true) {
            try {
                $order->load([
                    'tickets.marketplaceEvent', 'tickets.marketplaceTicketType',
                    'tickets.ticketType', 'tickets.event', 'marketplaceEvent', 'marketplaceClient',
                ]);
                app(PaymentController::class)->sendOrderConfirmationEmail($order);
                $resent = true;
            } catch (\Throwable $e) {
                Log::channel('marketplace')->warning('Order recovery: resend email failed', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // Does this email already have a (non-guest) account on this marketplace?
        $account = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();
        $hasAccount = $account && $account->password !== null;

        return $this->success([
            'order'        => $this->orderSummary($order),
            'email'        => $validated['email'],
            'email_resent' => $resent,
            'has_account'  => (bool) $hasAccount,
            // Tells the frontend whether attaching is possible right now.
            'can_attach'   => true,
        ], 'Comandă găsită');
    }

    /**
     * POST /customer/recover-order/attach   (auth:sanctum)
     *
     * Logged-in customer claims a recovered order (and its tickets) to
     * their account. Re-verifies order_number + email before attaching.
     */
    public function attach(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Trebuie să fii autentificat pentru a atașa comanda.', 401);
        }

        $validated = $request->validate([
            'order_number' => 'required|string|max:64',
            'email'        => 'required|email|max:255',
        ]);

        $order = $this->findOrder($client->id, $validated['order_number'], $validated['email']);
        if (! $order) {
            return $this->error('Nu am găsit o comandă cu aceste date.', 404);
        }

        // Guard: if already attached to a DIFFERENT registered customer,
        // refuse (the order belongs to someone else's account).
        if ($order->marketplace_customer_id
            && $order->marketplace_customer_id !== $customer->id) {
            $owner = MarketplaceCustomer::find($order->marketplace_customer_id);
            if ($owner && $owner->password !== null) {
                return $this->error('Comanda este deja atașată unui alt cont.', 409);
            }
        }

        if ($order->marketplace_customer_id === $customer->id) {
            return $this->success(['order' => $this->orderSummary($order)], 'Comanda este deja în contul tău.');
        }

        DB::transaction(function () use ($order, $customer) {
            $order->update(['marketplace_customer_id' => $customer->id]);
            // Re-point the order's tickets too so they appear in the wallet.
            Ticket::where('order_id', $order->id)
                ->update(['marketplace_customer_id' => $customer->id]);
        });

        Log::channel('marketplace')->info('Order recovery: order attached to account', [
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
        ]);

        return $this->success([
            'order' => $this->orderSummary($order->fresh()),
        ], 'Comanda a fost atașată contului tău.');
    }

    // ============================================================
    // INTERNALS
    // ============================================================

    /**
     * Resolve an order by number + email within one marketplace. Matches
     * the email against the order's stored customer_email OR the linked
     * customer's email (case-insensitive). Trims a leading '#'/spaces from
     * the order number that users often paste.
     */
    protected function findOrder(int $clientId, string $orderNumber, string $email): ?Order
    {
        $orderNumber = strtoupper(trim(ltrim(trim($orderNumber), '#')));
        $email = strtolower(trim($email));

        return Order::with(['marketplaceCustomer', 'marketplaceEvent'])
            ->where('marketplace_client_id', $clientId)
            ->whereRaw('UPPER(order_number) = ?', [$orderNumber])
            ->where(function ($q) use ($email) {
                $q->whereRaw('LOWER(customer_email) = ?', [$email])
                    ->orWhereHas('marketplaceCustomer', fn ($c) => $c->whereRaw('LOWER(email) = ?', [$email]));
            })
            ->first();
    }

    protected function orderSummary(Order $order): array
    {
        $eventName = null;
        if ($order->marketplaceEvent) {
            $name = $order->marketplaceEvent->name ?? null;
            $eventName = is_array($name) ? ($name['ro'] ?? $name['en'] ?? null) : $name;
        }
        // Activity orders carry the title in meta.
        if (! $eventName && is_array($order->meta ?? null)) {
            $eventName = $order->meta['commission_details'][0]['activity'] ?? null;
        }

        $ticketCount = Ticket::where('order_id', $order->id)->count();

        return [
            'order_number'   => $order->order_number,
            'status'         => $order->status,
            'is_paid'        => in_array($order->status, self::PAID_STATUSES, true),
            'total'          => (float) $order->total,
            'currency'       => $order->currency ?? 'RON',
            'created_at'     => $order->created_at?->toIso8601String(),
            'event_name'     => $eventName,
            'ticket_count'   => $ticketCount,
            'customer_email' => $order->customer_email,
            'is_attached'    => $order->marketplace_customer_id !== null,
        ];
    }
}
