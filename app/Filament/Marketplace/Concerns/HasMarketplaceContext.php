<?php

namespace App\Filament\Marketplace\Concerns;

use App\Models\MarketplaceClient;

trait HasMarketplaceContext
{
    /**
     * Get the current marketplace client
     */
    protected static function getMarketplaceClient(): ?MarketplaceClient
    {
        $user = auth()->user();

        if (!$user || !method_exists($user, 'marketplaceClient')) {
            return null;
        }

        return $user->marketplaceClient;
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
