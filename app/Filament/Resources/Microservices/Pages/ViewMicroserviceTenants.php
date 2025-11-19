<?php

namespace App\Filament\Resources\Microservices\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use App\Models\Microservice;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ViewMicroserviceTenants extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MicroserviceResource::class;

    protected string $view = 'filament.resources.microservices.pages.view-microservice-tenants';

    public ?Microservice $record = null;

    public function mount($record): void
    {
        $this->record = Microservice::findOrFail($record);
    }

    public function getTitle(): string
    {
        return "Tenants using: {$this->record->name}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->record->tenants()->getQuery()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tenant Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('public_name')
                    ->label('Public Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.activated_at')
                    ->label('Activated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pivot.expires_at')
                    ->label('Expires At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Tenant Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('pivot.is_active')
                    ->label('Microservice Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Tenant Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->defaultSort('pivot.activated_at', 'desc');
    }
}
