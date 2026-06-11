<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomerEvent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPlatformConversions extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Conversions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CoreCustomerEvent::query()
                    ->purchases()
                    ->with('coreCustomer')
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('conversion_value')
                    ->label('Value')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn ($record) => $record->getAttributionSource())
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Google Ads' => 'danger',
                        'Facebook Ads' => 'info',
                        'TikTok Ads' => 'warning',
                        'LinkedIn Ads' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('customer')
                    ->label('Customer')
                    ->getStateUsing(fn ($record) => $record->coreCustomer?->full_name ?? ($record->coreCustomer?->email ? 'Identified' : 'Anonymous'))
                    ->icon(fn ($record) => $record->coreCustomer?->email_hash ? 'heroicon-m-check-circle' : 'heroicon-m-question-mark-circle')
                    ->iconColor(fn ($record) => $record->coreCustomer?->email_hash ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('device_type')
                    ->label('Device')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->getStateUsing(fn ($record) => implode(', ', array_filter([$record->city, $record->country_code]))),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No conversions yet')
            ->emptyStateDescription('Conversions will appear here as they happen.')
            ->emptyStateIcon('heroicon-o-shopping-cart');
    }
}
