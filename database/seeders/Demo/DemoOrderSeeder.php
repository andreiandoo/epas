<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use App\Models\FestivalAddonPurchase;
use App\Models\FestivalPassPurchase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Carbon\Carbon;

class DemoOrderSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $event = $this->parent->refs['event'];
        $ticketTypes = $this->parent->refs['ticketTypes'];
        $customers = $this->parent->refs['customers'];
        $passes = $this->parent->refs['passes'];
        $addons = $this->parent->refs['addons'];

        // Build ticket type array indexed by short name
        $ttNames = array_keys($ticketTypes);
        $ticketSeq = 1;

        // ── Order definitions ──
        // [customer_idx, status, items[[tt_name, qty]], days_ago_from_event]
        $orderDefs = [
            // 30 completed orders
            [0, 'completed', [['General Access 4-Day', 2]], 65],
            [1, 'completed', [['General Access 4-Day', 1], ['Camping Standard', 1]], 60],
            [2, 'completed', [['VIP 4-Day', 2]], 58],
            [3, 'completed', [['General Access 1-Day', 3]], 55],
            [4, 'completed', [['General Access 4-Day', 1], ['Camping Premium', 1]], 53],
            [5, 'completed', [['General Access 4-Day', 2], ['Parking', 1]], 50],
            [6, 'completed', [['VIP 1-Day', 1]], 48],
            [7, 'completed', [['General Access 4-Day', 1]], 45],
            [8, 'completed', [['Early Bird 4-Day', 2]], 100],
            [9, 'completed', [['General Access 4-Day', 4]], 42],
            [10, 'completed', [['General Access 1-Day', 2]], 40],
            [11, 'completed', [['VIP 4-Day', 1], ['Camping Premium', 1]], 38],
            [12, 'completed', [['General Access 4-Day', 2], ['Camping Standard', 2]], 35],
            [13, 'completed', [['General Access 4-Day', 1]], 33],
            [14, 'completed', [['General Access 1-Day', 1]], 30],
            [15, 'completed', [['VIP 4-Day', 2], ['Parking', 1]], 28],
            [16, 'completed', [['General Access 4-Day', 3]], 25],
            [17, 'completed', [['General Access 4-Day', 1], ['Camping Standard', 1]], 22],
            [18, 'completed', [['General Access 1-Day', 2]], 20],
            [19, 'completed', [['VIP 1-Day', 2]], 18],
            [0, 'completed', [['General Access 1-Day', 1]], 15],  // Ana orders again
            [1, 'completed', [['Parking', 1]], 14],
            [2, 'completed', [['Camping Standard', 1]], 12],
            [3, 'completed', [['General Access 4-Day', 2]], 10],
            [4, 'completed', [['General Access 4-Day', 1]], 8],
            [5, 'completed', [['General Access 1-Day', 1]], 7],
            [6, 'completed', [['General Access 4-Day', 2]], 5],
            [7, 'completed', [['VIP 4-Day', 1]], 4],
            [8, 'completed', [['General Access 4-Day', 1], ['Parking', 1]], 3],
            [9, 'completed', [['General Access 1-Day', 1]], 2],
            // 5 pending
            [10, 'pending', [['General Access 4-Day', 1]], 1],
            [11, 'pending', [['VIP 1-Day', 1]], 1],
            [12, 'pending', [['General Access 4-Day', 2]], 1],
            [13, 'pending', [['Camping Standard', 1]], 1],
            [14, 'pending', [['General Access 1-Day', 1]], 1],
            // 3 cancelled
            [15, 'cancelled', [['General Access 4-Day', 1]], 20],
            [16, 'cancelled', [['VIP 4-Day', 1]], 15],
            [17, 'cancelled', [['General Access 1-Day', 2]], 10],
            // 2 refunded
            [18, 'refunded', [['VIP 4-Day', 1]], 25],
            [19, 'refunded', [['General Access 4-Day', 2]], 20],
            // 5 anonymous orders (no customer record)
            [null, 'completed', [['General Access 4-Day', 1]], 40],
            [null, 'completed', [['General Access 1-Day', 2]], 35],
            [null, 'completed', [['General Access 4-Day', 1], ['Parking', 1]], 30],
            [null, 'completed', [['General Access 1-Day', 1]], 25],
            [null, 'completed', [['General Access 4-Day', 2]], 20],
        ];

        $anonNames = [
            'Gheorghe Anonim', 'Vasile Necunoscut', 'Marian Fara-Email',
            'Ionut TestClient', 'Daniel Vizitator',
        ];
        $anonIdx = 0;

        $orders = [];
        $allTickets = [];

        foreach ($orderDefs as $i => $def) {
            [$custIdx, $status, $items, $daysAgo] = $def;
            $orderNum = sprintf('DEMO-AF26-%04d', $i + 1);

            $existing = Order::where('order_number', $orderNum)->first();
            if ($existing) {
                $orders[] = $existing;
                continue;
            }

            $customer = $custIdx !== null ? $customers[$custIdx] : null;
            $orderDate = Carbon::parse('2026-07-15')->subDays($daysAgo);

            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $tt = $ticketTypes[$item[0]] ?? null;
                if ($tt) {
                    $subtotal += ($tt->price_cents / 100) * $item[1];
                }
            }
            $commissionAmount = round($subtotal * 0.05, 2);

            $orderData = [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'order_number' => $orderNum,
                'source' => 'direct',
                'status' => $status,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'commission_rate' => 5.00,
                'commission_amount' => $commissionAmount,
                'currency' => 'RON',
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
                'meta' => ['demo' => true, 'seeder' => 'FestivalDemoSeeder'],
            ];

            if ($customer) {
                $orderData['customer_id'] = $customer->id;
                $orderData['customer_email'] = $customer->email;
                $orderData['customer_name'] = $customer->full_name ?? ($customer->first_name . ' ' . $customer->last_name);
                $orderData['customer_phone'] = $customer->phone;
            } else {
                $name = $anonNames[$anonIdx % count($anonNames)];
                $anonIdx++;
                $orderData['customer_name'] = $name;
                $orderData['customer_email'] = 'demo-anon-' . $anonIdx . '@noemail.local';
                $orderData['customer_phone'] = '+4074100' . sprintf('%04d', 50 + $anonIdx);
            }

            if (in_array($status, ['completed', 'paid'])) {
                $orderData['payment_status'] = 'paid';
                $orderData['paid_at'] = $orderDate;
                $orderData['status'] = 'completed';
            } elseif ($status === 'cancelled') {
                $orderData['cancelled_at'] = $orderDate->copy()->addHours(2);
            } elseif ($status === 'refunded') {
                $orderData['payment_status'] = 'refunded';
                $orderData['paid_at'] = $orderDate;
                $orderData['refunded_at'] = $orderDate->copy()->addDays(3);
                $orderData['refund_amount'] = $subtotal;
            }

            $order = Order::create($orderData);
            $orders[] = $order;

            // ── Order Items + Tickets ──
            foreach ($items as $item) {
                $tt = $ticketTypes[$item[0]] ?? null;
                if (! $tt) continue;

                $qty = $item[1];
                $unitPrice = $tt->price_cents / 100;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $tt->id,
                    'name' => $item[0],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice * $qty,
                ]);

                for ($t = 0; $t < $qty; $t++) {
                    $ticketStatus = match ($status) {
                        'completed' => 'valid',
                        'cancelled' => 'cancelled',
                        'refunded' => 'cancelled',
                        default => 'pending',
                    };

                    $code = sprintf('AF26-D%05d', $ticketSeq++);
                    $checkedIn = ($ticketStatus === 'valid' && $ticketSeq % 5 === 0) ? $orderDate->copy()->addDays($daysAgo)->setHour(rand(14, 22)) : null;

                    $ticket = Ticket::create([
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'ticket_type_id' => $tt->id,
                        'event_id' => $event->id,
                        'tenant_id' => $tenantId,
                        'code' => $code,
                        'barcode' => $code,
                        'status' => $ticketStatus,
                        'price' => $unitPrice,
                        'attendee_name' => $customer ? ($customer->first_name . ' ' . $customer->last_name) : ($orderData['customer_name'] ?? null),
                        'attendee_email' => $customer?->email,
                        'checked_in_at' => $checkedIn,
                        'meta' => ['demo' => true, 'fiscal_series' => sprintf('DEMO%08d', $ticketSeq)],
                    ]);

                    $allTickets[] = $ticket;
                }
            }
        }

        $this->parent->refs['orders'] = $orders;
        $this->parent->refs['tickets'] = $allTickets;

        // ── Festival Pass Purchases (first 20 completed orders with full pass customers) ──
        $passPurchases = [];
        $completedOrders = collect($orders)->filter(fn ($o) => $o->status === 'completed' && $o->customer_id);
        $fullPass = $passes['demo-full-festival'] ?? null;

        if ($fullPass) {
            foreach ($completedOrders->take(20) as $idx => $order) {
                $pp = FestivalPassPurchase::firstOrCreate(
                    ['festival_pass_id' => $fullPass->id, 'customer_id' => $order->customer_id, 'tenant_id' => $tenantId],
                    [
                        'order_id' => $order->id,
                        'code' => sprintf('FEST-DEMO-%06d', $idx + 1),
                        'holder_name' => $order->customer_name,
                        'holder_email' => $order->customer_email,
                        'status' => 'active',
                        'activated_at' => $order->created_at,
                    ]
                );
                $passPurchases[] = $pp;
            }
        }
        $this->parent->refs['passPurchases'] = $passPurchases;

        // ── Festival Addon Purchases ──
        $campingStd = $addons['demo-camping-standard'] ?? null;
        $campingPrem = $addons['demo-camping-premium'] ?? null;
        $locker = $addons['demo-locker-rental'] ?? null;

        $addonPurchases = [];
        if ($campingStd && count($passPurchases) >= 5) {
            for ($i = 0; $i < 5; $i++) {
                $pp = $passPurchases[$i];
                $addonPurchases[] = FestivalAddonPurchase::firstOrCreate(
                    ['festival_addon_id' => $campingStd->id, 'customer_id' => $pp->customer_id, 'tenant_id' => $tenantId],
                    [
                        'order_id' => $pp->order_id,
                        'festival_pass_purchase_id' => $pp->id,
                        'code' => sprintf('ADD-DEMO-%06d', $i + 1),
                        'quantity' => 1,
                        'price_cents_paid' => $campingStd->price_cents,
                        'currency' => 'RON',
                        'status' => 'active',
                    ]
                );
            }
        }
        if ($campingPrem && count($passPurchases) >= 8) {
            for ($i = 5; $i < 8; $i++) {
                $pp = $passPurchases[$i];
                $addonPurchases[] = FestivalAddonPurchase::firstOrCreate(
                    ['festival_addon_id' => $campingPrem->id, 'customer_id' => $pp->customer_id, 'tenant_id' => $tenantId],
                    [
                        'order_id' => $pp->order_id,
                        'festival_pass_purchase_id' => $pp->id,
                        'code' => sprintf('ADD-DEMO-%06d', $i + 1),
                        'quantity' => 1,
                        'price_cents_paid' => $campingPrem->price_cents,
                        'currency' => 'RON',
                        'status' => 'active',
                    ]
                );
            }
        }
        if ($locker && count($passPurchases) >= 12) {
            for ($i = 8; $i < 12; $i++) {
                $pp = $passPurchases[$i];
                $addonPurchases[] = FestivalAddonPurchase::firstOrCreate(
                    ['festival_addon_id' => $locker->id, 'customer_id' => $pp->customer_id, 'tenant_id' => $tenantId],
                    [
                        'order_id' => $pp->order_id,
                        'festival_pass_purchase_id' => $pp->id,
                        'code' => sprintf('ADD-DEMO-%06d', $i + 1),
                        'quantity' => 1,
                        'price_cents_paid' => $locker->price_cents,
                        'currency' => 'RON',
                        'status' => 'active',
                    ]
                );
            }
        }
        $this->parent->refs['addonPurchases'] = $addonPurchases;
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;

        $orderIds = Order::where('tenant_id', $tenantId)->where('order_number', 'like', 'DEMO-AF26-%')->pluck('id');

        FestivalAddonPurchase::where('tenant_id', $tenantId)->where('code', 'like', 'ADD-DEMO-%')->delete();
        FestivalPassPurchase::where('tenant_id', $tenantId)->where('code', 'like', 'FEST-DEMO-%')->delete();
        Ticket::where('tenant_id', $tenantId)->where('code', 'like', 'AF26-D%')->delete();
        OrderItem::whereIn('order_id', $orderIds)->delete();
        Order::whereIn('id', $orderIds)->delete();
    }
}
