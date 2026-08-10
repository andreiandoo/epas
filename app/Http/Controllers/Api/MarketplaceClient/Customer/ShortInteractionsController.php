<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Services\Shorts\ShortGamificationService;
use App\Services\Shorts\ShortReminderService;
use App\Services\Shorts\ShortShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wave-1 interactions: share (D1), drop reminders (D2) and the gamification
 * state the profile screen reads (D11).
 *
 * Kept apart from ShortsController so the feed's hot path stays small.
 */
class ShortInteractionsController extends BaseController
{
    public function __construct(
        private readonly ShortShareService $shares,
        private readonly ShortReminderService $reminders,
        private readonly ShortGamificationService $gamification,
    ) {}

    /**
     * POST marketplace-client/customer/shorts/{short}/share
     */
    public function share(Request $request, int $short): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:32'],
        ]);

        $model = Short::query()->published()->find($short);

        if (! $model) {
            return $this->error('Short not found', 404);
        }

        $result = $this->shares->share($model, $customer, $validated['channel'] ?? null);

        // The share counter and the telemetry stream both move — the counter
        // drives the feed payload, the event drives analytics.
        $model->newQuery()->whereKey($model->id)->increment('shares');

        ShortEvent::create([
            'short_id' => $model->id,
            'marketplace_customer_id' => $customer->id,
            'type' => ShortEvent::TYPE_SHARE,
            'feed' => $request->input('feed'),
            'meta' => ['channel' => $validated['channel'] ?? null],
            'created_at' => now(),
        ]);

        $points = config('shorts.gamification.enabled')
            ? $this->gamification->recordShare($customer)
            : ['points' => 0, 'awarded' => false];

        return $this->success($result + ['points' => $points]);
    }

    /**
     * POST marketplace-client/customer/shorts/{short}/remind
     */
    public function remind(Request $request, int $short): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $model = Short::query()->published()->find($short);

        if (! $model) {
            return $this->error('Short not found', 404);
        }

        $window = $this->reminders->saleWindow($model);

        if (! $window['pending']) {
            // Nothing to wait for — the client should be showing a buy CTA.
            return $this->error('Tickets for this event are already on sale.', 422);
        }

        $reminder = $this->reminders->remind($model, $customer);

        return $this->success([
            'short_id' => $model->id,
            'reminded' => true,
            'remind_at' => $reminder->remind_at?->toIso8601String(),
        ]);
    }

    /**
     * DELETE marketplace-client/customer/shorts/{short}/remind
     */
    public function forget(Request $request, int $short): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $model = Short::query()->find($short);

        if (! $model) {
            return $this->error('Short not found', 404);
        }

        $this->reminders->forget($model, $customer);

        return $this->success(['short_id' => $model->id, 'reminded' => false]);
    }

    /**
     * GET marketplace-client/customer/shorts/streak
     */
    public function streak(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $streak = $this->gamification->streakFor($customer);

        return $this->success([
            'current_streak' => $streak->current_streak,
            'longest_streak' => $streak->longest_streak,
            'total_points' => $streak->total_points,
            'last_watch_date' => $streak->last_watch_date?->toDateString(),
        ]);
    }

    /**
     * POST marketplace-client/customer/shorts/watched
     *
     * Called once per session after the first credible view; the daily streak is
     * a per-day fact, so the server decides whether it counts.
     */
    public function watched(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        if (! config('shorts.gamification.enabled')) {
            return $this->success(['awarded' => false, 'points' => 0, 'streak' => 0]);
        }

        return $this->success($this->gamification->recordDailyWatch($customer));
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
