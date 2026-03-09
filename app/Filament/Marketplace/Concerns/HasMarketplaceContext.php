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
}
