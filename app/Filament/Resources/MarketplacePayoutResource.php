<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplacePayoutResource\Pages;
use App\Models\MarketplacePayout;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;

class MarketplacePayoutResource extends Resource
{
    protected static ?string $model = MarketplacePayout::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Payouts';

    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Payout Details')
                    ->schema([
                        Forms\Components\Select::make('marketplace_client_id')
                            ->relationship('marketplaceClient', 'name')
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('marketplace_organizer_id')
                            ->relationship('organizer', 'name')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('reference')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('RON')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->helperText('Bank transfer reference or transaction ID'),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'bank_transfer' => 'Bank Transfer',
                                'paypal' => 'PayPal',
                                'wise' => 'Wise',
                                'other' => 'Other',
                            ]),
                        Forms\Components\Textarea::make('payment_notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record?->isProcessing() || $record?->isCompleted()),

                Forms\Components\Section::make('Rejection')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->rows(2)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record?->isRejected()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')
                    ->money('RON')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processing' => 'primary',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->relationship('marketplaceClient', 'name')
                    ->label('Marketplace'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record) => $record->canBeApproved())
                    ->action(function (MarketplacePayout $record) {
                        $record->approve(auth()->id());
                    }),
                Tables\Actions\Action::make('process')
                    ->label('Mark Processing')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record) => $record->canBeProcessed())
                    ->action(function (MarketplacePayout $record) {
                        $record->markAsProcessing(auth()->id());
                    }),
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record) => $record->canBeCompleted())
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->required()
                            ->helperText('Bank transfer reference or transaction ID'),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (MarketplacePayout $record, array $data) {
                        $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplacePayout $record) => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MarketplacePayout $record, array $data) {
                        $record->reject(auth()->id(), $data['reason']);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->canBeApproved()) {
                                    $record->approve(auth()->id());
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Infolists\Components\Section::make('Payout Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'processing' => 'primary',
                                'completed' => 'success',
                                'rejected' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('organizer.name')
                            ->label('Organizer'),
                        Infolists\Components\TextEntry::make('marketplaceClient.name')
                            ->label('Marketplace'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Amount Breakdown')
                    ->schema([
                        Infolists\Components\TextEntry::make('gross_amount')
                            ->money('RON'),
                        Infolists\Components\TextEntry::make('commission_amount')
                            ->money('RON')
                            ->label('Commission'),
                        Infolists\Components\TextEntry::make('fees_amount')
                            ->money('RON')
                            ->label('Fees'),
                        Infolists\Components\TextEntry::make('adjustments_amount')
                            ->money('RON')
                            ->label('Adjustments'),
                        Infolists\Components\TextEntry::make('amount')
                            ->money('RON')
                            ->label('Net Payout')
                            ->weight('bold'),
                    ])
                    ->columns(5),

                Infolists\Components\Section::make('Period')
                    ->schema([
                        Infolists\Components\TextEntry::make('period_start')
                            ->date(),
                        Infolists\Components\TextEntry::make('period_end')
                            ->date(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Payout Method')
                    ->schema([
                        Infolists\Components\TextEntry::make('payout_method.bank_name')
                            ->label('Bank'),
                        Infolists\Components\TextEntry::make('payout_method.iban')
                            ->label('IBAN')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('payout_method.account_holder')
                            ->label('Account Holder'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('organizer_notes')
                            ->label('Organizer Notes')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Admin Notes')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->organizer_notes || $record->admin_notes),

                Infolists\Components\Section::make('Rejection Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('rejectedByUser.name')
                            ->label('Rejected By'),
                        Infolists\Components\TextEntry::make('rejected_at')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->isRejected()),

                Infolists\Components\Section::make('Payment Confirmation')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_reference')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('payment_method'),
                        Infolists\Components\TextEntry::make('payment_notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->payment_reference),

                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Requested')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->approved_at),
                        Infolists\Components\TextEntry::make('processed_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->processed_at),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->completed_at),
                    ])
                    ->columns(4),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplacePayouts::route('/'),
            'view' => Pages\ViewMarketplacePayout::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
