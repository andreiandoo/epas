<?php

namespace App\Filament\Tenant\Resources\CustomerPointsResource\Pages;

use App\Filament\Tenant\Resources\CustomerPointsResource;
use App\Models\Gamification\PointsTransaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Infolists\Components as Info;

class ViewCustomerPoints extends ViewRecord
{
    protected static string $resource = CustomerPointsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('adjust')
                ->label('Adjust Points')
                ->icon('heroicon-o-adjustments-horizontal')
                ->form([
                    Forms\Components\TextInput::make('points')
                        ->label('Points')
                        ->numeric()
                        ->required()
                        ->helperText('Use negative to remove points'),
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->adjustPoints(
                        (int) $data['points'],
                        $data['reason'],
                        auth()->id()
                    );

                    Notification::make()
                        ->title('Points adjusted successfully')
                        ->success()
                        ->send();

                    $this->refreshFormData(['current_balance', 'total_earned', 'total_spent']);
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Info\Section::make('Customer')
                    ->schema([
                        Info\TextEntry::make('customer.email')
                            ->label('Email'),
                        Info\TextEntry::make('customer.first_name')
                            ->label('Name')
                            ->formatStateUsing(fn ($record) => trim(($record->customer->first_name ?? '') . ' ' . ($record->customer->last_name ?? '')) ?: '-'),
                        Info\TextEntry::make('referral_code')
                            ->label('Referral Code')
                            ->copyable(),
                    ])->columns(3),

                Info\Section::make('Points Balance')
                    ->schema([
                        Info\TextEntry::make('current_balance')
                            ->label('Current Balance')
                            ->badge()
                            ->color('success'),
                        Info\TextEntry::make('total_earned')
                            ->label('Total Earned'),
                        Info\TextEntry::make('total_spent')
                            ->label('Total Spent'),
                        Info\TextEntry::make('total_expired')
                            ->label('Total Expired'),
                        Info\TextEntry::make('pending_points')
                            ->label('Pending'),
                    ])->columns(5),

                Info\Section::make('Tier & Activity')
                    ->schema([
                        Info\TextEntry::make('current_tier')
                            ->label('Tier')
                            ->badge(),
                        Info\TextEntry::make('tier_points')
                            ->label('Tier Points'),
                        Info\TextEntry::make('last_earned_at')
                            ->label('Last Earned')
                            ->dateTime(),
                        Info\TextEntry::make('last_spent_at')
                            ->label('Last Spent')
                            ->dateTime(),
                    ])->columns(4),

                Info\Section::make('Referrals')
                    ->schema([
                        Info\TextEntry::make('referral_count')
                            ->label('Total Referrals'),
                        Info\TextEntry::make('referral_points_earned')
                            ->label('Points from Referrals'),
                    ])->columns(2),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            CustomerPointsResource\Widgets\PointsTransactionsWidget::class,
        ];
    }
}
