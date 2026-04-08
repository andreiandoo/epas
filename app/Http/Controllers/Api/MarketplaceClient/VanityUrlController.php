<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceVanityUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VanityUrlController extends BaseController
{
    /**
     * Resolve a vanity slug for the current marketplace.
     *
     * GET /api/marketplace-client/vanity/{slug}
     *
     * Returns:
     * {
     *   "found": true,
     *   "type": "artist|event|venue|organizer|external_url",
     *   "target_slug": "real-artist-slug",
     *   "target_url": "https://...",   // only for external_url
     *   "target_id": 21859
     * }
     *
     * Or status 404 with {"found": false}.
     */
    public function resolve(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $vanity = MarketplaceVanityUrl::where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$vanity) {
            return response()->json(['found' => false], 404);
        }

        // Track hit (non-blocking)
        try {
            $vanity->recordHit();
        } catch (\Throwable $e) {
            // Don't fail the request if tracking fails
        }

        // External URL → just return the URL for redirect
        if ($vanity->target_type === MarketplaceVanityUrl::TYPE_EXTERNAL_URL) {
            return response()->json([
                'found' => true,
                'type' => 'external_url',
                'target_url' => $vanity->target_url,
            ]);
        }

        $target = $vanity->resolveTarget();
        if (!$target) {
            return response()->json(['found' => false, 'reason' => 'target_missing'], 404);
        }

        return response()->json([
            'found' => true,
            'type' => $vanity->target_type,
            'target_slug' => $target->slug,
            'target_id' => $target->id,
        ]);
    }
}
