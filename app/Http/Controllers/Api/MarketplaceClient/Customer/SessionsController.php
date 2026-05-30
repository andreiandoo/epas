<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Active Sanctum tokens for the logged-in marketplace customer.
 *
 * Backs /cont/setari → Securitate → „Sesiuni active". Tokens are created
 * by AuthController with a structured name field `web|Browser|OS|IP` so we
 * can parse the device label here instead of storing duplicate metadata.
 */
class SessionsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $currentTokenId = optional($customer->currentAccessToken())->id;

        $tokens = $customer->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'name', 'last_used_at', 'created_at']);

        $sessions = $tokens->map(function (PersonalAccessToken $t) use ($currentTokenId) {
            return array_merge(
                $this->parseTokenName((string) $t->name),
                [
                    'id'           => $t->id,
                    'is_current'   => $t->id === $currentTokenId,
                    'created_at'   => $t->created_at?->toIso8601String(),
                    'last_used_at' => $t->last_used_at?->toIso8601String(),
                ]
            );
        })->values()->all();

        return $this->success(['sessions' => $sessions]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $token = $customer->tokens()->find($id);
        if (! $token) {
            return $this->error('Sesiunea nu mai există.', 404);
        }

        $currentId = optional($customer->currentAccessToken())->id;
        $token->delete();

        return $this->success([
            'logged_out_current' => $currentId === $id,
        ], 'Sesiunea a fost închisă.');
    }

    public function destroyAllOthers(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $currentId = optional($customer->currentAccessToken())->id;
        $deleted = $customer->tokens()
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->delete();

        return $this->success(['revoked' => $deleted], 'Celelalte sesiuni au fost închise.');
    }

    /**
     * Turn `web|Chrome|Windows|1.2.3.4` into a UI-friendly array. Tokens
     * created before the structured name format land in `device` as-is.
     */
    protected function parseTokenName(string $name): array
    {
        $parts = explode('|', $name);
        if (count($parts) >= 4 && $parts[0] === 'web') {
            return [
                'device'   => $parts[1] . ' · ' . $parts[2],
                'browser'  => $parts[1],
                'os'       => $parts[2],
                'ip'       => $parts[3],
                'platform' => 'web',
            ];
        }
        return [
            'device'   => $name,
            'browser'  => null,
            'os'       => null,
            'ip'       => null,
            'platform' => 'legacy',
        ];
    }
}
