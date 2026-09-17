<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use App\Models\MarketplacePartner;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base for /api/partner/v1. The partner.auth middleware sets the partner and its
 * marketplace client on the request.
 */
abstract class PartnerController extends Controller
{
    protected function partner(Request $request): MarketplacePartner
    {
        return $request->attributes->get('marketplace_partner');
    }

    protected function client(Request $request): MarketplaceClient
    {
        return $request->attributes->get('marketplace_client');
    }

    protected function fail(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    protected function page(LengthAwarePaginator $paginator, array $items): JsonResponse
    {
        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Keyset page. "next" is the cursor of the last row returned: pass it back to
     * continue, and keep it as the starting point of the next sync. has_more says
     * whether to ask again right away.
     */
    protected function cursorPage(Builder $query, int $perPage, callable $map, callable $cursor): JsonResponse
    {
        $rows = $query->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage)->values();

        return response()->json([
            'data' => $rows->map($map)->all(),
            'meta' => [
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next' => $rows->isNotEmpty() ? $cursor($rows->last()) : null,
            ],
        ]);
    }

    protected function perPage(Request $request, int $default, int $max): int
    {
        return max(1, min($max, (int) $request->query('per_page', $default)));
    }

    /**
     * An ISO 8601 query parameter. Returns null when absent and false when invalid.
     * An unencoded "+" in the offset arrives as a space, so it is put back.
     */
    protected function dateParam(Request $request, string $name): Carbon|false|null
    {
        $value = trim((string) $request->query($name, ''));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace(' ', '+', $value));
        } catch (\Throwable) {
            return false;
        }
    }
}
