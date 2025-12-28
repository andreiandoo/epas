<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\AffiliateWithdrawalResource\Pages;
use App\Models\AffiliateWithdrawal;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class AffiliateWithdrawalResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = AffiliateWithdrawal::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?string $navigationParentItem = 'Affiliates';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Withdrawal';

    protected static ?string $pluralModelLabel = 'Withdrawals';

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        $count = AffiliateWithdrawal::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('affiliates');
    }

    public static function canCreate(): bool
    {
        return false; // Withdrawals are created by affiliates, not admins
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Withdrawal Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->disabled(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->disabled()
                            ->prefix(fn ($record) => $record?->currency ?? 'RON'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->placeholder('External payment reference')
                            ->helperText('Reference from bank/PayPal/etc.'),
                    ])
                    ->columns(2),

                SC\Section::make('Affiliate Information')
                    ->schema([
                        Forms\Components\Placeholder::make('affiliate_name')
                            ->label('Affiliate')
                            ->content(fn ($record) => $record?->affiliate?->name ?? 'N/A'),

                        Forms\Components\Placeholder::make('affiliate_email')
                            ->label('Email')
                            ->content(fn ($record) => $record?->affiliate?->contact_email ?? 'N/A'),

                        Forms\Components\Placeholder::make('payment_method')
                            ->label('Payment Method')
                            ->content(fn ($record) => $record?->getPaymentMethodLabel() ?? 'N/A'),

                        Forms\Components\Placeholder::make('payment_details_display')
                            ->label('Payment Details')
                            ->content(fn ($record) => $record?->getFormattedPaymentDetails() ?? 'N/A'),
                    ])
                    ->columns(2),

                SC\Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Internal notes about this withdrawal...'),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->rows(2)
                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->required(fn (Forms\Get $get) => $get('status') === 'rejected'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency ?? 'RON')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn ($record) => $record->getPaymentMethodLabel())
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
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
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('affiliate')
                    ->relationship('affiliate', 'name'),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'bank_transfer' => 'Bank Transfer',
                        'paypal' => 'PayPal',
                        'revolut' => 'Revolut',
                        'wise' => 'Wise',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Withdrawal')
                    ->modalDescription('Mark this withdrawal as processing. You will need to manually process the payment.')
                    ->action(function ($record) {
                        $record->markAsProcessing(auth()->id());
                        Notification::make()
                            ->success()
                            ->title('Withdrawal approved')
                            ->body('The withdrawal has been marked as processing.')
                            ->send();
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'processing']))
                    ->form([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->placeholder('Payment reference from bank/PayPal'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsCompleted($data['transaction_id'] ?? null, $data['admin_notes'] ?? null);
                        Notification::make()
                            ->success()
                            ->title('Withdrawal completed')
                            ->body('The withdrawal has been marked as completed.')
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'processing']))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Explain why this withdrawal is being rejected...'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Withdrawal')
                    ->modalDescription('The withdrawal amount will be returned to the affiliate\'s available balance.')
                    ->action(function ($record, array $data) {
                        $record->reject($data['rejection_reason'], auth()->id());
                        Notification::make()
                            ->warning()
                            ->title('Withdrawal rejected')
                            ->body('The withdrawal has been rejected and the amount returned to the affiliate.')
                            ->send();
                    }),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status === 'pending') {
                                $record->markAsProcessing(auth()->id());
                                $count++;
                            }
                        }
                        Notification::make()
                            ->success()
                            ->title("{$count} withdrawals approved")
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListAffiliateWithdrawals::route('/'),
            'view' => Pages\ViewAffiliateWithdrawal::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->with(['affiliate']);
    }
}
