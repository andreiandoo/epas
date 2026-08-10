<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceFollow;
use App\Services\Shorts\ShortAffinityProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The follow graph behind the "Following" feed segment (B2).
 *
 * Polymorphic over artists, organisers and venues. The API speaks short tokens
 * ("artist") rather than class names, so the client never has to know the
 * server's namespaces.
 */
class FollowsController extends BaseController
{
    public function __construct(private readonly ShortAffinityProfile $profile) {}

    /**
     * GET marketplace-client/customer/follows
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $follows = MarketplaceFollow::query()
            ->forCustomer($customer->id)
            ->with('followable')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MarketplaceFollow $follow) => [
                'id' => $follow->id,
                'type' => MarketplaceFollow::tokenFor($follow->followable_type),
                'followable_id' => (int) $follow->followable_id,
                'name' => $follow->followable?->name ?? $follow->followable?->title,
                'slug' => $follow->followable?->slug,
                'followed_at' => $follow->created_at?->toIso8601String(),
            ]);

        return $this->success(['items' => $follows->values()]);
    }

    /**
     * POST marketplace-client/customer/follows — toggle.
     */
    public function toggle(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceFollow::FOLLOWABLE_TYPES))],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $class = MarketplaceFollow::resolveType($validated['type']);

        // Verify the target exists before storing a follow that points nowhere.
        if (! $class || ! $class::query()->whereKey($validated['id'])->exists()) {
            return $this->error('Not found', 404);
        }

        $existing = MarketplaceFollow::query()
            ->forCustomer($customer->id)
            ->where('followable_type', $class)
            ->where('followable_id', $validated['id'])
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            MarketplaceFollow::query()->create([
                'marketplace_customer_id' => $customer->id,
                'followable_type' => $class,
                'followable_id' => $validated['id'],
            ]);
            $following = true;
        }

        // The ranker caches the taste profile; a follow must show up in the very
        // next feed page, not five minutes later.
        $this->profile->forget($customer);

        return $this->success([
            'type' => $validated['type'],
            'id' => $validated['id'],
            'following' => $following,
        ]);
    }

    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (! $customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
