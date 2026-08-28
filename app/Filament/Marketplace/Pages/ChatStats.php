<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Chat\ChatConversation;
use App\Models\MarketplaceAdmin;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Statistics dashboard for the `live-chat` microservice. Shows overall
 * conversation totals and per-operator performance (claimed / resolved /
 * inactivity-closed, resolution rate, average rating and first-response time),
 * plus a recent conversation log. Read-only — no Livewire polling.
 *
 * Status semantics used here: a MANUALLY resolved chat has status 'resolved';
 * a chat auto-closed for INACTIVITY has status 'closed'. Resolution rate is
 * resolved / (resolved + inactivity-closed).
 */
class ChatStats extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Statistici chat';
    protected static UnitEnum|string|null $navigationGroup = 'Chat';
    protected static ?int $navigationSort = 10;
    protected static ?string $slug = 'chat-stats';
    protected string $view = 'filament.marketplace.pages.chat-stats';

    public function getTitle(): string
    {
        return 'Statistici chat';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('live-chat');
    }

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('live-chat');
    }

    public function mount(): void
    {
        abort_unless(static::marketplaceHasMicroservice('live-chat'), 404);
    }

    // -------- View data --------

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $clientId = static::getMarketplaceClientId();

        if (!$clientId) {
            return [
                'overall' => [],
                'operators' => [],
                'conversations' => collect(),
            ];
        }

        $base = fn () => ChatConversation::query()->where('marketplace_client_id', $clientId);

        // -------- Overall totals --------
        $ratingCount = (clone $base())->whereNotNull('rating')->count();

        $overall = [
            'total' => (clone $base())->count(),
            'active' => (clone $base())->where('status', ChatConversation::STATUS_ACTIVE)->count(),
            'queued' => (clone $base())->where('status', ChatConversation::STATUS_QUEUED)->count(),
            'resolved' => (clone $base())->where('status', ChatConversation::STATUS_RESOLVED)->count(),
            'inactivity' => (clone $base())->where('status', ChatConversation::STATUS_CLOSED)->count(),
            'avg_rating' => $ratingCount ? round((float) (clone $base())->whereNotNull('rating')->avg('rating'), 1) : 0.0,
            'rating_count' => $ratingCount,
            'avg_response_seconds' => $this->avgResponseSeconds((clone $base())),
        ];

        // -------- Per-operator stats --------
        $admins = MarketplaceAdmin::query()
            ->where('marketplace_client_id', $clientId)
            ->get(['id', 'name']);

        $operators = [];
        foreach ($admins as $admin) {
            $row = $this->operatorRow(
                $base,
                fn ($q) => $q->where('assigned_to_marketplace_admin_id', $admin->id),
                $admin->name,
            );
            // Only include operators who have actually handled at least one chat.
            if ($row['claimed'] >= 1) {
                $operators[] = $row;
            }
        }

        // Unassigned bucket ("Nealocat"): conversations with no operator.
        $unassignedRow = $this->operatorRow(
            $base,
            fn ($q) => $q->whereNull('assigned_to_marketplace_admin_id'),
            'Nealocat',
        );
        if ($unassignedRow['claimed'] >= 1) {
            $operators[] = $unassignedRow;
        }

        // Sort by claimed desc.
        usort($operators, fn ($a, $b) => $b['claimed'] <=> $a['claimed']);

        // -------- Recent conversations --------
        $conversations = (clone $base())
            ->with('assignee:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return compact('overall', 'operators', 'conversations');
    }

    /**
     * Build a single per-operator (or unassigned) statistics row.
     *
     * @param  callable():\Illuminate\Database\Eloquent\Builder  $base
     * @param  callable(\Illuminate\Database\Eloquent\Builder):\Illuminate\Database\Eloquent\Builder  $scope
     * @return array<string, mixed>
     */
    private function operatorRow(callable $base, callable $scope, string $name): array
    {
        $claimed = $scope($base())->count();
        $resolved = $scope($base())->where('status', ChatConversation::STATUS_RESOLVED)->count();
        $inactivity = $scope($base())->where('status', ChatConversation::STATUS_CLOSED)->count();
        $active = $scope($base())->where('status', ChatConversation::STATUS_ACTIVE)->count();

        $ratingCount = $scope($base())->whereNotNull('rating')->count();
        $avgRating = $ratingCount
            ? round((float) $scope($base())->whereNotNull('rating')->avg('rating'), 1)
            : 0.0;

        $ended = $resolved + $inactivity;
        $resolutionRate = $ended > 0 ? (int) round($resolved / $ended * 100) : null;

        return [
            'name' => $name,
            'claimed' => $claimed,
            'resolved' => $resolved,
            'inactivity' => $inactivity,
            'active' => $active,
            'avg_rating' => $avgRating,
            'rating_count' => $ratingCount,
            'avg_response_seconds' => $this->avgResponseSeconds($scope($base())),
            'resolution_rate' => $resolutionRate,
        ];
    }

    /**
     * Average first-response time (queued_at → first_response_at) in seconds,
     * computed in PHP over the given query. Returns null when no rows qualify.
     */
    private function avgResponseSeconds(\Illuminate\Database\Eloquent\Builder $query): ?int
    {
        $rows = $query
            ->whereNotNull('first_response_at')
            ->whereNotNull('queued_at')
            ->get(['queued_at', 'first_response_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $total = 0;
        foreach ($rows as $r) {
            $total += (int) $r->first_response_at->diffInSeconds($r->queued_at, true);
        }

        return (int) round($total / $rows->count());
    }
}
