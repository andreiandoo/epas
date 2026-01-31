<?php

namespace App\Http\Middleware;

use App\Services\PromoCodes\PromoCodeValidator;
use App\Services\PromoCodes\PromoCodeCalculator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically apply the best available promo code for the customer
 */
class AutoApplyBestPromoCode
{
    public function __construct(
        protected PromoCodeValidator $validator,
        protected PromoCodeCalculator $calculator
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply on checkout/cart endpoints with tenant context
        if (!$request->has('tenant_id') || !$request->has('cart')) {
            return $next($request);
        }

        $tenantId = $request->input('tenant_id');
        $cart = $request->input('cart');
        $customerId = $request->input('customer_id');

        // Skip if user already provided a code
        if ($request->has('promo_code')) {
            return $next($request);
        }

        // Find all applicable promo codes for this tenant
        $availableCodes = DB::table('promo_codes')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) use ($customerId) {
                // Include public codes or customer-specific codes
                $query->where('is_public', true)
                    ->orWhere('customer_id', $customerId);
            })
            ->get();

        $bestCode = null;
        $bestDiscount = 0;

        foreach ($availableCodes as $code) {
            $codeArray = (array) $code;

            $validation = $this->validator->validate($codeArray, $cart, $customerId);

            if ($validation['valid']) {
                $calculation = $this->calculator->calculate($codeArray, $cart);

                if ($calculation['discount_amount'] > $bestDiscount) {
                    $bestDiscount = $calculation['discount_amount'];
                    $bestCode = $codeArray;
                }
            }
        }

        // Auto-apply the best code to the request
        if ($bestCode) {
            $request->merge([
                'promo_code' => $bestCode['code'],
                'auto_applied' => true,
            ]);
        }

        return $next($request);
    }
}
