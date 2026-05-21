<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ResourceRentalResource\Pages;
use App\Models\Leisure\ResourceRental;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class ResourceRentalResource extends Resource
{
    protected static ?string $model = ResourceRental::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 31;
    protected static ?string $navigationLabel = 'Rentals (istoric)';
    protected static ?string $modelLabel = 'Rental';
    protected static ?string $pluralModelLabel = 'Rentals';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value
            : (string) $tenant?->tenant_type;
        return $type === 'leisure';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant?->id;
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenantId)
            ->with(['physicalResource:id,name,resource_type', 'ticket:id,code']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Început')
                    ->dateTime('d.m H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('physicalResource.name')
                    ->label('Resursă')
                    ->searchable(),

                Tables\Columns\TextColumn::make('physicalResource.resource_type')
                    ->label('Tip')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ticket.code')
                    ->label('Bilet')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('planned_end_at')
                    ->label('Plan. sfârșit')
                    ->dateTime('d.m H:i')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Sfârșit real')
                    ->dateTime('d.m H:i')
                    ->placeholder('în desfășurare')
                    ->color(fn ($state, $record) => $state === null && $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('current_overtime_minutes')
                    ->label('Depășire (min)')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('overtime_surcharge_cents')
                    ->label('Surcharge')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('surcharge_paid')
                    ->label('Surcharge plătit')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('Doar active')
                    ->query(fn (Builder $q) => $q->whereNull('ended_at')),
                Tables\Filters\Filter::make('overdue')
                    ->label('Active depășite')
                    ->query(fn (Builder $q) => $q->whereNull('ended_at')->where('planned_end_at', '<', now())),
            ])
            ->recordActions([
                Action::make('forceEnd')
                    ->label('Forțează închidere')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->is_active)
                    ->action(function ($record) {
                        app(\App\Services\Leisure\RentalService::class)->end($record, auth()->id());
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false; // rentals are created via operator/POS flow, not admin
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResourceRentals::route('/'),
            'view' => Pages\ViewResourceRental::route('/{record}'),
        ];
    }
}
