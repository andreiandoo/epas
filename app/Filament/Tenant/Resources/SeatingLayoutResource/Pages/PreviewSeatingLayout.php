<?php

namespace App\Filament\Tenant\Resources\SeatingLayoutResource\Pages;

use App\Filament\Tenant\Resources\SeatingLayoutResource;
use App\Models\Seating\SeatingLayout;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class PreviewSeatingLayout extends Page
{
    protected static string $resource = SeatingLayoutResource::class;

    protected string $view = 'filament.resources.seating-layout-resource.pages.preview';

    protected static ?string $title = 'Previzualizare';

    public int $layoutId;

    public function mount($record): void
    {
        $this->layoutId = (int) $record;
    }

    public function getSeatingLayoutProperty(): SeatingLayout
    {
        return SeatingLayout::withoutGlobalScopes()->findOrFail($this->layoutId);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('designer')
                ->label('Deschide Designer')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => SeatingLayoutResource::getUrl('designer', ['record' => $this->layoutId])),
            Actions\Action::make('edit')
                ->label('Editează')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => SeatingLayoutResource::getUrl('edit', ['record' => $this->layoutId])),
        ];
    }
}
