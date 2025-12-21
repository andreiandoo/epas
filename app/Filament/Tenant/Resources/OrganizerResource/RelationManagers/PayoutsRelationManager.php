<?php

namespace App\Filament\Tenant\Resources\OrganizerResource\RelationManagers;

use App\Models\Marketplace\MarketplacePayout;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payouts';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payout Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->required(),

                        Forms\Components\DatePicker::make('period_start')
                            ->label('Period Start')
                            ->disabled(),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Period End')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->disabled()
                            ->prefix('RON'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->date()
                    ->description(fn (MarketplacePayout $record) => 'to ' . $record->period_end?->format('M j, Y')),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not processed'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                // Payouts are generated through the PayoutService
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record) => $record->status === 'pending')
                    ->action(function (MarketplacePayout $record) {
                        $record->markAsProcessing();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Process Payout')
                    ->modalDescription('Are you sure you want to mark this payout as processing?'),

                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record) => $record->status === 'processing')
                    ->form([
                        Forms\Components\TextInput::make('transaction_reference')
                            ->label('Transaction Reference')
                            ->required(),
                    ])
                    ->action(function (MarketplacePayout $record, array $data) {
                        $record->markAsCompleted($data['transaction_reference']);
                    })
                    ->modalHeading('Complete Payout'),

                Tables\Actions\Action::make('fail')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplacePayout $record) => in_array($record->status, ['pending', 'processing']))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Failure Reason')
                            ->required(),
                    ])
                    ->action(function (MarketplacePayout $record, array $data) {
                        $record->markAsFailed($data['reason']);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark Payout as Failed'),
            ])
            ->bulkActions([]);
    }
}
