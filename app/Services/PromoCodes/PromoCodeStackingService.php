<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;

/**
 * Handle promo code stacking/combination logic
 */
class PromoCodeStackingService
{
    public function __construct(
        protected PromoCodeValidator $validator,
        protected PromoCodeCalculator $calculator
    ) {}

    /**
     * Validate and calculate stacked promo codes
     *
     * @param array $promoCodes Array of promo code objects
     * @param array $cart
     * @param string|null $customerId
     * @return array
     */
    public function stackCodes(array $promoCodes, array $cart, ?string $customerId = null): array
    {
        $stackingAllowed = config('microservices.promo_codes.allow_stacking', false);

        if (!$stackingAllowed) {
            return [
                'valid' => false,
                'reason' => 'Promo code stacking is not allowed',
            ];
        }

        // Check if codes can be combined
        foreach ($promoCodes as $code) {
            if (!$code['combinable']) {
                return [
                    'valid' => false,
                    'reason' => "Promo code '{$code['code']}' cannot be combined with other codes",
                ];
            }
        }

        // Check exclusion rules
        foreach ($promoCodes as $i => $code) {
            $excludeIds = $code['exclude_combinations'] ? json_decode($code['exclude_combinations'], true) : [];

            foreach ($promoCodes as $j => $otherCode) {
                if ($i !== $j && in_array($otherCode['id'], $excludeIds)) {
                    return [
                        'valid' => false,
                        'reason' => "Codes '{$code['code']}' and '{$otherCode['code']}' cannot be combined",
                    ];
                }
            }
        }

        // Validate each code individually
        foreach ($promoCodes as $code) {
            $validation = $this->validator->validate($code, $cart, $customerId);

            if (!$validation['valid']) {
                return [
                    'valid' => false,
                    'reason' => "Code '{$code['code']}': {$validation['reason']}",
                ];
            }
        }

        // Calculate combined discount
        $totalDiscount = 0;
        $appliedCodes = [];

        foreach ($promoCodes as $code) {
            $calculation = $this->calculator->calculate($code, $cart);
            $totalDiscount += $calculation['discount_amount'];
            $appliedCodes[] = [
                'code' => $code['code'],
                'discount' => $calculation['discount_amount'],
            ];
        }

        $finalAmount = max(0, $cart['total'] - $totalDiscount);

        return [
            'valid' => true,
            'total_discount' => $totalDiscount,
            'final_amount' => $finalAmount,
            'applied_codes' => $appliedCodes,
        ];
    }
}
