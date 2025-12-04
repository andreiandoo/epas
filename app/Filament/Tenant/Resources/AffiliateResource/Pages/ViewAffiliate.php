<?php

namespace App\Filament\Tenant\Resources\AffiliateResource\Pages;

use App\Filament\Tenant\Resources\AffiliateResource;
use App\Services\AffiliateTrackingService;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliate extends ViewRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $stats = app(AffiliateTrackingService::class)->getAffiliateStats($this->record->id);

        return $infolist
            ->schema([
                Section::make('Statistics Overview')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('total_conversions')
                                        ->label('Total Conversions')
                                        ->state($stats['total_conversions'])
                                        ->size('lg')
                                        ->weight('bold'),
                                ]),
                                Group::make([
                                    TextEntry::make('approved_conversions')
                                        ->label('Approved')
                                        ->state($stats['approved_conversions'])
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color('success'),
                                ]),
                                Group::make([
                                    TextEntry::make('pending_conversions')
                                        ->label('Pending')
                                        ->state($stats['pending_conversions'])
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color('warning'),
                                ]),
                                Group::make([
                                    TextEntry::make('reversed_conversions')
                                        ->label('Reversed')
                                        ->state($stats['reversed_conversions'])
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color('danger'),
                                ]),
                            ]),
                    ]),

                Section::make('Commission Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('total_commission')
                                        ->label('Total Commission Earned')
                                        ->state(number_format($stats['total_commission'], 2) . ' RON')
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color('success'),
                                ]),
                                Group::make([
                                    TextEntry::make('pending_commission')
                                        ->label('Pending Commission')
                                        ->state(number_format($stats['pending_commission'], 2) . ' RON')
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color('warning'),
                                ]),
                                Group::make([
                                    TextEntry::make('total_sales')
                                        ->label('Total Sales Generated')
                                        ->state(number_format($stats['total_sales'], 2) . ' RON')
                                        ->size('lg')
                                        ->weight('bold'),
                                ]),
                            ]),
                    ]),

                Section::make('Affiliate Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Affiliate Code')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('name')
                            ->label('Name'),

                        TextEntry::make('contact_email')
                            ->label('Email')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'active' => 'success',
                                'suspended' => 'warning',
                                'inactive' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('commission_display')
                            ->label('Commission Rate')
                            ->state(function ($record) {
                                if ($record->commission_type === 'fixed') {
                                    return number_format($record->commission_rate, 2) . ' RON per order';
                                }
                                return $record->commission_rate . '% of order value';
                            }),

                        TextEntry::make('coupon')
                            ->label('Coupon Code')
                            ->state(function ($record) {
                                $coupon = $record->coupons()->where('is_active', true)->first();
                                return $coupon?->coupon_code ?? '-';
                            })
                            ->copyable(),
                    ]),

                Section::make('Tracking Links')
                    ->description('Generate tracking links for this affiliate')
                    ->schema([
                        TextEntry::make('tracking_url')
                            ->label('Default Tracking URL')
                            ->state(function ($record) {
                                return url('/') . '?aff=' . $record->code;
                            })
                            ->copyable(),
                    ]),
            ]);
    }
}
