<?php

namespace App\Filament\Marketplace\Concerns;

use App\Models\MarketplaceClient;

trait HasMarketplaceContext
{
    /**
     * Get the current marketplace client
     */
    public static function getMarketplaceClient(): ?MarketplaceClient
    {
        // Try marketplace_admin guard first (works for both marketplace admins and super-admins via middleware)
        $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();

        if ($admin && method_exists($admin, 'marketplaceClient')) {
            $client = $admin->marketplaceClient;
            if ($client) {
                return $client;
            }
        }

        // Fallback to default auth guard
        $user = auth()->user();

        if ($user && method_exists($user, 'marketplaceClient')) {
            return $user->marketplaceClient;
        }

        return null;
    }

    /**
     * Get the marketplace client ID
     */
    protected static function getMarketplaceClientId(): ?int
    {
        return static::getMarketplaceClient()?->id;
    }

    /**
     * Check if marketplace has a specific microservice enabled
     */
    protected static function marketplaceHasMicroservice(string $slug): bool
    {
        $client = static::getMarketplaceClient();

        if (!$client) {
            return false;
        }

        return $client->hasMicroservice($slug);
    }

    /**
     * Expand country names to include ISO code variants and vice versa.
     * e.g. ["Romania"] → ["Romania", "RO", "ro"]
     */
    protected static function expandCountryVariants(array $countries): array
    {
        if (empty($countries)) return [];

        $map = [
            'Romania' => ['RO', 'ro', 'România'],
            'Germany' => ['DE', 'de', 'Deutschland'],
            'France' => ['FR', 'fr'],
            'Spain' => ['ES', 'es', 'España'],
            'Italy' => ['IT', 'it', 'Italia'],
            'United Kingdom' => ['GB', 'gb', 'UK', 'uk'],
            'United States' => ['US', 'us', 'USA'],
            'Austria' => ['AT', 'at', 'Österreich'],
            'Hungary' => ['HU', 'hu', 'Magyarország'],
            'Bulgaria' => ['BG', 'bg'],
            'Moldova' => ['MD', 'md'],
            'Serbia' => ['RS', 'rs'],
            'Ukraine' => ['UA', 'ua'],
        ];

        // Build reverse map (ISO → full name)
        $reverseMap = [];
        foreach ($map as $name => $codes) {
            foreach ($codes as $code) {
                $reverseMap[strtoupper($code)] = $name;
            }
        }

        $expanded = [];
        foreach ($countries as $country) {
            $expanded[] = $country;

            // If it's a full name, add ISO codes
            if (isset($map[$country])) {
                foreach ($map[$country] as $variant) {
                    $expanded[] = $variant;
                }
            }

            // If it's an ISO code, add full name + other variants
            $upper = strtoupper($country);
            if (isset($reverseMap[$upper])) {
                $fullName = $reverseMap[$upper];
                $expanded[] = $fullName;
                foreach ($map[$fullName] ?? [] as $variant) {
                    $expanded[] = $variant;
                }
            }
        }

        return array_unique($expanded);
    }
}
