<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceCustomer;
use App\Services\Shorts\ShortCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Editorial collections (B7) and the stories tray (B8).
 *
 * Public reads, like the rest of the feed: browsing happens before login.
 */
class ShortCollectionsController extends Controller
{
    public function __construct(private readonly ShortCollectionService $collections) {}

    /**
     * GET tenant-client/short-collections
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['items' => $this->collections->index($this->clientId($request))],
        ]);
    }

    /**
     * GET tenant-client/short-collections/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $collection = $this->collections->show($slug, $this->viewer($request), $this->clientId($request));

        if (! $collection) {
            return response()->json(['success' => false, 'message' => 'Collection not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $collection]);
    }

    /**
     * GET tenant-client/stories — live stories, grouped by owner.
     */
    public function stories(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['items' => $this->collections->stories($this->clientId($request), $this->viewer($request))],
        ]);
    }

    protected function viewer(Request $request): ?MarketplaceCustomer
    {
        $user = $request->user();

        return $user instanceof MarketplaceCustomer ? $user : null;
    }

    protected function clientId(Request $request): ?int
    {
        return $request->attributes->get('marketplace_client')?->id;
    }
}
