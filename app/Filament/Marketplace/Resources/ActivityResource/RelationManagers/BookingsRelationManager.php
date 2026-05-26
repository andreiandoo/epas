<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\RelationManagers;

use App\Models\ActivityBooking;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Bookings tab on the Activity edit page — scoped to ONE activity, grouped
 * by booking_date so the organizer sees "what does my Tuesday look like".
 *
 * Mirrors ActivityBookingResource's table actions (check-in / no-show /
 * cancel) but lives in the edit-activity context so it's the natural
 * place an organizer lands when they want to see "who's coming to MY
 * Activity X?".
 */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Rezervări';

    protected static ?string $modelLabel = 'Rezervare';

    protected static ?string $pluralModelLabel = 'Rezervări';

    public function form(Schema $schema): Schema
    {
        // Read-only relation manager — no inline create/edit. Use the
        // dedicated ActivityBookingResource for full CRUD if ever needed.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('confirmation_code')
            ->columns([
                Tables\Columns\TextColumn::make('confirmation_code')
                    ->label('Cod')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('Data')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('slot_start_time')
                    ->label('Ora')
                    ->formatStateUsing(fn ($state) => $state
                        ? (is_string($state) ? substr($state, 0, 5) : $state->format('H:i'))
                        : '—')
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Locuri')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, $record) => $record->customer
                        ? trim(($record->customer->first_name ?? '') . ' ' . ($record->customer->last_name ?? '')) . "\n" . $state
                        : ($state ?? '—'))
                    ->searchable()
                    ->wrap()
                    ->limit(45),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) =>
                        number_format(($state ?? 0) / 100, 2, ',', '.') . ' ' . ($record->currency ?? 'RON'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ActivityBooking::STATUS_PAID,
                        ActivityBooking::STATUS_CONFIRMED       => 'success',
                        ActivityBooking::STATUS_CHECKED_IN      => 'info',
                        ActivityBooking::STATUS_PENDING_PAYMENT => 'warning',
                        ActivityBooking::STATUS_CANCELLED       => 'danger',
                        ActivityBooking::STATUS_NO_SHOW         => 'gray',
                        default                                  => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        ActivityBooking::STATUS_PAID            => 'Plătit',
                        ActivityBooking::STATUS_CONFIRMED       => 'Confirmat',
                        ActivityBooking::STATUS_CHECKED_IN      => 'Validat',
                        ActivityBooking::STATUS_PENDING_PAYMENT => 'În așteptare',
                        ActivityBooking::STATUS_CANCELLED       => 'Anulat',
                        ActivityBooking::STATUS_NO_SHOW         => 'No-show',
                        default                                  => $state,
                    }),
            ])
            ->defaultGroup('booking_date')
            ->defaultSort('booking_date', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('booking_date')->orderBy('slot_start_time'))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        ActivityBooking::STATUS_PAID            => 'Plătit',
                        ActivityBooking::STATUS_CONFIRMED       => 'Confirmat',
                        ActivityBooking::STATUS_CHECKED_IN      => 'Validat',
                        ActivityBooking::STATUS_PENDING_PAYMENT => 'În așteptare',
                        ActivityBooking::STATUS_CANCELLED       => 'Anulat',
                        ActivityBooking::STATUS_NO_SHOW         => 'No-show',
                    ]),

                Filter::make('today')
                    ->label('Doar azi')
                    ->query(fn (Builder $query) => $query->whereDate('booking_date', now()->toDateString()))
                    ->toggle(),

                Filter::make('upcoming')
                    ->label('Doar viitoare')
                    ->query(fn (Builder $query) => $query->whereDate('booking_date', '>=', now()->toDateString()))
                    ->toggle(),
            ])
            ->headerActions([
                // Deep link to the standalone resource pre-filtered to this
                // activity — useful when the organizer wants to see the
                // bookings list with its full toolbar.
                Action::make('openFullList')
                    ->label('Listă completă')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn () => route('filament.marketplace.resources.activity-bookings.index', [
                        'activity_id' => $this->ownerRecord->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (ActivityBooking $record) => route('filament.marketplace.resources.activity-bookings.view', [
                        'record' => $record->id,
                    ])),

                Action::make('checkIn')
                    ->label('Validează')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ActivityBooking $record): bool => in_array($record->status, [
                        ActivityBooking::STATUS_PAID,
                        ActivityBooking::STATUS_CONFIRMED,
                    ], true))
                    ->requiresConfirmation()
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
                            ->title('Rezervarea a fost validată')
                            ->success()
                            ->send();
                    }),

                Action::make('markNoShow')
                    ->label('No-show')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (ActivityBooking $record): bool => in_array($record->status, [
                        ActivityBooking::STATUS_PAID,
                        ActivityBooking::STATUS_CONFIRMED,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (ActivityBooking $record): void {
                        $record->update(['status' => ActivityBooking::STATUS_NO_SHOW]);
                        Notification::make()->title('Marcat ca no-show')->success()->send();
                    }),

                Action::make('cancelBooking')
                    ->label('Anulează')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ActivityBooking $record): bool => ! in_array($record->status, [
                        ActivityBooking::STATUS_CANCELLED,
                        ActivityBooking::STATUS_CHECKED_IN,
                    ], true))
                    ->requiresConfirmation()
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
                        Notification::make()->title('Rezervarea a fost anulată')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
