<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomer;
use App\Services\Platform\DuplicateDetectionService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DuplicateAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Potential Duplicate Customers';

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\Platform\DuplicateCustomerMatch::query()
                    ->where('is_resolved', false)
                    ->orderByDesc('match_score')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer_a.uuid')
                    ->label('Customer A')
                    ->searchable()
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->customerA?->email_hash),

                Tables\Columns\TextColumn::make('customer_b.uuid')
                    ->label('Customer B')
                    ->searchable()
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->customerB?->email_hash),

                Tables\Columns\TextColumn::make('match_score')
                    ->label('Score')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 0) . '%')
                    ->color(fn ($state) => match (true) {
                        $state >= 0.95 => 'danger',
                        $state >= 0.85 => 'warning',
                        default => 'info',
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'definite' => 'danger',
                        'likely' => 'warning',
                        'possible' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('matched_fields')
                    ->label('Matched On')
                    ->formatStateUsing(function ($state) {
                        if (!is_array($state)) {
                            return '-';
                        }
                        return implode(', ', array_keys(array_filter($state)));
                    })
                    ->limit(30),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Detected')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Action::make('merge')
                    ->label('Merge')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Merge Customers')
                    ->modalDescription('This will merge Customer A into Customer B. All events, sessions, and conversions will be transferred.')
                    ->action(function ($record) {
                        $customerA = $record->customerA;
                        $customerB = $record->customerB;

                        if ($customerA && $customerB) {
                            $customerA->mergeInto($customerB);
                            $record->update([
                                'is_resolved' => true,
                                'resolution' => 'merged',
                                'resolved_at' => now(),
                            ]);
                        }
                    }),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->action(function ($record) {
                        $record->update([
                            'is_resolved' => true,
                            'resolution' => 'dismissed',
                            'resolved_at' => now(),
                        ]);
                    }),
            ])
            ->emptyStateHeading('No duplicates detected')
            ->emptyStateDescription('The system has not detected any potential duplicate customers.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public static function canView(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('duplicate_customer_matches');
    }
}
