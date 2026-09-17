<?php

namespace App\Filament\Marketplace\Resources\MarketplacePartnerResource\RelationManagers;

use App\Jobs\DeliverPartnerRequestJob;
use App\Models\MarketplacePartnerDelivery;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Requests sent to the partner (last 30 days), read-only, with a manual resend.
 */
class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Trimiteri către partener';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i:s', timezone: 'Europe/Bucharest'),
                Tables\Columns\TextColumn::make('type')->label('Tip')->badge(),
                Tables\Columns\TextColumn::make('subject_id')->label('ID')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        MarketplacePartnerDelivery::STATUS_SENT => 'Trimis',
                        MarketplacePartnerDelivery::STATUS_FAILED => 'Eșuat',
                        default => 'În așteptare',
                    })
                    ->color(fn (string $state) => match ($state) {
                        MarketplacePartnerDelivery::STATUS_SENT => 'success',
                        MarketplacePartnerDelivery::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('attempts')->label('Încercări'),
                Tables\Columns\TextColumn::make('http_status')->label('HTTP')->placeholder('—'),
                Tables\Columns\TextColumn::make('error')
                    ->label('Eroare')
                    ->limit(60)
                    ->tooltip(fn (MarketplacePartnerDelivery $record) => $record->error)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stare')
                    ->options([
                        MarketplacePartnerDelivery::STATUS_PENDING => 'În așteptare',
                        MarketplacePartnerDelivery::STATUS_SENT => 'Trimis',
                        MarketplacePartnerDelivery::STATUS_FAILED => 'Eșuat',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('resend')
                    ->label('Retrimite')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (MarketplacePartnerDelivery $record) => $record->status === MarketplacePartnerDelivery::STATUS_FAILED)
                    ->action(function (MarketplacePartnerDelivery $record) {
                        $record->forceFill(['status' => MarketplacePartnerDelivery::STATUS_PENDING, 'error' => null])->save();
                        DeliverPartnerRequestJob::dispatch($record->id);

                        Notification::make()->title('Trimiterea a fost pusă din nou în coadă')->success()->send();
                    }),
            ]);
    }
}
