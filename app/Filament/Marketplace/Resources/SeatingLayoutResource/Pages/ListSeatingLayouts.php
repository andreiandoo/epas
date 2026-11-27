<?php

namespace App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\SeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListSeatingLayouts extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = SeatingLayoutResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Hărți'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Hartă nouă'))
                ->icon('heroicon-m-plus'),
        ];
    }
}
