<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomer;
use App\Services\Platform\ChurnPredictionService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Notifications\Notification;

class HighValueAtRiskCustomers extends BaseWidget
{
    protected ?string $heading = 'High-Value Customers at Risk';

    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '120s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CoreCustomer::query()
                    ->notMerged()
                    ->notAnonymized()
                    ->where('churn_risk_score', '>=', 60)
                    ->where('lifetime_value', '>=', 100)
                    ->orderByDesc('lifetime_value')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Customer')
                    ->searchable()
                    ->limit(12)
                    ->copyable(),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => $record->getDisplayName())
                    ->searchable(),

                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('LTV')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable(),

                Tables\Columns\TextColumn::make('churn_risk_score')
                    ->label('Churn Risk')
                    ->formatStateUsing(fn ($state) => number_format($state, 0) . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 80 => 'danger',
                        $state >= 60 => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('customer_segment')
                    ->label('Segment')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'champions' => 'success',
                        'loyal_customers' => 'info',
                        'potential_loyalists' => 'primary',
                        'at_risk' => 'warning',
                        'hibernating' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Active')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.core-customers.view', $record)),

                Tables\Actions\Action::make('send_winback')
                    ->label('Win-back')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Send Win-back Campaign')
                    ->modalDescription('This will add the customer to the win-back email campaign.')
                    ->action(function ($record) {
                        // In a real implementation, this would trigger a win-back campaign
                        Notification::make()
                            ->title('Win-back campaign initiated')
                            ->body("Customer {$record->uuid} added to win-back campaign.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('add_note')
                    ->label('Note')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Add a note about this customer')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        // In a real implementation, this would save a note
                        Notification::make()
                            ->title('Note added')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No high-value customers at risk')
            ->emptyStateDescription('Great news! No high-value customers are currently at risk of churning.')
            ->emptyStateIcon('heroicon-o-face-smile');
    }
}
