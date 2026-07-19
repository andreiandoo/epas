<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\InstallmentAgreementResource\Pages;
use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstallmentAgreementResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = InstallmentAgreement::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Plăți flexibile';

    protected static ?string $navigationLabel = 'Comenzi cu plăți flexibile';

    protected static ?string $modelLabel = 'Plan activ';

    protected static ?string $pluralModelLabel = 'Comenzi cu plăți flexibile';

    protected static ?string $slug = 'flexible-payment-agreements';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('marketplace_client_id', static::getMarketplaceClientId());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('flexible-payments');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')->label('Comandă')->searchable(),
                Tables\Columns\TextColumn::make('customer_email')->label('Client')->searchable(),
                Tables\Columns\TextColumn::make('plan_type')
                    ->label('Metodă')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'bnpl_single' ? 'BNPL' : 'Rate'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'info', 'completed' => 'success',
                        'defaulted', 'cancelled' => 'danger', 'refunded' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progres')
                    ->state(fn (InstallmentAgreement $r) => $r->paid_installments_count . '/' . ($r->number_of_installments + ($r->down_payment_cents > 0 ? 1 : 0))),
                Tables\Columns\TextColumn::make('outstanding')
                    ->label('Sold rămas')
                    ->state(fn (InstallmentAgreement $r) => number_format($r->outstandingCents() / 100, 2) . ' ' . $r->currency),
                Tables\Columns\TextColumn::make('next_due_at')->label('Următoarea scadență')->dateTime('d.m.Y'),
                Tables\Columns\TextColumn::make('customer_total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $r) => number_format($state / 100, 2) . ' ' . $r->currency),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'active' => 'Activ', 'completed' => 'Finalizat',
                    'defaulted' => 'Default', 'cancelled' => 'Anulat', 'refunded' => 'Refundat',
                ]),
                Tables\Filters\SelectFilter::make('plan_type')->label('Metodă')->options([
                    'installments' => 'Rate', 'bnpl_single' => 'BNPL',
                ]),
            ])
            ->actions([
                Action::make('chargeNext')
                    ->label('Debitează următoarea')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->visible(fn (InstallmentAgreement $r) => $r->status === 'active')
                    ->action(function (InstallmentAgreement $r) {
                        $next = $r->payments()
                            ->whereIn('status', ['scheduled', 'due', 'retrying', 'action_required', 'failed'])
                            ->where('sequence', '>', 0)
                            ->orderBy('due_date')->first();
                        if ($next) {
                            app(\App\Services\Installments\InstallmentChargeService::class)->charge($next);
                        }
                    }),
                Action::make('cancel')
                    ->label('Anulează plan')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (InstallmentAgreement $r) => in_array($r->status, ['active', 'pending']))
                    ->action(function (InstallmentAgreement $r) {
                        app(\App\Services\Installments\InstallmentDunningService::class)->cancel($r, 'manual_cancel');
                    }),
                Action::make('refund')
                    ->label('Retur')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->visible(fn (InstallmentAgreement $r) => in_array($r->status, ['active', 'completed']) && $r->paidCents() > 0)
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('event_cancelled')
                            ->label('Eveniment anulat (retur integral, inclusiv taxe)')
                            ->helperText('Bifat: clientul primește tot. Nebifat: retur client — taxele nereturnabile se rețin.')
                            ->default(false),
                    ])
                    ->action(function (InstallmentAgreement $r, array $data) {
                        $svc = app(\App\Services\Installments\InstallmentRefundService::class);
                        ($data['event_cancelled'] ?? false)
                            ? $svc->eventCancelled($r, 'admin_event_cancelled')
                            : $svc->customerReturn($r, 'admin_customer_return');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallmentAgreements::route('/'),
            'view' => Pages\ViewInstallmentAgreement::route('/{record}'),
        ];
    }
}
