<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Jobs\SendGiftCardEmailJob;
use App\Models\MarketplaceGiftCard;
use App\Models\MarketplaceGiftCardDesign;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GiftCardController extends BaseController
{
    /**
     * Get available gift card options (amounts, designs, occasions)
     */
    public function options(Request $request): JsonResponse
    {
        $marketplace = $this->getMarketplace($request);

        $designs = MarketplaceGiftCardDesign::forMarketplace($marketplace->id)
            ->active()
            ->ordered()
            ->get()
            ->map(fn ($design) => [
                'slug' => $design->slug,
                'name' => $design->name,
                'occasion' => $design->occasion,
                'occasion_label' => $design->occasion_label,
                'preview_url' => $design->preview_url,
                'colors' => $design->colors,
                'is_default' => $design->is_default,
            ]);

        // Get gift card settings from marketplace
        $settings = $marketplace->gift_card_settings ?? [];

        return $this->success([
            'preset_amounts' => $settings['preset_amounts'] ?? MarketplaceGiftCard::PRESET_AMOUNTS,
            'min_custom_amount' => $settings['min_custom_amount'] ?? 10,
            'max_custom_amount' => $settings['max_custom_amount'] ?? 1000,
            'allow_custom_amount' => $settings['allow_custom_amount'] ?? false,
            'currency' => $settings['currency'] ?? 'RON',
            'validity_days' => $settings['validity_days'] ?? 365,
            'occasions' => MarketplaceGiftCard::OCCASIONS,
            'designs' => $designs,
            'delivery_methods' => [
                ['value' => 'email', 'label' => 'Send by Email'],
                ['value' => 'print', 'label' => 'Print at Home'],
            ],
        ]);
    }

    /**
     * Purchase a gift card
     */
    public function purchase(Request $request): JsonResponse
    {
        $customer = $this->getOptionalCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $settings = $marketplace->gift_card_settings ?? [];
        $presetAmounts = $settings['preset_amounts'] ?? MarketplaceGiftCard::PRESET_AMOUNTS;
        $allowCustom = $settings['allow_custom_amount'] ?? false;
        $minAmount = $settings['min_custom_amount'] ?? 10;
        $maxAmount = $settings['max_custom_amount'] ?? 1000;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', function ($attribute, $value, $fail) use ($presetAmounts, $allowCustom, $minAmount, $maxAmount) {
                if (!in_array((float) $value, array_map('floatval', $presetAmounts))) {
                    if (!$allowCustom) {
                        $fail('Please select a valid amount.');
                    } elseif ($value < $minAmount || $value > $maxAmount) {
                        $fail("Amount must be between {$minAmount} and {$maxAmount}.");
                    }
                }
            }],
            'quantity' => 'required|integer|min:1|max:10',

            // Purchaser info
            'purchaser_name' => 'required|string|max:255',
            'purchaser_email' => 'required|email|max:255',

            // Recipient info
            'recipient_name' => 'required|string|max:255',
            'recipient_email' => 'required|email|max:255',
            'personal_message' => 'nullable|string|max:500',
            'occasion' => 'nullable|string|in:' . implode(',', array_keys(MarketplaceGiftCard::OCCASIONS)),

            // Delivery
            'delivery_method' => 'required|in:email,print',
            'scheduled_delivery_at' => 'nullable|date|after:now',

            // Design
            'design_template' => 'nullable|string|exists:marketplace_gift_card_designs,slug',
        ]);

        $giftCards = [];
        $validityDays = $settings['validity_days'] ?? 365;

        for ($i = 0; $i < $validated['quantity']; $i++) {
            $giftCard = MarketplaceGiftCard::create([
                'marketplace_client_id' => $marketplace->id,
                'initial_amount' => $validated['amount'],
                'balance' => $validated['amount'],
                'currency' => $settings['currency'] ?? 'RON',
                'purchaser_id' => $customer?->id,
                'purchaser_email' => $validated['purchaser_email'],
                'purchaser_name' => $validated['purchaser_name'],
                'recipient_email' => $validated['recipient_email'],
                'recipient_name' => $validated['recipient_name'],
                'personal_message' => $validated['personal_message'] ?? null,
                'occasion' => $validated['occasion'] ?? null,
                'delivery_method' => $validated['delivery_method'],
                'scheduled_delivery_at' => $validated['scheduled_delivery_at'] ?? null,
                'design_template' => $validated['design_template'] ?? 'default',
                'status' => MarketplaceGiftCard::STATUS_PENDING,
                'expires_at' => now()->addDays($validityDays),
            ]);

            // Record purchase transaction
            $giftCard->transactions()->create([
                'marketplace_client_id' => $marketplace->id,
                'type' => 'purchase',
                'amount' => $validated['amount'],
                'balance_before' => 0,
                'balance_after' => $validated['amount'],
                'currency' => $giftCard->currency,
                'performed_by_customer_id' => $customer?->id,
                'description' => 'Gift card purchased',
            ]);

            $giftCards[] = $giftCard;
        }

        // Calculate total for payment
        $totalAmount = $validated['amount'] * $validated['quantity'];

        return $this->success([
            'gift_cards' => collect($giftCards)->map(fn ($gc) => [
                'id' => $gc->id,
                'code' => $gc->code,
                'amount' => $gc->initial_amount,
                'currency' => $gc->currency,
                'recipient_email' => $gc->recipient_email,
                'recipient_name' => $gc->recipient_name,
                'expires_at' => $gc->expires_at->toIso8601String(),
            ]),
            'total_amount' => $totalAmount,
            'currency' => $settings['currency'] ?? 'RON',
            'payment_required' => true,
            'message' => 'Gift cards created. Complete payment to activate.',
        ], 'Gift cards created successfully', 201);
    }

    /**
     * Complete gift card purchase (after payment)
     */
    public function completePurchase(Request $request): JsonResponse
    {
        $marketplace = $this->getMarketplace($request);

        $validated = $request->validate([
            'gift_card_ids' => 'required|array',
            'gift_card_ids.*' => 'integer|exists:marketplace_gift_cards,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'payment_reference' => 'nullable|string',
        ]);

        $giftCards = MarketplaceGiftCard::whereIn('id', $validated['gift_card_ids'])
            ->where('marketplace_client_id', $marketplace->id)
            ->where('status', MarketplaceGiftCard::STATUS_PENDING)
            ->get();

        if ($giftCards->isEmpty()) {
            return $this->error('No pending gift cards found', 404);
        }

        foreach ($giftCards as $giftCard) {
            $giftCard->update([
                'purchase_order_id' => $validated['order_id'] ?? null,
            ]);

            // Activate the gift card
            $giftCard->activate();

            // Send email if delivery method is email and no scheduled date
            if ($giftCard->delivery_method === 'email' && !$giftCard->scheduled_delivery_at) {
                SendGiftCardEmailJob::dispatch($giftCard);
                $giftCard->markDelivered();
            }
        }

        return $this->success([
            'activated_count' => $giftCards->count(),
            'gift_cards' => $giftCards->map(fn ($gc) => [
                'id' => $gc->id,
                'code' => $gc->code,
                'status' => $gc->status,
                'is_delivered' => $gc->is_delivered,
            ]),
        ], 'Gift cards activated successfully');
    }

    /**
     * Check gift card balance
     */
    public function checkBalance(Request $request): JsonResponse
    {
        $marketplace = $this->getMarketplace($request);

        $validated = $request->validate([
            'code' => 'required|string',
            'pin' => 'nullable|string',
        ]);

        $giftCard = MarketplaceGiftCard::where('marketplace_client_id', $marketplace->id)
            ->where('code', strtoupper(trim($validated['code'])))
            ->first();

        if (!$giftCard) {
            return $this->error('Gift card not found', 404);
        }

        // Optional PIN verification
        if ($giftCard->pin && (!isset($validated['pin']) || $giftCard->pin !== $validated['pin'])) {
            return $this->error('Invalid PIN', 403);
        }

        return $this->success([
            'code' => $giftCard->masked_code,
            'balance' => $giftCard->balance,
            'initial_amount' => $giftCard->initial_amount,
            'currency' => $giftCard->currency,
            'status' => $giftCard->status,
            'status_label' => $giftCard->status_label,
            'is_usable' => $giftCard->isUsable(),
            'expires_at' => $giftCard->expires_at->toIso8601String(),
            'days_until_expiry' => $giftCard->days_until_expiry,
        ]);
    }

    /**
     * Claim a gift card (link to customer account)
     */
    public function claim(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $validated = $request->validate([
            'code' => 'required|string',
            'pin' => 'nullable|string',
        ]);

        $giftCard = MarketplaceGiftCard::where('marketplace_client_id', $marketplace->id)
            ->where('code', strtoupper(trim($validated['code'])))
            ->first();

        if (!$giftCard) {
            return $this->error('Gift card not found', 404);
        }

        // PIN verification
        if ($giftCard->pin && (!isset($validated['pin']) || $giftCard->pin !== $validated['pin'])) {
            return $this->error('Invalid PIN', 403);
        }

        if (!$giftCard->isActive()) {
            return $this->error('This gift card is not active', 422);
        }

        if ($giftCard->recipient_customer_id) {
            if ($giftCard->recipient_customer_id === $customer->id) {
                return $this->error('You have already claimed this gift card', 422);
            }
            return $this->error('This gift card has already been claimed', 422);
        }

        // Verify email matches if set
        if ($giftCard->recipient_email && strtolower($giftCard->recipient_email) !== strtolower($customer->email)) {
            return $this->error('This gift card was sent to a different email address', 403);
        }

        $giftCard->claim($customer);

        return $this->success([
            'gift_card' => [
                'id' => $giftCard->id,
                'code' => $giftCard->masked_code,
                'balance' => $giftCard->balance,
                'currency' => $giftCard->currency,
                'expires_at' => $giftCard->expires_at->toIso8601String(),
            ],
            'message' => 'Gift card claimed successfully',
        ]);
    }

    /**
     * Get customer's gift cards (purchased and received)
     */
    public function myGiftCards(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $type = $request->input('type', 'all'); // all, purchased, received

        $query = MarketplaceGiftCard::where('marketplace_client_id', $marketplace->id);

        if ($type === 'purchased') {
            $query->where('purchaser_id', $customer->id);
        } elseif ($type === 'received') {
            $query->where(function ($q) use ($customer) {
                $q->where('recipient_customer_id', $customer->id)
                    ->orWhere('recipient_email', $customer->email);
            });
        } else {
            $query->where(function ($q) use ($customer) {
                $q->where('purchaser_id', $customer->id)
                    ->orWhere('recipient_customer_id', $customer->id)
                    ->orWhere('recipient_email', $customer->email);
            });
        }

        $giftCards = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->success([
            'gift_cards' => $giftCards->map(function ($gc) use ($customer) {
                $isPurchaser = $gc->purchaser_id === $customer->id;

                return [
                    'id' => $gc->id,
                    'code' => $isPurchaser ? $gc->code : $gc->masked_code,
                    'amount' => $gc->initial_amount,
                    'balance' => $gc->balance,
                    'currency' => $gc->currency,
                    'status' => $gc->status,
                    'status_label' => $gc->status_label,
                    'is_usable' => $gc->isUsable(),
                    'type' => $isPurchaser ? 'purchased' : 'received',
                    'recipient_name' => $gc->recipient_name,
                    'recipient_email' => $gc->recipient_email,
                    'purchaser_name' => $gc->purchaser_name,
                    'occasion' => $gc->occasion,
                    'occasion_label' => $gc->occasion_label,
                    'personal_message' => $gc->personal_message,
                    'expires_at' => $gc->expires_at->toIso8601String(),
                    'days_until_expiry' => $gc->days_until_expiry,
                    'is_claimed' => $gc->recipient_customer_id !== null,
                    'created_at' => $gc->created_at->toIso8601String(),
                ];
            }),
            'pagination' => [
                'current_page' => $giftCards->currentPage(),
                'last_page' => $giftCards->lastPage(),
                'per_page' => $giftCards->perPage(),
                'total' => $giftCards->total(),
            ],
            'summary' => [
                'total_balance' => $customer->gift_card_balance,
                'currency' => 'RON',
            ],
        ]);
    }

    /**
     * Redeem gift card during checkout
     */
    public function redeem(Request $request): JsonResponse
    {
        $customer = $this->getOptionalCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $validated = $request->validate([
            'code' => 'required|string',
            'pin' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $giftCard = MarketplaceGiftCard::where('marketplace_client_id', $marketplace->id)
            ->where('code', strtoupper(trim($validated['code'])))
            ->first();

        if (!$giftCard) {
            return $this->error('Gift card not found', 404);
        }

        // PIN verification
        if ($giftCard->pin && (!isset($validated['pin']) || $giftCard->pin !== $validated['pin'])) {
            return $this->error('Invalid PIN', 403);
        }

        if (!$giftCard->isUsable()) {
            return $this->error('This gift card cannot be used: ' . $giftCard->status_label, 422);
        }

        $amount = min($validated['amount'], $giftCard->balance);

        $order = Order::where('id', $validated['order_id'])
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        $success = $giftCard->redeem($amount, $order, $customer);

        if (!$success) {
            return $this->error('Failed to redeem gift card', 500);
        }

        // Update order with gift card info
        $existingCodes = $order->gift_card_codes ?? [];
        $existingCodes[] = $giftCard->code;

        $order->update([
            'gift_card_amount' => ($order->gift_card_amount ?? 0) + $amount,
            'gift_card_codes' => $existingCodes,
        ]);

        return $this->success([
            'redeemed_amount' => $amount,
            'remaining_balance' => $giftCard->balance,
            'currency' => $giftCard->currency,
            'gift_card' => [
                'code' => $giftCard->masked_code,
                'balance' => $giftCard->balance,
                'status' => $giftCard->status,
            ],
            'order' => [
                'id' => $order->id,
                'total' => $order->total,
                'gift_card_amount' => $order->gift_card_amount,
                'remaining_to_pay' => max(0, $order->total - ($order->gift_card_amount ?? 0)),
            ],
        ], 'Gift card redeemed successfully');
    }

    /**
     * Get gift card transaction history
     */
    public function transactions(Request $request, int $id): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $giftCard = MarketplaceGiftCard::where('marketplace_client_id', $marketplace->id)
            ->where('id', $id)
            ->where(function ($q) use ($customer) {
                $q->where('purchaser_id', $customer->id)
                    ->orWhere('recipient_customer_id', $customer->id)
                    ->orWhere('recipient_email', $customer->email);
            })
            ->firstOrFail();

        $transactions = $giftCard->transactions()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'type_label' => $tx->type_label,
                'amount' => $tx->amount,
                'formatted_amount' => $tx->formatted_amount,
                'balance_after' => $tx->balance_after,
                'description' => $tx->description,
                'order_id' => $tx->order_id,
                'created_at' => $tx->created_at->toIso8601String(),
            ]);

        return $this->success([
            'gift_card' => [
                'id' => $giftCard->id,
                'code' => $giftCard->masked_code,
                'balance' => $giftCard->balance,
                'initial_amount' => $giftCard->initial_amount,
            ],
            'transactions' => $transactions,
        ]);
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
     * Get optional customer (for guest checkout)
     */
    protected function getOptionalCustomer(Request $request): ?MarketplaceCustomer
    {
        $customer = $request->user();
        return $customer instanceof MarketplaceCustomer ? $customer : null;
    }
}
