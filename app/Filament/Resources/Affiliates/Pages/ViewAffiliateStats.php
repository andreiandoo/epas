<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use App\Services\AffiliateTrackingService;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ViewAffiliateStats extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = AffiliateResource::class;

    protected static string $view = 'filament.resources.affiliates.pages.view-affiliate-stats';

    public ?Affiliate $record = null;
    public array $stats = [];

    public function mount($record): void
    {
        $this->record = Affiliate::with(['coupons', 'links'])->findOrFail($record);

        $service = app(AffiliateTrackingService::class);
        $this->stats = $service->getAffiliateStats($this->record->id);
    }

    public function getTitle(): string
    {
        return "Statistics: {$this->record->name}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->record->conversions()->getQuery()
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_ref')
                    ->label('Order Reference')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Order Amount')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Commission')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('commission_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'percent',
                        'success' => 'fixed',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'reversed',
                    ])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('attributed_by')
                    ->label('Attributed By')
                    ->colors([
                        'info' => 'link',
                        'success' => 'coupon',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'reversed' => 'Reversed',
                    ]),

                Tables\Filters\SelectFilter::make('attributed_by')
                    ->label('Attribution Method')
                    ->options([
                        'link' => 'Link',
                        'coupon' => 'Coupon',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
