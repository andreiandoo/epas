<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Tenant;
use App\Models\Domain;
use App\Services\OrderEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Create a new order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Support both split name and single name field
            'customer_name' => 'nullable|string|max:255',
            'customer_first_name' => 'nullable|string|max:255',
            'customer_last_name' => 'nullable|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'cart' => 'required|array|min:1',
            'cart.*.eventId' => 'required|integer',
            'cart.*.ticketTypeId' => 'required|integer',
            'cart.*.quantity' => 'required|integer|min:1',
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.name' => 'required|string|max:255',
            'beneficiaries.*.email' => 'nullable|email|max:255',
            'beneficiaries.*.phone' => 'nullable|string|max:50',
            'notification_email' => 'nullable|boolean',
            'notification_whatsapp' => 'nullable|boolean',
            // Coupon support
            'coupon_code' => 'nullable|string|max:50',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        // Parse first/last name from either split fields or single field
        $firstName = $validated['customer_first_name'] ?? null;
        $lastName = $validated['customer_last_name'] ?? null;

        if (!$firstName && !$lastName && !empty($validated['customer_name'])) {
            // Split the full name
            $nameParts = explode(' ', trim($validated['customer_name']), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
        }

        $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

        // Resolve tenant from hostname
        $hostname = $request->query('hostname');
        if (!$hostname) {
            return response()->json(['error' => 'Hostname required'], 400);
        }

        $domain = Domain::where('domain', $hostname)
            ->where('is_active', true)
            ->first();

        if (!$domain) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        $tenant = $domain->tenant;

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        try {
            return DB::transaction(function () use ($validated, $tenant, $firstName, $lastName, $fullName, $request) {
                // Find or create customer
                $customer = Customer::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'email' => $validated['customer_email'],
                    ],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $validated['customer_phone'] ?? null,
                        'primary_tenant_id' => $tenant->id,
                    ]
                );

                // Update customer name if provided and currently empty
                if ((!$customer->first_name && !$customer->last_name) && ($firstName || $lastName)) {
                    $customer->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $validated['customer_phone'] ?? $customer->phone,
                    ]);
                }

                // Calculate total with bulk discounts
                $totalCents = 0;
                $orderItems = [];

                foreach ($validated['cart'] as $cartItem) {
                    $ticketType = TicketType::find($cartItem['ticketTypeId']);

                    if (!$ticketType) {
                        throw new \Exception("Ticket type {$cartItem['ticketTypeId']} not found");
                    }

                    // Check availability
                    $availableQty = $ticketType->quota_total - $ticketType->quota_sold;
                    if ($cartItem['quantity'] > $availableQty) {
                        throw new \Exception("Not enough tickets available for {$ticketType->name}");
                    }

                    // Get price (sale price or regular price)
                    $pricePerTicket = $ticketType->sale_price_cents ?? $ticketType->price_cents;

                    // Apply bulk discounts
                    $quantity = $cartItem['quantity'];
                    $bulkDiscounts = $ticketType->bulk_discounts ?? [];
                    $itemTotal = $this->calculateBulkDiscountPrice($quantity, $pricePerTicket, $bulkDiscounts);

                    $totalCents += $itemTotal;

                    $orderItems[] = [
                        'ticket_type_id' => $ticketType->id,
                        'quantity' => $quantity,
                        'price_cents' => $pricePerTicket,
                        'total_cents' => $itemTotal,
                    ];

                    // Update quota
                    $ticketType->increment('quota_sold', $quantity);
                }

                // Apply coupon discount if provided
                $discountAmount = isset($validated['discount_amount']) ? (int) ($validated['discount_amount'] * 100) : 0;
                if ($discountAmount > 0 && $discountAmount < $totalCents) {
                    $totalCents -= $discountAmount;
                }

                // Create order
                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'customer_email' => $validated['customer_email'],
                    'total_cents' => $totalCents,
                    'status' => 'pending',
                    'promo_code' => $validated['coupon_code'] ?? null,
                    'promo_discount' => $discountAmount > 0 ? $discountAmount / 100 : 0,
                    'meta' => [
                        'customer_name' => $fullName,
                        'customer_first_name' => $firstName,
                        'customer_last_name' => $lastName,
                        'customer_phone' => $validated['customer_phone'] ?? null,
                        'items' => $orderItems,
                        'beneficiaries' => $validated['beneficiaries'] ?? null,
                        'coupon_code' => $validated['coupon_code'] ?? null,
                        'discount_amount' => $discountAmount / 100,
                        'notification_preferences' => [
                            'email' => $validated['notification_email'] ?? true,
                            'whatsapp' => $validated['notification_whatsapp'] ?? false,
                        ],
                    ],
                ]);

                // Create tickets and assign beneficiaries
                $beneficiaries = $validated['beneficiaries'] ?? [];
                $beneficiaryIndex = 0;
                
                foreach ($orderItems as $item) {
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $ticketMeta = [];
                        
                        // Assign beneficiary if available
                        if (isset($beneficiaries[$beneficiaryIndex])) {
                            $ticketMeta['beneficiary'] = [
                                'name' => $beneficiaries[$beneficiaryIndex]['name'],
                                'email' => $beneficiaries[$beneficiaryIndex]['email'] ?? null,
                                'phone' => $beneficiaries[$beneficiaryIndex]['phone'] ?? null,
                            ];
                        }
                        
                        Ticket::create([
                            'order_id' => $order->id,
                            'ticket_type_id' => $item['ticket_type_id'],
                            'code' => $this->generateTicketCode(),
                            'status' => 'pending',
                            'meta' => $ticketMeta,
                        ]);

                        $beneficiaryIndex++;
                    }
                }

                // Send order confirmation email (after transaction commits)
                $orderEmailService = app(OrderEmailService::class);
                $orderEmailService->sendOrderConfirmation($order, $tenant);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $order->id,
                        'total' => $totalCents / 100,
                        'currency' => $tenant->settings['currency'] ?? 'RON',
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate price with bulk discounts applied
     */
    private function calculateBulkDiscountPrice(int $quantity, int $pricePerTicket, array $discounts): int
    {
        $bestTotal = $quantity * $pricePerTicket;

        foreach ($discounts as $discount) {
            if ($discount['rule_type'] === 'buy_x_get_y' && $quantity >= $discount['buy_qty']) {
                $sets = floor($quantity / $discount['buy_qty']);
                $freeTickets = $sets * $discount['get_qty'];
                $paidTickets = $quantity - $freeTickets;
                $discountedTotal = $paidTickets * $pricePerTicket;
                $bestTotal = min($bestTotal, $discountedTotal);
            } elseif ($discount['rule_type'] === 'amount_off_per_ticket' && $quantity >= $discount['min_qty']) {
                $discountedTotal = ($quantity * $pricePerTicket) - ($quantity * $discount['amount_off']);
                $bestTotal = min($bestTotal, $discountedTotal);
            } elseif ($discount['rule_type'] === 'percent_off' && $quantity >= $discount['min_qty']) {
                $discountedTotal = ($quantity * $pricePerTicket) * (1 - $discount['percent_off'] / 100);
                $bestTotal = min($bestTotal, (int)$discountedTotal);
            }
        }

        return (int)$bestTotal;
    }

    /**
     * Generate unique ticket code
     */
    private function generateTicketCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get order details
     * SECURITY FIX: Added tenant_id verification to prevent IDOR
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        // SECURITY FIX: Must verify order belongs to current tenant
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json(['error' => 'Tenant context required'], 401);
        }

        $order = Order::with(['tickets.ticketType.event', 'customer'])
            ->where('tenant_id', $tenant->id)  // SECURITY: Enforce tenant isolation
            ->find($orderId);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'total' => $order->total_cents / 100,
                'customer_name' => $order->meta['customer_name'] ?? '',
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->meta['customer_phone'] ?? '',
                'items' => $order->meta['items'] ?? [],
                'created_at' => $order->created_at->toIso8601String(),
            ],
        ]);
    }
}
