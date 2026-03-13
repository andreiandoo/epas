<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCoupon;
use App\Models\AffiliateConversion;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AffiliateTrackingService
{
    /**
     * Track an affiliate click and set cookie
     */
    public function trackClick(array $data): array
    {
        $tenant = Tenant::find($data['tenant_id']);
        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        // Find affiliate by code
        $affiliate = Affiliate::where('code', $data['affiliate_code'])
            ->where('tenant_id', $tenant->id)
            ->active()
            ->first();

        if (!$affiliate) {
            throw new \Exception('Affiliate not found or inactive');
        }

        // Create click record
        $click = AffiliateClick::create([
            'affiliate_id' => $affiliate->id,
            'tenant_id' => $tenant->id,
            'ip_hash' => $this->hashIp($data['ip'] ?? null),
            'user_agent' => $data['user_agent'] ?? null,
            'referer' => $data['referer'] ?? null,
            'landing_url' => $data['url'] ?? null,
            'utm_params' => $data['utm'] ?? null,
            'clicked_at' => now(),
        ]);

        // Get configuration from tenant_microservice pivot
        $config = $this->getAffiliateConfig($tenant);

        return [
            'success' => true,
            'click_id' => $click->id,
            'affiliate_code' => $affiliate->code,
            'cookie_name' => $config['cookie_name'],
            'cookie_duration_days' => $config['cookie_duration_days'],
            'cookie_value' => json_encode([
                'affiliate_code' => $affiliate->code,
                'click_id' => $click->id,
                'timestamp' => now()->timestamp,
            ]),
        ];
    }

    /**
     * Attribute an order to an affiliate
     */
    public function attributeOrder(array $data): ?array
    {
        $tenant = Tenant::find($data['tenant_id']);
        if (!$tenant) {
            return null;
        }

        $config = $this->getAffiliateConfig($tenant);
        $affiliateCode = null;
        $attributedBy = null;

        // Priority 1: Coupon (if provided and exists)
        if (!empty($data['coupon_code'])) {
            $coupon = AffiliateCoupon::where('coupon_code', $data['coupon_code'])
                ->active()
                ->whereHas('affiliate', function ($query) use ($tenant) {
                    $query->where('tenant_id', $tenant->id)->active();
                })
                ->first();

            if ($coupon) {
                $affiliateCode = $coupon->affiliate->code;
                $attributedBy = 'coupon';
            }
        }

        // Priority 2: Last-click from cookie (within window)
        if (!$affiliateCode && !empty($data['cookie_value'])) {
            try {
                $cookieData = json_decode($data['cookie_value'], true);

                if ($cookieData && isset($cookieData['affiliate_code'], $cookieData['timestamp'])) {
                    $clickedAt = Carbon::createFromTimestamp($cookieData['timestamp']);
                    $windowDays = $config['cookie_duration_days'];

                    // Check if click is within attribution window
                    if ($clickedAt->diffInDays(now()) <= $windowDays) {
                        // Verify affiliate still exists and is active
                        $affiliate = Affiliate::where('code', $cookieData['affiliate_code'])
                            ->where('tenant_id', $tenant->id)
                            ->active()
                            ->first();

                        if ($affiliate) {
                            $affiliateCode = $cookieData['affiliate_code'];
                            $attributedBy = 'link';
                        }
                    }
                }
            } catch (\Exception $e) {
                // Invalid cookie data, ignore
            }
        }

        if (!$affiliateCode) {
            return null; // No attribution
        }

        $affiliate = Affiliate::where('code', $affiliateCode)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$affiliate) {
            return null;
        }

        // Calculate commission using affiliate's own rate (not global config)
        $orderAmount = $data['order_amount'] ?? 0;
        $commission = $this->calculateCommissionForAffiliate($orderAmount, $affiliate);

        return [
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'affiliate_name' => $affiliate->name,
            'attributed_by' => $attributedBy,
            'commission_value' => $commission['value'],
            'commission_type' => $commission['type'],
            'order_amount' => $orderAmount,
        ];
    }

    /**
     * Confirm order and create conversion record
     */
    public function confirmOrder(array $data): ?AffiliateConversion
    {
        $tenant = Tenant::find($data['tenant_id']);
        if (!$tenant) {
            return null;
        }

        // Get attribution
        $attribution = $this->attributeOrder([
            'tenant_id' => $data['tenant_id'],
            'coupon_code' => $data['coupon_code'] ?? null,
            'cookie_value' => $data['cookie_value'] ?? null,
            'order_amount' => $data['order_amount'] ?? 0,
        ]);

        if (!$attribution) {
            return null; // No affiliate to attribute to
        }

        $config = $this->getAffiliateConfig($tenant);

        // Self-purchase guard
        if ($config['self_purchase_guard'] ?? false) {
            $buyerEmail = $data['buyer_email'] ?? null;
            $affiliate = Affiliate::find($attribution['affiliate_id']);

            if ($buyerEmail && $affiliate && $buyerEmail === $affiliate->contact_email) {
                // Self-purchase detected - mark for review or ignore
                return null;
            }
        }

        // Check for duplicate (dedup)
        $existing = AffiliateConversion::where('tenant_id', $tenant->id)
            ->where('order_ref', $data['order_ref'])
            ->first();

        if ($existing) {
            return $existing; // Already tracked
        }

        // Create conversion record
        $conversion = AffiliateConversion::create([
            'tenant_id' => $tenant->id,
            'affiliate_id' => $attribution['affiliate_id'],
            'order_ref' => $data['order_ref'],
            'amount' => $data['order_amount'] ?? 0,
            'commission_value' => $attribution['commission_value'],
            'commission_type' => $attribution['commission_type'],
            'status' => 'pending',
            'attributed_by' => $attribution['attributed_by'],
            'click_ref' => $data['click_ref'] ?? null,
            'meta' => [
                'buyer_email' => $data['buyer_email'] ?? null,
                'created_by' => 'system',
            ],
        ]);

        return $conversion;
    }

    /**
     * Approve conversion (when payment is captured)
     */
    public function approveConversion(string $orderRef, int $tenantId): ?AffiliateConversion
    {
        $conversion = AffiliateConversion::where('order_ref', $orderRef)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->first();

        if ($conversion) {
            $conversion->update(['status' => 'approved']);
        }

        return $conversion;
    }

    /**
     * Reverse conversion (on refund or chargeback)
     */
    public function reverseConversion(string $orderRef, int $tenantId): ?AffiliateConversion
    {
        $conversion = AffiliateConversion::where('order_ref', $orderRef)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($conversion) {
            $conversion->update(['status' => 'reversed']);
        }

        return $conversion;
    }

    /**
     * Get affiliate statistics
     */
    public function getAffiliateStats(int $affiliateId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = AffiliateConversion::where('affiliate_id', $affiliateId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $stats = [
            'total_conversions' => $query->count(),
            'pending_conversions' => (clone $query)->pending()->count(),
            'approved_conversions' => (clone $query)->approved()->count(),
            'reversed_conversions' => (clone $query)->reversed()->count(),
            'total_commission' => (clone $query)->approved()->sum('commission_value'),
            'pending_commission' => (clone $query)->pending()->sum('commission_value'),
            'total_sales' => (clone $query)->approved()->sum('amount'),
        ];

        return $stats;
    }

    /**
     * Get tenant statistics for all affiliates
     */
    public function getTenantStats(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = AffiliateConversion::where('tenant_id', $tenantId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $stats = [
            'total_affiliates' => Affiliate::where('tenant_id', $tenantId)->active()->count(),
            'total_conversions' => $query->count(),
            'pending_conversions' => (clone $query)->pending()->count(),
            'approved_conversions' => (clone $query)->approved()->count(),
            'reversed_conversions' => (clone $query)->reversed()->count(),
            'total_commission' => (clone $query)->approved()->sum('commission_value'),
            'pending_commission' => (clone $query)->pending()->sum('commission_value'),
            'total_sales' => (clone $query)->approved()->sum('amount'),
        ];

        return $stats;
    }

    /**
     * Calculate commission based on affiliate's own rate
     */
    protected function calculateCommissionForAffiliate(float $amount, Affiliate $affiliate): array
    {
        $type = $affiliate->commission_type ?? 'percent';
        $rate = $affiliate->commission_rate ?? 10;

        if ($type === 'fixed') {
            return [
                'type' => 'fixed',
                'value' => $rate,
            ];
        }

        // Percent
        $commissionAmount = ($amount * $rate) / 100;

        return [
            'type' => 'percent',
            'value' => round($commissionAmount, 2),
        ];
    }

    /**
     * Calculate commission based on config (fallback for global settings)
     */
    protected function calculateCommission(float $amount, array $config): array
    {
        $type = $config['commission_type'] ?? 'percent';
        $value = $config['commission_value'] ?? 0;

        if ($type === 'fixed') {
            return [
                'type' => 'fixed',
                'value' => $value,
            ];
        }

        // Percent
        $commissionAmount = ($amount * $value) / 100;

        return [
            'type' => 'percent',
            'value' => round($commissionAmount, 2),
        ];
    }

    /**
     * Get affiliate tracking configuration for tenant
     */
    protected function getAffiliateConfig(Tenant $tenant): array
    {
        // Get microservice configuration from pivot table
        $microservice = $tenant->microservices()
            ->where('slug', 'affiliate-tracking')
            ->first();

        $config = $microservice?->pivot->configuration ?? [];

        // Default configuration
        return array_merge([
            'cookie_name' => 'aff_ref',
            'cookie_duration_days' => 90,
            'commission_type' => 'percent',
            'commission_value' => 10.00,
            'self_purchase_guard' => true,
            'exclude_taxes_from_commission' => true,
        ], $config);
    }

    /**
     * Hash IP address for privacy
     */
    protected function hashIp(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        return hash('sha256', $ip . config('app.key'));
    }
}
