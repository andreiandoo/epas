<?php

namespace App\Http\Controllers\Api\TenantApp;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant orders (read). POS door-sale creation is intentionally NOT wired here
 * yet — it should reuse App\Services\DoorSales\DoorSalesService (already keyed
 * on tenant_id) once validated against a real environment. See the plan doc.
 */
class OrdersController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'orders.view');
        $tid = $this->tenantId($request);
        $perPage = min((int) $request->integer('per_page', 30) ?: 30, 100);

        $query = Order::where('tenant_id', $tid)->orderByDesc('created_at');
        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $orders = $query->paginate($perPage);

        return $this->paginated($orders, fn (Order $o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'total' => (float) $o->total,
            'currency' => $o->currency ?? 'RON',
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'source' => $o->source,
            'customer_name' => $o->customer_name,
            'created_at' => optional($o->created_at)->toIso8601String(),
        ]);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        $this->requirePermission($request, 'orders.view');
        $tid = $this->tenantId($request);

        $order = Order::where('tenant_id', $tid)
            ->with(['tickets'])
            ->find($orderId);

        if (! $order) {
            return $this->error('Comandă inexistentă.', 404);
        }

        return $this->success([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'currency' => $order->currency ?? 'RON',
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'source' => $order->source,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'created_at' => optional($order->created_at)->toIso8601String(),
            'tickets' => $order->tickets->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'barcode' => $t->barcode,
                'status' => $t->status,
                'seat_label' => $t->seat_label,
                'checked_in_at' => optional($t->checked_in_at)->toIso8601String(),
            ]),
        ]);
    }
}
