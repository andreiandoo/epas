<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\PayoutResource\Pages;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceAdmin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PayoutResource extends Resource
{
    protected static ?string $model = MarketplacePayout::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        $count = static::getEloquentQuery()
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payout Request')
                    ->schema([
                        Forms\Components\Placeholder::make('reference_display')
                            ->label('Reference')
                            ->content(fn (?MarketplacePayout $record): string => $record?->reference ?? '-'),

                        Forms\Components\Placeholder::make('organizer_display')
                            ->label('Organizer')
                            ->content(fn (?MarketplacePayout $record): string => $record?->organizer?->name ?? '-'),

                        Forms\Components\Placeholder::make('amount_display')
                            ->label('Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?MarketplacePayout $record): string => $record?->status_label ?? '-'),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Amount Breakdown')
                    ->schema([
                        Forms\Components\Placeholder::make('gross_amount_display')
                            ->label('Gross Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->gross_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('commission_amount_display')
                            ->label('Commission')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->commission_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('fees_amount_display')
                            ->label('Fees')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->fees_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('net_amount_display')
                            ->label('Net Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
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
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
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

                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeApproved())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->approve($admin->id);
                    }),

                Tables\Actions\Action::make('process')
                    ->label('Mark Processing')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeProcessed())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->markAsProcessing($admin->id);
                    }),

                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeCompleted())
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Payment Notes')
                            ->rows(2),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->reject($admin->id, $data['rejection_reason']);
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
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
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}
