<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Colors\Color;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('full_name')->label('Nume complet')
                    ->state(fn($r) => $r->full_name ?? trim(($r->first_name ?? '').' '.($r->last_name ?? '')))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('orders_count')->label('Orders')->sortable()
                    ->state(fn($r) => $r->orders()->count()),
                TextColumn::make('income')->label('Income')->sortable()
                    ->state(fn($r) => number_format(($r->orders()->sum('total_cents') ?? 0) / 100, 2).' RON'),
                TextColumn::make('tickets')->label('Tickets')->sortable()
                    ->state(function ($r) {
                        return \App\Models\Ticket::query()
                            ->whereHas('order', fn($q) => $q->where('customer_id', $r->id))
                            ->count();
                    }),
                TextColumn::make('tenants_list')->label('Tenants')
                    ->state(function ($r) {
                        $names = $r->tenants()->pluck('name','tenants.id')->toArray();
                        $primaryId = $r->primary_tenant_id;
                        if ($primaryId && isset($names[$primaryId])) {
                            $primary = $names[$primaryId];
                            unset($names[$primaryId]);
                            return '★ '.$primary.(count($names) ? ' · '.implode(' · ', array_values($names)) : '');
                        }
                        return implode(' · ', array_values($names));
                    })
                    ->limit(80)
                    ->tooltip(fn($state) => $state),
            ])
            ->recordActions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('stats')
                    ->label('Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color(Color::Blue)
                    ->url(fn($record) => \App\Filament\Resources\Customers\CustomerResource::getUrl('stats', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
