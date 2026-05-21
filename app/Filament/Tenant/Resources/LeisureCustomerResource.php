<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\LeisureCustomerResource\Pages;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeisureCustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 50;
    protected static ?string $navigationLabel = 'CRM Clienți';
    protected static ?string $modelLabel = 'Client';
    protected static ?string $pluralModelLabel = 'Clienți';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value : (string) $tenant?->tenant_type;
        return $type === 'leisure' && ($tenant?->features['leisure']['crm']['enabled'] ?? true);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant?->id;
        return parent::getEloquentQuery()
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                ->orWhere('primary_tenant_id', $tenantId)
                ->orWhereHas('tenants', fn ($qq) => $qq->where('tenants.id', $tenantId)))
            ->withCount(['orders'])
            ->withSum('orders as total_spent_cents', 'total_cents');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Prenume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Comenzi')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_spent_cents')
                    ->label('Total cheltuit')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((int) ($state ?? 0) / 100, 2) . ' RON')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Client din')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('high_value')
                    ->label('High value (>500 RON)')
                    ->query(fn (Builder $q) => $q->having('total_spent_cents', '>', 50000)),
                Tables\Filters\Filter::make('returning')
                    ->label('Returning (>= 2 comenzi)')
                    ->query(fn (Builder $q) => $q->having('orders_count', '>=', 2)),
                Tables\Filters\Filter::make('no_orders')
                    ->label('Fără comenzi')
                    ->query(fn (Builder $q) => $q->having('orders_count', '=', 0)),
                Tables\Filters\Filter::make('created_after')
                    ->form([\Filament\Forms\Components\DatePicker::make('after')->label('Înregistrat după')])
                    ->query(fn (Builder $q, array $data) => $data['after'] ? $q->whereDate('created_at', '>=', $data['after']) : $q),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => static::exportCsv()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function exportCsv()
    {
        $tenantId = auth()->user()?->tenant?->id;
        $customers = Customer::query()
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                ->orWhere('primary_tenant_id', $tenantId)
                ->orWhereHas('tenants', fn ($qq) => $qq->where('tenants.id', $tenantId)))
            ->withCount(['orders'])
            ->withSum('orders as total_spent_cents', 'total_cents')
            ->get();

        $filename = 'clienti-' . now()->format('Y-m-d-His') . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        return response()->stream(function () use ($customers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Prenume', 'Nume', 'Email', 'Telefon', 'Oraș', 'Comenzi', 'Total cheltuit (RON)', 'Client din']);
            foreach ($customers as $c) {
                fputcsv($out, [
                    $c->first_name,
                    $c->last_name,
                    $c->email,
                    $c->phone,
                    $c->city,
                    $c->orders_count,
                    number_format(($c->total_spent_cents ?? 0) / 100, 2),
                    optional($c->created_at)->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeisureCustomers::route('/'),
        ];
    }
}
