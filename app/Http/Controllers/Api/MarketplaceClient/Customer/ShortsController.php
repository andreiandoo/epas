<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Models\ShortLike;
use App\Models\ShortSave;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Authenticated shorts interactions for the mobile app: like, save, and the
 * personalised feed segments.
 *
 * Toggles are idempotent (unique key on the pivot) and mirror their effect into
 * short_events so the analytics stream stays the single source of truth.
 */
class ShortsController extends BaseController
{
    public function __construct(
        private readonly ShortFeedService $feed,
        private readonly ShortPayload $payload,
    ) {}

    /**
     * GET marketplace-client/customer/shorts/feed?feed=for_you|following
     */
    public function feed(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'feed' => ['nullable', 'string', 'in:'.implode(',', ShortFeedService::FEEDS)],
            'cursor' => ['nullable', 'string', 'max:512'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $result = $this->feed->page(
            feed: $validated['feed'] ?? 'for_you',
            cursor: $validated['cursor'] ?? null,
            limit: (int) ($validated['limit'] ?? config('shorts.feed.page_size', 10)),
            customer: $customer,
            filters: array_filter([
                'marketplace_client_id' => $this->getClient($request)?->id,
            ]),
        );

        return $this->success($result);
    }

    /**
     * POST marketplace-client/customer/shorts/{short}/like — toggle.
     */
    public function toggleLike(Request $request, int $short): JsonResponse
    {
        return $this->toggle(
            $request,
            $short,
            ShortLike::class,
            'likes',
            ShortEvent::TYPE_LIKE,
            ShortEvent::TYPE_UNLIKE,
        );
    }

    /**
     * POST marketplace-client/customer/shorts/{short}/save — toggle.
     */
    public function toggleSave(Request $request, int $short): JsonResponse
    {
        return $this->toggle(
            $request,
            $short,
            ShortSave::class,
            'saves',
            ShortEvent::TYPE_SAVE,
            ShortEvent::TYPE_UNSAVE,
        );
    }

    /**
     * GET marketplace-client/customer/shorts/saved
     */
    public function saved(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $perPage = min((int) $request->input('per_page', 20), 50);

        $saved = ShortSave::query()
            ->where('marketplace_customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $shorts = Short::query()
            ->whereIn('id', collect($saved->items())->pluck('short_id'))
            ->with(['owner', 'event'])
            ->get()
            ->sortBy(fn (Short $s) => array_search(
                $s->id,
                collect($saved->items())->pluck('short_id')->all(),
                true,
            ));

        $likedIds = ShortLike::query()
            ->where('marketplace_customer_id', $customer->id)
            ->whereIn('short_id', $shorts->pluck('id'))
            ->pluck('short_id')
            ->all();

        return $this->success([
            'items' => $this->payload->collection($shorts, $likedIds, $shorts->pluck('id')->all()),
            'meta' => [
                'current_page' => $saved->currentPage(),
                'last_page' => $saved->lastPage(),
                'total' => $saved->total(),
            ],
        ]);
    }

    /**
     * Shared toggle for the like/save pivots.
     *
     * @param  class-string<ShortLike|ShortSave>  $model
     */
    protected function toggle(
        Request $request,
        int $shortId,
        string $model,
        string $counter,
        string $onType,
        string $offType,
    ): JsonResponse {
        $customer = $this->requireCustomer($request);

        $short = Short::query()->find($shortId);

        if (! $short) {
            return $this->error('Short not found', 404);
        }

        $existing = $model::query()
            ->where('short_id', $short->id)
            ->where('marketplace_customer_id', $customer->id)
            ->first();

        $active = DB::transaction(function () use ($existing, $model, $short, $customer, $counter) {
            if ($existing) {
                $existing->delete();
                // Guard the denormalised counter against dropping below zero if
                // an aggregate job and a toggle ever race.
                $short->newQuery()->whereKey($short->id)->where($counter, '>', 0)->decrement($counter);

                return false;
            }

            $model::query()->create([
                'short_id' => $short->id,
                'marketplace_customer_id' => $customer->id,
            ]);
            $short->newQuery()->whereKey($short->id)->increment($counter);

            return true;
        });

        ShortEvent::create([
            'short_id' => $short->id,
            'marketplace_customer_id' => $customer->id,
            'type' => $active ? $onType : $offType,
            'feed' => $request->input('feed'),
            'created_at' => now(),
        ]);

        return $this->success([
            'short_id' => $short->id,
            'active' => $active,
            'count' => (int) $short->newQuery()->whereKey($short->id)->value($counter),
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
