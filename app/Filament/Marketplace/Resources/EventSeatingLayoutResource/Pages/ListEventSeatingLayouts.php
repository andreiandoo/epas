<?php

namespace App\Filament\Marketplace\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\EventSeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListEventSeatingLayouts extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = EventSeatingLayoutResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Hărți evenimente'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Hartă eveniment nouă'))
                ->icon('heroicon-m-plus'),
        ];
    }
}
