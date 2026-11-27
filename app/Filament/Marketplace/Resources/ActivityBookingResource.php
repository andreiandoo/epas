<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityBookingResource\Pages;
use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Filament admin resource for Activity bookings.
 *
 * Mirrors the OrderResource pattern (scoped to current marketplace) but
 * surfaces the per-slot reservation view that operators actually want at
 * the venue door: who's coming, on what date+time, how many people, and
 * a one-click "Check-in" action.
 *
 * Gated by the `activities-module` microservice toggle — invisible and
 * unreachable for marketplaces that haven't activated it.
 */
class ActivityBookingResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = ActivityBooking::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Rezervări activități';

    protected static ?string $modelLabel = 'Rezervare';

    protected static ?string $pluralModelLabel = 'Rezervări activități';

    protected static \UnitEnum|string|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('activities-module');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('activities-module');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::marketplaceHasMicroservice('activities-module')) {
            return null;
        }
        $marketplace = static::getMarketplaceClient();
        if (! $marketplace) {
            return null;
        }
        // Show today's confirmed-and-upcoming bookings as the badge — same
        // signal a venue manager glances at to size the day.
        return (string) static::getEloquentQuery()
            ->where('booking_date', now()->toDateString())
            ->whereIn('status', [
                ActivityBooking::STATUS_PAID,
                ActivityBooking::STATUS_CONFIRMED,
            ])
            ->count();
    }

    /**
     * Scope to the current marketplace + accept optional ?activity_id / ?date
     * query params so deep-links from the Activity admin land filtered.
     */
    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        $query = parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);

        if ($activityId = request()->query('activity_id')) {
            $query->where('activity_id', $activityId);
        }
        if ($date = request()->query('date')) {
            $query->whereDate('booking_date', $date);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make(__('Rezervare'))
                ->columns(3)
                ->schema([
                    SC\TextEntry::make('confirmation_code')
                        ->label(__('Cod confirmare'))
                        ->copyable()
                        ->weight('bold')
                        ->color('primary'),
                    SC\TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            ActivityBooking::STATUS_PAID         => 'success',
                            ActivityBooking::STATUS_CONFIRMED    => 'success',
                            ActivityBooking::STATUS_CHECKED_IN   => 'info',
                            ActivityBooking::STATUS_PENDING_PAYMENT => 'warning',
                            ActivityBooking::STATUS_CANCELLED    => 'danger',
                            ActivityBooking::STATUS_NO_SHOW      => 'gray',
                            default => 'gray',
                        }),
                    SC\TextEntry::make('booking_date')
                        ->label(__('Data'))
                        ->date('d M Y'),
                    SC\TextEntry::make('slot_start_time')
                        ->label(__('Ora început'))
                        ->formatStateUsing(fn ($state) => $state
                            ? (is_string($state) ? substr($state, 0, 5) : $state->format('H:i'))
                            : '—'),
                    SC\TextEntry::make('slot_end_time')
                        ->label(__('Ora sfârșit'))
                        ->formatStateUsing(fn ($state) => $state
                            ? (is_string($state) ? substr($state, 0, 5) : $state->format('H:i'))
                            : '—'),
                    SC\TextEntry::make('participants_count')
                        ->label(__('Locuri folosite')),
                    SC\TextEntry::make('checked_in_at')
                        ->label(__('Validat la'))
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    SC\TextEntry::make('held_until')
                        ->label(__('Hold expiră la'))
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                ]),

            SC\Section::make(__('Activitate'))
                ->columns(3)
                ->schema([
                    SC\TextEntry::make('activity.title')
                        ->label(__('Activitate'))
                        ->formatStateUsing(fn ($state) => is_array($state)
                            ? ($state['ro'] ?? $state['en'] ?? '—')
                            : ($state ?? '—')),
                    SC\TextEntry::make('activity.organizer.name')
                        ->label(__('Organizator'))
                        ->placeholder('—'),
                    SC\TextEntry::make('activity.city.name')
                        ->label(__('Oraș'))
                        ->formatStateUsing(fn ($state) => is_array($state)
                            ? ($state['ro'] ?? $state['en'] ?? '—')
                            : ($state ?? '—')),
                ]),

            SC\Section::make(__('Client'))
                ->columns(3)
                ->schema([
                    SC\TextEntry::make('customer.first_name')
                        ->label(__('Prenume')),
                    SC\TextEntry::make('customer.last_name')
                        ->label(__('Nume')),
                    SC\TextEntry::make('customer.email')
                        ->label(__('Email'))
                        ->copyable(),
                    SC\TextEntry::make('customer.phone')
                        ->label(__('Telefon'))
                        ->placeholder('—'),
                ]),

            SC\Section::make(__('Plată'))
                ->columns(3)
                ->schema([
                    SC\TextEntry::make('total_cents')
                        ->label(__('Total'))
                        ->formatStateUsing(fn ($state, $record) =>
                            number_format(($state ?? 0) / 100, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')),
                    SC\TextEntry::make('commission_cents')
                        ->label(__('Comision'))
                        ->formatStateUsing(fn ($state, $record) =>
                            number_format(($state ?? 0) / 100, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')),
                    SC\TextEntry::make('order.order_number')
                        ->label(__('Comandă'))
                        ->copyable()
                        ->placeholder('—'),
                ]),

            SC\Section::make(__('Bilete'))
                ->collapsible()
                ->schema([
                    SC\RepeatableEntry::make('tickets')
                        ->label('')
                        ->schema([
                            SC\TextEntry::make('code')
                                ->label(__('Cod bilet'))
                                ->copyable(),
                            SC\TextEntry::make('attendee_name')
                                ->label(__('Beneficiar'))
                                ->placeholder('—'),
                            SC\TextEntry::make('status')
                                ->label(__('Status'))
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'valid'       => 'success',
                                    'checked_in'  => 'info',
                                    'pending'     => 'warning',
                                    'cancelled'   => 'danger',
                                    'refunded'    => 'danger',
                                    default       => 'gray',
                                }),
                            SC\TextEntry::make('checked_in_at')
                                ->label(__('Validat la'))
                                ->dateTime('d M H:i')
                                ->placeholder('—'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('confirmation_code')
                    ->label(__('Cod'))
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('activity.title')
                    ->label(__('Activitate'))
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state['ro'] ?? $state['en'] ?? '—')
                        : ($state ?? '—'))
                    ->searchable()
                    ->limit(35)
                    ->wrap(),

                Tables\Columns\TextColumn::make('booking_date')
                    ->label(__('Data'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('slot_start_time')
                    ->label(__('Ora'))
                    ->formatStateUsing(fn ($state) => $state
                        ? (is_string($state) ? substr($state, 0, 5) : $state->format('H:i'))
                        : '—')
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label(__('Locuri'))
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('customer.email')
                    ->label(__('Client'))
                    ->formatStateUsing(fn ($state, $record) => $record->customer
                        ? trim(($record->customer->first_name ?? '') . ' ' . ($record->customer->last_name ?? '')) . "\n" . $state
                        : ($state ?? '—'))
                    ->searchable()
                    ->wrap()
                    ->html(false)
                    ->limit(40),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state, $record) =>
                        number_format(($state ?? 0) / 100, 2, ',', '.') . ' ' . ($record->currency ?? 'RON'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ActivityBooking::STATUS_PAID,
                        ActivityBooking::STATUS_CONFIRMED   => 'success',
                        ActivityBooking::STATUS_CHECKED_IN  => 'info',
                        ActivityBooking::STATUS_PENDING_PAYMENT => 'warning',
                        ActivityBooking::STATUS_CANCELLED   => 'danger',
                        ActivityBooking::STATUS_NO_SHOW     => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        ActivityBooking::STATUS_PAID            => 'Plătit',
                        ActivityBooking::STATUS_CONFIRMED       => 'Confirmat',
                        ActivityBooking::STATUS_CHECKED_IN      => 'Validat',
                        ActivityBooking::STATUS_PENDING_PAYMENT => 'În așteptare',
                        ActivityBooking::STATUS_CANCELLED       => 'Anulat',
                        ActivityBooking::STATUS_NO_SHOW         => 'No-show',
                        default => $state,
                    }),
            ])
            ->defaultGroup('booking_date')
            ->defaultSort('booking_date', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('booking_date')->orderBy('slot_start_time'))
            ->filters([
                SelectFilter::make('activity_id')
                    ->label(__('Activitate'))
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();
                        if (! $marketplace) return [];
                        return Activity::where('marketplace_client_id', $marketplace->id)
                            ->orderBy('updated_at', 'desc')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn ($a) => [
                                $a->id => is_array($a->title)
                                    ? ($a->title['ro'] ?? $a->title['en'] ?? "Activitate #{$a->id}")
                                    : ($a->title ?? "Activitate #{$a->id}"),
                            ])
                            ->all();
                    })
                    ->searchable(),

                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->multiple()
                    ->options([
                        ActivityBooking::STATUS_PAID            => 'Plătit',
                        ActivityBooking::STATUS_CONFIRMED       => 'Confirmat',
                        ActivityBooking::STATUS_CHECKED_IN      => 'Validat',
                        ActivityBooking::STATUS_PENDING_PAYMENT => 'În așteptare',
                        ActivityBooking::STATUS_CANCELLED       => 'Anulat',
                        ActivityBooking::STATUS_NO_SHOW         => 'No-show',
                    ]),

                Filter::make('booking_date_range')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')->label(__('De la')),
                        \Filament\Forms\Components\DatePicker::make('to')->label(__('Până la')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('booking_date', '>=', $v))
                            ->when($data['to']   ?? null, fn ($q, $v) => $q->whereDate('booking_date', '<=', $v));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['from']) && empty($data['to'])) return null;
                        return 'Dată: ' . ($data['from'] ?? '…') . ' → ' . ($data['to'] ?? '…');
                    }),

                Filter::make('today')
                    ->label(__('Doar azi'))
                    ->query(fn (Builder $query) => $query->whereDate('booking_date', now()->toDateString()))
                    ->toggle(),

                Filter::make('upcoming')
                    ->label(__('Doar viitoare'))
                    ->query(fn (Builder $query) => $query->whereDate('booking_date', '>=', now()->toDateString()))
                    ->toggle(),

                Filter::make('not_checked_in')
                    ->label(__('Neîncă validate'))
                    ->query(fn (Builder $query) => $query
                        ->whereNull('checked_in_at')
                        ->where('status', '!=', ActivityBooking::STATUS_CANCELLED))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('checkIn')
                    ->label(__('Validează'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ActivityBooking $record): bool => in_array($record->status, [
                        ActivityBooking::STATUS_PAID,
                        ActivityBooking::STATUS_CONFIRMED,
                    ], true))
                    ->requiresConfirmation()
                    ->modalHeading(__('Validează rezervarea'))
                    ->modalDescription(fn (ActivityBooking $record) => sprintf(
                        'Marchezi rezervarea %s ca validată? Toate biletele asociate (%d) vor fi marcate ca verificate la intrare.',
                        $record->confirmation_code,
                        Ticket::where('activity_booking_id', $record->id)->count(),
                    ))
                    ->action(function (ActivityBooking $record): void {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status'        => ActivityBooking::STATUS_CHECKED_IN,
                                'checked_in_at' => now(),
                            ]);
                            Ticket::where('activity_booking_id', $record->id)
                                ->whereIn('status', ['valid', 'pending'])
                                ->update([
                                    'status'        => 'checked_in',
                                    'checked_in_at' => now(),
                                    'checked_in_by' => auth()->user()?->name ?? 'Admin marketplace',
                                ]);
                        });
                        Notification::make()
                            ->title(__('Rezervarea a fost validată'))
                            ->success()
                            ->send();
                    }),

                Action::make('markNoShow')
                    ->label(__('No-show'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (ActivityBooking $record): bool => in_array($record->status, [
                        ActivityBooking::STATUS_PAID,
                        ActivityBooking::STATUS_CONFIRMED,
                    ], true))
                    ->requiresConfirmation()
                    ->modalHeading(__('Marchează ca no-show'))
                    ->modalDescription(__('Clientul nu s-a prezentat. Locurile rămân blocate, dar rezervarea nu mai contează ca validată.'))
                    ->action(function (ActivityBooking $record): void {
                        $record->update(['status' => ActivityBooking::STATUS_NO_SHOW]);
                        Notification::make()
                            ->title(__('Rezervare marcată ca no-show'))
                            ->success()
                            ->send();
                    }),

                Action::make('cancelBooking')
                    ->label(__('Anulează'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ActivityBooking $record): bool => ! in_array($record->status, [
                        ActivityBooking::STATUS_CANCELLED,
                        ActivityBooking::STATUS_CHECKED_IN,
                    ], true))
                    ->requiresConfirmation()
                    ->modalHeading(__('Anulează rezervarea'))
                    ->modalDescription(__('Locurile vor fi eliberate. Acțiunea NU declanșează rambursarea — gestionează manual din pagina comenzii dacă plata a fost încasată.'))
                    ->action(function (ActivityBooking $record): void {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status'     => ActivityBooking::STATUS_CANCELLED,
                                'held_until' => null,
                            ]);
                            Ticket::where('activity_booking_id', $record->id)
                                ->whereIn('status', ['valid', 'pending'])
                                ->update(['status' => 'cancelled']);
                        });
                        Notification::make()
                            ->title(__('Rezervarea a fost anulată'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No destructive bulk actions for v1 — bookings are
                    // financial records and should be touched one at a time.
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityBookings::route('/'),
            'view'  => Pages\ViewActivityBooking::route('/{record}'),
        ];
    }
}
