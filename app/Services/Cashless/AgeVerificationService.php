<?php

namespace App\Services\Cashless;

use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSettings;
use App\Models\Customer;
use App\Models\VendorProduct;
use Illuminate\Support\Collection;

class AgeVerificationService
{
    /**
     * Verify that the account holder meets age requirements for the given products.
     *
     * @param Collection<int, VendorProduct> $products
     * @throws \InvalidArgumentException if age verification fails
     */
    public function verifyForProducts(CashlessAccount $account, Collection $products, int $editionId): void
    {
        $settings = CashlessSettings::forEdition($editionId);

        if (! $settings || ! $settings->enforce_age_verification) {
            return;
        }

        $ageRestrictedProducts = $products->filter(fn (VendorProduct $p) => $p->is_age_restricted);

        if ($ageRestrictedProducts->isEmpty()) {
            return;
        }

        $customer = Customer::find($account->customer_id);

        if (! $customer) {
            throw new \InvalidArgumentException('Customer not found. Cannot verify age.');
        }

        $maxRequiredAge = $ageRestrictedProducts->max('min_age') ?? 18;

        $method = $settings->age_verification_method;

        if ($method === 'date_of_birth' || $method === 'both') {
            $this->verifyByDateOfBirth($customer, $maxRequiredAge);
        }

        if ($method === 'manual_id' || $method === 'both') {
            $this->verifyByIdCheck($customer);
        }
    }

    /**
     * Verify age using date_of_birth field on Customer.
     */
    private function verifyByDateOfBirth(Customer $customer, int $requiredAge): void
    {
        if (! $customer->date_of_birth) {
            throw new \InvalidArgumentException(
                'Date of birth not set. Age-restricted products require age verification.'
            );
        }

        $age = $customer->date_of_birth->age;

        if ($age < $requiredAge) {
            throw new \InvalidArgumentException(
                "Customer is {$age} years old. Minimum age required: {$requiredAge}."
            );
        }
    }

    /**
     * Verify that manual ID check has been performed.
     */
    private function verifyByIdCheck(Customer $customer): void
    {
        if (! $customer->id_verified) {
            throw new \InvalidArgumentException(
                'ID verification required for age-restricted products. Please verify ID first.'
            );
        }
    }

    /**
     * Mark a customer's ID as verified.
     */
    public function markIdVerified(Customer $customer, string $method = 'manual_id'): void
    {
        $customer->update([
            'id_verified'            => true,
            'id_verified_at'         => now(),
            'id_verification_method' => $method,
        ]);
    }
}
