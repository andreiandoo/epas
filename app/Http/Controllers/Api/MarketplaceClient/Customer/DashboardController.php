<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\CustomerPoints;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Single-roundtrip bundle for /cont (dashboard).
 *
 * Replaces 10 separate AJAX calls that dashboard.php was firing in init():
 *   /customer/me, /customer/rewards/config, /customer/referrals,
 *   /customer/stats, /customer/stats/upcoming-events, /activities,
 *   /customer/orders, /customer/support-tickets,
 *   /customer/reviews/events-to-review, /customer/gift-cards
 *
 * One DB connection round-trip per query, all serialised together so the
 * client paints the whole hero + stats + upcoming + recos + orders + utility
 * cards from a single API response.
 */
class DashboardController extends BaseController
{
    public function bundle(Request $request): JsonResponse
    {
        $client   = $this->requireClient($request);
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        // Resolve the same controllers the standalone endpoints use, so the
        // payload shape stays in sync as those evolve.
        $authCtrl    = app(AuthController::class);
        $statsCtrl   = app(StatsController::class);
        $recoCtrl    = app(RecommendationsController::class);
        $rewardsCtrl = app(RewardsController::class);
        $referralsCtrl = app(ReferralsController::class);

        $customerPayload = $this->safeJson(fn () => $authCtrl->me($request)->getData(true));

        $config = $this->safeJson(fn () => $rewardsCtrl->config($request)->getData(true));

        // Build only the lightweight subset of rewards.index we actually
        // surface on the dashboard hero.
        $rewardsSummary = $this->buildRewardsSummary($customer, $client);

        // Referrals — best-effort (auto-creates the row if needed).
        $referrals = $this->safeJson(fn () => $referralsCtrl->index($request)->getData(true));

        // We deliberately DON'T call StatsController::index() here — it
        // returns the full kitchen-sink (XP / level / badges / watchlist
        // count / reviews count) and runs 7+ separate count queries plus
        // two ->withCount->get->sum() patterns that load every paid order
        // into memory just to sum tickets. The dashboard hero needs 5
        // numbers, so we compute them with 2 aggregate queries.
        $stats = ['data' => ['stats' => $this->buildStats($customer)]];
        $upcoming = $this->safeJson(fn () => $statsCtrl->upcomingEvents($request)->getData(true));

        // Recommendations (capped to 4 — dashboard grid is 2×2)
        $request->query->set('limit', 4);
        $recommendations = $this->safeJson(fn () => $recoCtrl->index($request)->getData(true));

        // Recent orders (top 4)
        $recentOrders = $this->buildRecentOrders($customer);

        // Utility counts (support / reviews / gift cards) — single query each
        $utility = $this->buildUtilityCounts($customer);

        return $this->success([
            'customer'        => $this->extract($customerPayload, 'data.customer') ?: $this->extract($customerPayload, 'data'),
            'rewards_config'  => $this->extract($config, 'data'),
            'rewards_summary' => $rewardsSummary,
            'referrals'       => $this->extract($referrals, 'data'),
            'stats'           => $this->extract($stats, 'data.stats') ?: $this->extract($stats, 'data'),
            'upcoming'        => $this->extract($upcoming, 'data.events') ?: ($this->extract($upcoming, 'data') ?: []),
            'recommendations' => $this->extract($recommendations, 'data.items') ?: [],
            'recent_orders'   => $recentOrders,
            'utility'         => $utility,
        ]);
    }

    /**
     * Lightweight dashboard stats — replaces StatsController::index() for
     * the bundle. Two aggregate SELECTs vs the 7+ count() round-trips +
     * 2 load-all-then-sum patterns the legacy endpoint performs.
     *
     * Returns ONLY what the dashboard hero + 4 stat cards consume:
     *   orders_count, total_spent, last_order_at, upcoming_tickets_count,
     *   upcoming_activities_count.
     */
    protected function buildStats(MarketplaceCustomer $customer): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Query 1: order aggregate. One round-trip → all the order numbers.
        $orderAgg = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->selectRaw('COUNT(*) AS orders_count, COALESCE(SUM(total_amount), 0) AS total_spent, MAX(created_at) AS last_order_at')
            ->first();

        // Query 2: ticket aggregate, restricted to upcoming events. Joining
        // marketplace_events lets us count tickets + DISTINCT events in one
        // pass without N+1 fetches per order.
        $upcomingAgg = (object) ['tickets' => 0, 'activities' => 0];
        try {
            $upcomingAgg = DB::table('tickets')
                ->join('marketplace_events', 'marketplace_events.id', '=', 'tickets.event_id')
                ->where('tickets.marketplace_customer_id', $customer->id)
                ->whereIn('tickets.status', ['paid', 'valid', 'confirmed', 'checked_in'])
                ->where('marketplace_events.starts_at', '>=', now())
                ->selectRaw('COUNT(*) AS tickets, COUNT(DISTINCT tickets.event_id) AS activities')
                ->first();
        } catch (\Throwable $e) {
            // Schema variation between EventPilot installs — fall back to 0
            \Log::warning('Dashboard upcoming aggregate failed', ['error' => $e->getMessage()]);
        }

        return [
            'orders_count'                => (int) ($orderAgg->orders_count ?? 0),
            'total_orders'                => (int) ($orderAgg->orders_count ?? 0),
            'total_spent'                 => (float) ($orderAgg->total_spent ?? 0),
            'last_order_at'               => $orderAgg->last_order_at ?? null,
            'upcoming_tickets_count'      => (int) ($upcomingAgg->tickets ?? 0),
            'tickets_count'               => (int) ($upcomingAgg->tickets ?? 0),
            'upcoming_activities_count'   => (int) ($upcomingAgg->activities ?? 0),
            'upcoming_events_count'       => (int) ($upcomingAgg->activities ?? 0),
        ];
    }

    /**
     * Lightweight rewards summary — current balance + expiring soon. Skips
     * the full RewardsController::index() because that fires several extra
     * sub-queries (badges, level XP) the dashboard hero doesn't show.
     */
    protected function buildRewardsSummary(MarketplaceCustomer $customer, $client): array
    {
        $points = CustomerPoints::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->first();
        if (! $points) {
            return ['balance' => 0, 'lifetime_earned' => 0, 'spent' => 0, 'expiring_soon' => 0];
        }

        // Expiring soon — anything dropping out of validity within 30 days,
        // if the schema tracks it. Safe fallback to 0 when columns absent.
        $expiringSoon = 0;
        try {
            if (\Schema::hasTable('points_transactions')) {
                $expiringSoon = (int) DB::table('points_transactions')
                    ->where('marketplace_customer_id', $customer->id)
                    ->where('type', 'earned')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->where('expires_at', '>', now())
                    ->sum(DB::raw('GREATEST(points - COALESCE(points_consumed, 0), 0)'));
            }
        } catch (\Throwable $e) {}

        return [
            'balance'         => (int) $points->current_balance,
            'lifetime_earned' => (int) $points->total_earned,
            'spent'           => (int) $points->total_spent,
            'expiring_soon'   => $expiringSoon,
            'expiring_days'   => 30,
        ];
    }

    /**
     * 4 most recent orders shaped for the dashboard table — id / date /
     * total / status. Same data as /customer/orders but slimmed and with
     * pre-computed UI strings so the JS doesn't have to format.
     */
    protected function buildRecentOrders(MarketplaceCustomer $customer): array
    {
        try {
            $orders = Order::where('marketplace_customer_id', $customer->id)
                ->orderByDesc('id')
                ->limit(4)
                ->get(['id', 'order_number', 'status', 'total_amount', 'currency', 'created_at']);
        } catch (\Throwable $e) {
            return [];
        }

        return $orders->map(function ($o) {
            $status = strtolower((string) ($o->status ?? 'confirmed'));
            $statusClass = match ($status) {
                'paid', 'confirmed', 'confirmată', 'completed' => 'bg-mint text-forest',
                'refunded', 'cancelled', 'retur'              => 'bg-rose text-vermilion',
                default                                        => 'bg-paper-2 text-ink-soft',
            };
            $statusLabel = match ($status) {
                'paid', 'confirmed' => 'confirmată',
                'completed'         => 'finalizată',
                'refunded'          => 'retur',
                'cancelled'         => 'anulată',
                'pending'           => 'în așteptare',
                default             => $status,
            };
            return [
                'id'         => '#' . ($o->order_number ?: 'BO-' . $o->id),
                'raw_id'     => $o->id,
                'date'       => $o->created_at?->locale('ro')->translatedFormat('j M Y') ?? '',
                'total'      => number_format((float) $o->total_amount, 0, ',', '.') . ' lei',
                'status'     => $statusLabel,
                'statusClass'=> $statusClass,
                'url'        => '/cont/comenzile-mele#' . ($o->order_number ?: $o->id),
            ];
        })->values()->all();
    }

    /**
     * Cards at the bottom of the dashboard — Support count + Reviews
     * pending + Gift card balance. Each is one query, cached 5 min so
     * a customer flicking between tabs doesn't keep re-querying.
     */
    protected function buildUtilityCounts(MarketplaceCustomer $customer): array
    {
        return Cache::remember('dash_utility:' . $customer->id, 300, function () use ($customer) {
            $supportActive = 0;
            try {
                $supportActive = (int) DB::table('support_tickets')
                    ->where('opener_type', MarketplaceCustomer::class)
                    ->where('opener_id', $customer->id)
                    ->whereIn('status', ['open', 'pending', 'awaiting_customer', 'awaiting_staff'])
                    ->count();
            } catch (\Throwable $e) {}

            $reviewsPending = 0;
            try {
                // Approximate: tickets that have been checked in to events
                // the customer hasn't reviewed yet.
                $reviewedEventIds = DB::table('marketplace_customer_reviews')
                    ->where('marketplace_customer_id', $customer->id)
                    ->pluck('event_id')
                    ->all();
                $reviewsPending = (int) Ticket::where('marketplace_customer_id', $customer->id)
                    ->where('status', 'checked_in')
                    ->when(! empty($reviewedEventIds), fn ($q) => $q->whereNotIn('event_id', $reviewedEventIds))
                    ->distinct('event_id')
                    ->count('event_id');
            } catch (\Throwable $e) {}

            $giftBalance = 0;
            try {
                if (\Schema::hasTable('gift_cards')) {
                    $giftBalance = (float) DB::table('gift_cards')
                        ->where('marketplace_customer_id', $customer->id)
                        ->where('status', 'active')
                        ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                        ->sum('balance');
                }
            } catch (\Throwable $e) {}

            return [
                'supportActive'  => $supportActive,
                'reviewsPending' => $reviewsPending,
                'giftBalance'    => $giftBalance,
            ];
        });
    }

    /**
     * Run a closure that returns JSON-array data; swallow exceptions so a
     * partial failure in one sub-payload doesn't 500 the entire bundle.
     */
    protected function safeJson(callable $fn): array
    {
        try {
            $out = $fn();
            return is_array($out) ? $out : [];
        } catch (\Throwable $e) {
            \Log::warning('Dashboard bundle partial failure', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Walk a nested array using dot notation, returning null if any step
     * is missing. Used to pluck `data.customer` etc. from sub-responses.
     */
    protected function extract(array $payload, string $path)
    {
        $cursor = $payload;
        foreach (explode('.', $path) as $key) {
            if (! is_array($cursor) || ! array_key_exists($key, $cursor)) return null;
            $cursor = $cursor[$key];
        }
        return $cursor;
    }
}
