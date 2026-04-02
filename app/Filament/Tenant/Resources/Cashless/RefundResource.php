<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Filament\Tenant\Resources\Cashless\RefundResource\Pages;
use App\Models\Cashless\CashlessRefund;
use App\Services\Cashless\RefundService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RefundResource extends Resource
{
    protected static ?string $model = CashlessRefund::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Refunds';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'cashless-refunds';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sale.sale_number')->label('Sale #')->searchable(),
                Tables\Columns\BadgeColumn::make('refund_type')
                    ->colors(['primary' => 'full', 'warning' => 'partial', 'gray' => 'auto', 'info' => 'compensation']),
                Tables\Columns\TextColumn::make('total_refund_cents')->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['warning' => 'pending', 'success' => 'processed', 'danger' => 'rejected', 'info' => 'approved']),
                Tables\Columns\TextColumn::make('reason')->limit(40)->tooltip(fn ($record) => $record->reason),
                Tables\Columns\TextColumn::make('requestedBy.name')->label('Requested By'),
                Tables\Columns\TextColumn::make('approvedBy.name')->label('Approved By')->placeholder('-'),
                Tables\Columns\TextColumn::make('requested_at')->dateTime('d M H:i')->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')->label('Vendor')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'processed' => 'Processed', 'rejected' => 'Rejected']),
                Tables\Filters\SelectFilter::make('refund_type')
                    ->options(['full' => 'Full', 'partial' => 'Partial', 'auto' => 'Auto', 'compensation' => 'Compensation']),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve & Process')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(RefundService::class)->approveAndProcess($record, auth()->id());
                            Notification::make()->title('Refund processed')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')->required(),
                    ])
                    ->action(function ($record, array $data) {
                        app(RefundService::class)->reject($record, $data['rejection_reason'], auth()->id());
                        Notification::make()->title('Refund rejected')->warning()->send();
                    }),
            ])
            ->defaultSort('requested_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefunds::route('/'),
        ];
    }
}
