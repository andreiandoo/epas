<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Shop\ShopOrder;
use App\Models\Shop\ShopDigitalDownload;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopOrderController extends Controller
{
    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();
            return $domain?->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function hasShopMicroservice(Tenant $tenant): bool
    {
        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * List customer orders
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->user()?->customer_id;
        $email = $request->query('email');

        if (!$customerId && !$email) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
        $page = $request->query('page', 1);
        $perPage = min($request->query('per_page', 10), 50);

        $query = ShopOrder::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $email, fn($q) => $q->where('customer_email', $email))
            ->with('items.product')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $orders = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $formattedOrders = $orders->map(fn($o) => $this->formatOrder($o, $tenantLanguage, false));

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $formattedOrders,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => (int) $page,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * Get single order
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->user()?->customer_id;
        $email = $request->query('email');

        $order = ShopOrder::where('tenant_id', $tenant->id)
            ->where('order_number', $orderNumber)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $email, fn($q) => $q->where('customer_email', $email))
            ->with('items.product')
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->formatOrder($order, $tenantLanguage, true),
            ],
        ]);
    }

    /**
     * Get digital downloads for an order
     */
    public function downloads(Request $request, string $orderNumber): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $customerId = $request->user()?->customer_id;
        $email = $request->query('email');

        $order = ShopOrder::where('tenant_id', $tenant->id)
            ->where('order_number', $orderNumber)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $email, fn($q) => $q->where('customer_email', $email))
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order is not paid yet',
            ], 400);
        }

        $downloads = ShopDigitalDownload::where('order_id', $order->id)
            ->with('product')
            ->get()
            ->map(function ($download) use ($tenant) {
                $lang = $tenant->language ?? 'en';
                $title = is_array($download->product->title)
                    ? ($download->product->title[$lang] ?? '')
                    : $download->product->title;

                return [
                    'id' => $download->id,
                    'product_title' => $title,
                    'file_name' => $download->file_name,
                    'download_count' => $download->download_count,
                    'max_downloads' => $download->max_downloads,
                    'downloads_remaining' => $download->max_downloads
                        ? max(0, $download->max_downloads - $download->download_count)
                        : null,
                    'expires_at' => $download->expires_at?->toISOString(),
                    'is_expired' => $download->isExpired(),
                    'can_download' => $download->canDownload(),
                    'download_url' => $download->canDownload()
                        ? route('shop.download', ['token' => $download->download_token])
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'downloads' => $downloads,
            ],
        ]);
    }

    /**
     * Download a digital product
     */
    public function download(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $download = ShopDigitalDownload::where('download_token', $token)->first();

        if (!$download) {
            abort(404, 'Download not found');
        }

        if (!$download->canDownload()) {
            abort(403, 'Download limit exceeded or expired');
        }

        $product = $download->product;

        if (!$product || !$product->digital_file_url) {
            abort(404, 'File not found');
        }

        // Increment download count
        $download->incrementDownloadCount();

        // Return file download
        $fileUrl = $product->digital_file_url;

        // If it's an external URL, validate domain before redirect to prevent open redirect
        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            // SECURITY FIX: Only allow redirects to trusted domains
            $parsedUrl = parse_url($fileUrl);
            $allowedHosts = array_filter(explode(',', config('app.trusted_download_domains', '')));
            $host = $parsedUrl['host'] ?? '';

            if (empty($allowedHosts) || !in_array($host, $allowedHosts)) {
                // If no trusted domains configured or domain not trusted, serve via proxy or block
                \Log::warning('Digital download redirect blocked: untrusted domain', [
                    'url' => $fileUrl,
                    'host' => $host,
                ]);
                abort(403, 'External download URL not allowed. Configure trusted_download_domains.');
            }

            return redirect($fileUrl);
        }

        // If it's a local file
        if (Storage::disk('public')->exists($fileUrl)) {
            return Storage::disk('public')->download($fileUrl, $download->file_name);
        }

        abort(404, 'File not found');
    }

    private function formatOrder(ShopOrder $order, string $language, bool $detailed = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'subtotal_cents' => $order->subtotal_cents,
            'subtotal' => $order->subtotal_cents / 100,
            'shipping_cents' => $order->shipping_cents,
            'shipping' => $order->shipping_cents / 100,
            'discount_cents' => $order->discount_cents,
            'discount' => $order->discount_cents / 100,
            'tax_cents' => $order->tax_cents,
            'tax' => $order->tax_cents / 100,
            'total_cents' => $order->total_cents,
            'total' => $order->total_cents / 100,
            'currency' => $order->currency,
            'item_count' => $order->items->count(),
            'created_at' => $order->created_at->toISOString(),
        ];

        if ($detailed) {
            $data['customer_email'] = $order->customer_email;
            $data['billing_address'] = $order->billing_address;
            $data['shipping_address'] = $order->shipping_address;
            $data['shipping_method'] = $order->shipping_method;
            $data['tracking_number'] = $order->tracking_number;
            $data['tracking_url'] = $order->tracking_url;
            $data['coupon_code'] = $order->coupon_code;
            $data['paid_at'] = $order->paid_at?->toISOString();
            $data['shipped_at'] = $order->shipped_at?->toISOString();
            $data['delivered_at'] = $order->delivered_at?->toISOString();

            $data['items'] = $order->items->map(function ($item) use ($language) {
                $title = is_array($item->product?->title)
                    ? ($item->product->title[$language] ?? '')
                    : ($item->product?->title ?? $item->product_name ?? '');

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_slug' => $item->product?->slug,
                    'title' => $title,
                    'variant_name' => $item->variant_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'unit_price' => $item->unit_price_cents / 100,
                    'total_cents' => $item->total_cents,
                    'total' => $item->total_cents / 100,
                    'type' => $item->product?->type,
                ];
            });

            // Check if has digital products
            $data['has_digital_products'] = $order->items->contains(fn($i) => $i->product?->type === 'digital');
        }

        return $data;
    }
}
