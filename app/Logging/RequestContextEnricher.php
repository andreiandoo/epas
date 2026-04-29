<?php

namespace App\Logging;

use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use App\Models\User;
use App\Models\VenueOwner;
use Illuminate\Support\Facades\Auth;

/**
 * Best-effort enrichment of an error record with the current HTTP request
 * context: URL, method, IP, user-agent, and a normalized identification of
 * whoever was authenticated when the error fired.
 *
 * Returns an array of nullable fields. Used by both the Monolog handler
 * (live errors) and the global exception reporter.
 */
class RequestContextEnricher
{
    /**
     * @return array{
     *   request_url:?string,
     *   request_method:?string,
     *   request_ip:?string,
     *   request_user_agent:?string,
     *   request_user_type:?string,
     *   request_user_id:?int,
     *   tenant_id:?int,
     *   marketplace_client_id:?int,
     * }
     */
    public function capture(): array
    {
        // No request bound (CLI, queue worker, scheduler) — return empty shell.
        if (!app()->bound('request') || !function_exists('request')) {
            return self::empty();
        }

        try {
            $request = request();
        } catch (\Throwable $e) {
            return self::empty();
        }

        if (!$request) {
            return self::empty();
        }

        [$userType, $userId] = $this->resolveUser();
        [$tenantId, $clientId] = $this->resolveTenancy($request);

        return [
            'request_url' => self::truncate($request->fullUrl(), 2048),
            'request_method' => substr($request->method(), 0, 8),
            'request_ip' => $request->ip(),
            'request_user_agent' => self::truncate((string) $request->userAgent(), 1000),
            'request_user_type' => $userType,
            'request_user_id' => $userId,
            'tenant_id' => $tenantId,
            'marketplace_client_id' => $clientId,
        ];
    }

    private function resolveUser(): array
    {
        // Walk through the guards we know about. We avoid Auth::user() on the
        // default guard alone because requests authenticated via Sanctum on
        // a non-web guard wouldn't surface there.
        foreach (['web', 'sanctum', 'organizer', 'venue_owner'] as $guard) {
            try {
                $user = Auth::guard($guard)->user();
            } catch (\Throwable $e) {
                continue;
            }
            if (!$user) {
                continue;
            }

            return [self::userTypeFor($user), (int) $user->getKey()];
        }

        return [null, null];
    }

    private function resolveTenancy($request): array
    {
        $client = $request->attributes->get('marketplace_client');
        $clientId = $client?->id ?? null;

        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? null;

        // Authenticated organizer carries marketplace_client_id directly.
        if (!$clientId) {
            try {
                $user = Auth::guard('sanctum')->user();
                if ($user instanceof MarketplaceOrganizer) {
                    $clientId = $user->marketplace_client_id;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return [$tenantId, $clientId];
    }

    private static function userTypeFor(object $user): string
    {
        return match (true) {
            $user instanceof User => 'admin',
            $user instanceof MarketplaceOrganizer => 'organizer',
            $user instanceof MarketplaceOrganizerTeamMember => 'team_member',
            $user instanceof MarketplaceCustomer => 'customer',
            $user instanceof VenueOwner => 'venue_owner',
            default => class_basename($user),
        };
    }

    private static function empty(): array
    {
        return [
            'request_url' => null,
            'request_method' => null,
            'request_ip' => null,
            'request_user_agent' => null,
            'request_user_type' => null,
            'request_user_id' => null,
            'tenant_id' => null,
            'marketplace_client_id' => null,
        ];
    }

    private static function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
