<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\PendingPaymentResource\Pages;
use App\Models\MarketplacePayout;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * "De plătit" page — operator's focused payment queue.
 *
 * A second Resource on top of the existing MarketplacePayout model (same
 * underlying table). This view filters to active payment items (status in
 * pending/approved/processing/completed/rejected, event-attached only) and
 * exposes a payment-centric workflow:
 *   - Listă: ce trebuie plătit azi (default filter: "în așteptare")
 *   - View: organizator, cont bancar, sold defalcat (decont − factură),
 *     2 acțiuni — Achitat / Respins — care marchează atât decontul cât și
 *     factura organizator linkată dintr-un singur transfer real.
 */
class PendingPaymentResource extends Resource
{
    protected static ?string $model = MarketplacePayout::class;
    protected static ?string $navigationLabel = 'De plătit';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'reference';

    /**
     * Badge: număr de plăți în așteptare pentru marketplace-ul curent.
     */
    public static function getNavigationBadge(): ?string
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) {
            return null;
        }

        $count = MarketplacePayout::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->whereNotNull('event_id')
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Restrict to:
     *   - marketplace_client of the current admin
     *   - event-attached payouts only (general/organizer-level payouts stay
     *     in PayoutResource)
     *   - statuses we want to surface as "to pay": pending → in așteptare,
     *     completed → achitat (history), rejected → respins (history).
     */
    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id)
            ->whereNotNull('event_id')
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed', 'rejected']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_with_meta')
                    ->label('Eveniment')
                    ->state(function ($record) {
                        $event = $record->event;
                        if (!$event) {
                            return '—';
                        }
                        $t = $event->title;
                        return is_array($t)
                            ? ($t['ro'] ?? $t['en'] ?? (reset($t) ?: 'Untitled'))
                            : ($t ?? 'Untitled');
                    })
                    ->description(function ($record) {
                        $event = $record->event;
                        if (!$event) {
                            return null;
                        }
                        $parts = [];
                        $start = $event->start_date?->format('d.m.Y');
                        $end = $event->end_date?->format('d.m.Y');
                        if ($start) {
                            $parts[] = ($end && $end !== $start) ? "{$start} – {$end}" : $start;
                        }
                        if ($event->venue) {
                            $vn = $event->venue->name;
                            $vname = is_array($vn) ? ($vn['ro'] ?? $vn['en'] ?? null) : $vn;
                            if ($vname) {
                                $parts[] = $vname;
                            }
                            if ($event->venue->city) {
                                $parts[] = $event->venue->city;
                            }
                        }
                        return implode(' · ', $parts) ?: null;
                    })
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record->id]))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('event', function ($q) use ($term) {
                            // Title is a translatable JSON column — search via raw cast
                            $q->whereRaw('LOWER(CAST(title AS TEXT)) LIKE ?', [$term]);
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('organizer.company_name')
                    ->label('Organizator')
                    ->state(fn ($record) => $record->organizer?->company_name
                        ?? $record->organizer?->name
                        ?? '—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referință')
                    ->description(fn ($record) => $record->decont_series)
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('sold_de_plata')
                    ->label('Sold de plată')
                    ->state(function ($record) {
                        return self::computeBalance($record);
                    })
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2) . ' RON')
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Sort by amount as proxy — the invoice subtraction is rare
                        // enough that exact ordering by computed balance isn't worth
                        // a join here.
                        return $query->orderBy('amount', $direction);
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn ($record) => match ($record->status) {
                        'pending', 'approved', 'processing' => 'În așteptare',
                        'completed' => 'Achitat',
                        'rejected' => 'Respins',
                        default => $record->status,
                    })
                    ->color(fn ($state) => match ($state) {
                        'În așteptare' => 'warning',
                        'Achitat' => 'success',
                        'Respins' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data decont')
                    ->date('d.m.Y'),
            ])
            ->filters([
                // Status filtering is handled by the toolbar tabs (În așteptare /
                // Achitat / Respins) on the list page — see ListPendingPayments::getTabs().
                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizator')
                    ->relationship('organizer', 'company_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('Vezi'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Compute the net amount the marketplace actually transfers to the
     * organizer for this payout:
     *   payout.amount − Σ(linked organizer-invoice amounts that are still
     *   outstanding/new/overdue).
     *
     * Paid invoices are excluded — they were already netted in a prior
     * settlement. Cancelled too — they no longer represent a real debt.
     */
    public static function computeBalance(MarketplacePayout $payout): float
    {
        $invoiceTotal = (float) \App\Models\Invoice::query()
            ->where('marketplace_payout_id', $payout->id)
            ->where('meta->is_pos_commission', true)
            ->whereIn('status', ['outstanding', 'new', 'overdue'])
            ->sum('amount');

        return round((float) $payout->amount - $invoiceTotal, 2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendingPayments::route('/'),
            'view' => Pages\ViewPendingPayment::route('/{record}'),
        ];
    }
}
