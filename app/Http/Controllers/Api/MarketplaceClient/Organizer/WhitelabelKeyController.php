<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceWhitelabelKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organizer's whitelabel API key: the restricted key that goes into the
 * whitelabel package instead of the marketplace API key.
 */
class WhitelabelKeyController extends BaseController
{
    /** Current key (issued on first request). */
    public function show(Request $request): JsonResponse
    {
        $organizer = $this->widgetOrganizer($request);
        if ($organizer instanceof JsonResponse) {
            return $organizer;
        }

        return $this->success($this->payload(MarketplaceWhitelabelKey::forOrganizer($organizer)));
    }

    /** Replace the key. The installed site stops working until it gets the new one. */
    public function regenerate(Request $request): JsonResponse
    {
        $organizer = $this->widgetOrganizer($request);
        if ($organizer instanceof JsonResponse) {
            return $organizer;
        }

        return $this->success($this->payload(MarketplaceWhitelabelKey::regenerate($organizer)));
    }

    protected function widgetOrganizer(Request $request): MarketplaceOrganizer|JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        if (!($organizer->settings['widget_enabled'] ?? false)) {
            return $this->error('Widgets are not enabled for this organizer', 403);
        }

        return $organizer;
    }

    protected function payload(MarketplaceWhitelabelKey $key): array
    {
        return [
            'key' => $key->secret,
            'created_at' => $key->updated_at?->toIso8601String(),
            'last_used_at' => $key->last_used_at?->toIso8601String(),
        ];
    }
}
