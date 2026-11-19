<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * POS routes of the marketplace API used to trust the marketplace API key
 * alone, so anyone holding the (public) key could create cash orders, send an
 * order's tickets to another address or check them in. The POS apps already
 * send the organizer's Sanctum token next to the key; this checks that the
 * token belongs to the organizer that owns the order or event.
 *
 * Log-only until services.marketplace_pos.enforce_owner is on, so a caller we
 * did not anticipate shows up in the logs before anything is refused. When
 * enforcing, the answer is 404, not 403: the apps' offline sales queue stops
 * for good on a 403 (audit, constraint list).
 */
class EnsurePosOrderOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->attributes->get('marketplace_client');
        [$reason, $tokenType] = $this->check($request, $client);

        if ($reason !== null) {
            $enforce = (bool) config('services.marketplace_pos.enforce_owner');

            Log::channel('marketplace')->warning('POS owner check failed', [
                'reason' => $reason,
                'enforced' => $enforce,
                'token_type' => $tokenType,
                'client_id' => $client?->id,
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);

            if ($enforce) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
        }

        return $next($request);
    }

    /**
     * @return array{0: ?string, 1: ?string} [failure reason or null, token owner type]
     */
    protected function check(Request $request, $client): array
    {
        if (!$client) {
            return ['no_client', null];
        }

        $plain = $request->bearerToken();
        if (!$plain) {
            return ['no_token', null];
        }

        $token = PersonalAccessToken::findToken($plain);
        $owner = $token?->tokenable;
        if (!$owner) {
            return ['invalid_token', null];
        }

        $tokenType = class_basename($owner);
        if (!$owner instanceof MarketplaceOrganizer) {
            return ['not_organizer_token', $tokenType];
        }

        if ((int) $owner->marketplace_client_id !== (int) $client->id) {
            return ['other_marketplace', $tokenType];
        }

        $organizerId = $this->targetOrganizerId($request, (int) $client->id);
        if ($organizerId === null) {
            return ['target_not_found', $tokenType];
        }

        if ($organizerId !== (int) $owner->id) {
            return ['not_owner', $tokenType];
        }

        return [null, $tokenType];
    }

    protected function targetOrganizerId(Request $request, int $clientId): ?int
    {
        $orderId = $request->route('order');
        if ($orderId !== null) {
            $id = Order::where('marketplace_client_id', $clientId)
                ->whereKey((int) $orderId)
                ->value('marketplace_organizer_id');

            return $id !== null ? (int) $id : null;
        }

        $eventId = $request->route('event') ?? $request->input('event_id');
        if ($eventId !== null && is_numeric($eventId)) {
            $id = Event::where('marketplace_client_id', $clientId)
                ->whereKey((int) $eventId)
                ->value('marketplace_organizer_id');

            return $id !== null ? (int) $id : null;
        }

        return null;
    }
}
