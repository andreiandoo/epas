<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Customer;
use App\Models\CustomerToken;
use Illuminate\Http\Request;

trait ResolvesCustomer
{
    /**
     * Rezolvă clientul autentificat din bearer token (CustomerToken sha256).
     */
    protected function resolveCustomer(Request $request): ?Customer
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $customerToken = CustomerToken::where('token', hash('sha256', $token))
            ->with('customer')
            ->first();

        if (! $customerToken || (method_exists($customerToken, 'isExpired') && $customerToken->isExpired())) {
            return null;
        }

        if (method_exists($customerToken, 'markAsUsed')) {
            $customerToken->markAsUsed();
        }

        return $customerToken->customer;
    }
}
