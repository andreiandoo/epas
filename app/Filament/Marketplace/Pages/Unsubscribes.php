<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceNewsletterRecipient;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class Unsubscribes extends Page
{
    use HasMarketplaceContext;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-minus';
    protected static ?string $navigationLabel = 'Dezabonări';
    protected static ?string $title = 'Dezabonări';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?string $navigationParentItem = 'Newsletters';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.marketplace.pages.unsubscribes';

    /** Human labels for the stored reason keys (see NewsletterTrackingController). */
    protected const REASON_LABELS = [
        'too_many' => 'Prea multe emailuri',
        'not_relevant' => 'Conținut irelevant',
        'never_signed_up' => 'Nu s-a abonat',
        'spam' => 'Ajung în spam',
        'other' => 'Alt motiv',
        'nespecificat' => 'Nespecificat',
    ];

    /** Every unsubscribed recipient belonging to this marketplace's newsletters. */
    protected function baseQuery(): Builder
    {
        $marketplaceId = static::getMarketplaceClient()?->id;

        return MarketplaceNewsletterRecipient::query()
            ->whereNotNull('unsubscribed_at')
            ->whereHas('newsletter', fn ($q) => $q->where('marketplace_client_id', $marketplaceId));
    }

    public function getViewData(): array
    {
        $counts = $this->baseQuery()
            ->selectRaw("COALESCE(unsubscribe_reason, 'nespecificat') as reason, COUNT(*) as c")
            ->groupBy('reason')
            ->pluck('c', 'reason')
            ->all();

        $total = (int) array_sum($counts);

        $breakdown = [];
        foreach (self::REASON_LABELS as $key => $label) {
            $count = (int) ($counts[$key] ?? 0);
            $breakdown[] = [
                'label' => $label,
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }
        usort($breakdown, fn ($a, $b) => $b['count'] <=> $a['count']);

        // How many actually gave feedback (anything other than "nespecificat").
        $withReason = $total - (int) ($counts['nespecificat'] ?? 0);

        $recent = $this->baseQuery()
            ->latest('unsubscribed_at')
            ->limit(100)
            ->get(['email', 'unsubscribe_reason', 'unsubscribe_reason_detail', 'unsubscribed_at'])
            ->map(fn ($r) => [
                'email' => $r->email,
                'reason' => self::REASON_LABELS[$r->unsubscribe_reason] ?? 'Nespecificat',
                'detail' => $r->unsubscribe_reason_detail,
                'date' => optional($r->unsubscribed_at)->format('d.m.Y H:i'),
            ]);

        return [
            'total' => $total,
            'withReason' => $withReason,
            'breakdown' => $breakdown,
            'recent' => $recent,
        ];
    }
}
