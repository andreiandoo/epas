<?php

namespace App\Filament\Resources\SystemErrors\Pages;

use App\Filament\Resources\SystemErrors\SystemErrorResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSystemErrors extends ListRecords
{
    protected static string $resource = SystemErrorResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            // Severity filters
            'critical' => Tab::make('Critical+')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('level', '>=', 500)),
            'error' => Tab::make('Error')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('level', '=', 400)),
            'warning' => Tab::make('Warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('level', '=', 300)),

            // Time window
            'last_7d' => Tab::make('Last 7 days')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7))),

            // Domain categories
            'auth' => Tab::make('Auth')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'auth')),
            'payment' => Tab::make('Payment')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'payment')),
            'email' => Tab::make('Email')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'email')),
            'database' => Tab::make('Database')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'database')),
            'external_api' => Tab::make('External API')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'external_api')),
            'queue' => Tab::make('Queue')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'queue')),
            'seating' => Tab::make('Seating')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'seating')),
            'security' => Tab::make('Security')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'security')),
            'other' => Tab::make('Other')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('category', [
                    'auth', 'payment', 'email', 'database', 'external_api', 'queue', 'seating', 'security',
                ])),
        ];
    }
}
