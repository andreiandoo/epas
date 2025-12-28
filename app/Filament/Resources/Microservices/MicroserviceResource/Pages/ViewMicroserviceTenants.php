<?php

namespace App\Filament\Resources\Microservices\MicroserviceResource\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use App\Models\Microservice;
use App\Models\Tenant;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ViewMicroserviceTenants extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MicroserviceResource::class;

    protected string $view = 'filament.resources.microservices.pages.view-microservice-tenants';

    public Microservice $record;

    public function getTitle(): string|Htmlable
    {
        return 'Tenants using ' . $this->record->getTranslation('name', 'en');
    }

    public function getBreadcrumb(): string
    {
        return 'Tenants';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->whereHas('microservices', function (Builder $query) {
                        $query->where('microservice_id', $this->record->id);
                    })
                    ->with(['owner', 'microservices' => function ($query) {
                        $query->where('microservice_id', $this->record->id);
                    }])
            )
            ->columns([
                Tables\Columns\TextColumn::make('public_name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->slug),
                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('pivot_is_active')
                    ->label('Status')
                    ->state(function ($record) {
                        return $record->microservices->first()?->pivot->is_active ?? false;
                    })
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ]),
                Tables\Columns\TextColumn::make('pivot_activated_at')
                    ->label('Activated')
                    ->state(function ($record) {
                        return $record->microservices->first()?->pivot->activated_at;
                    })
                    ->dateTime('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot_expires_at')
                    ->label('Expires')
                    ->state(function ($record) {
                        return $record->microservices->first()?->pivot->expires_at;
                    })
                    ->dateTime('M d, Y')
                    ->placeholder('Never'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
