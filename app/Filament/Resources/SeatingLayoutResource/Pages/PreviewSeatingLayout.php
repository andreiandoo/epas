<?php

namespace App\Filament\Resources\SeatingLayoutResource\Pages;

use App\Filament\Resources\SeatingLayoutResource;
use App\Models\Seating\SeatingLayout;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class PreviewSeatingLayout extends Page
{
    protected static string $resource = SeatingLayoutResource::class;

    protected string $view = 'filament.resources.seating-layout-resource.pages.preview';

    protected static ?string $title = 'Preview';

    public SeatingLayout $seatingLayout;

    public function mount(SeatingLayout $record): void
    {
        $this->seatingLayout = $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('designer')
                ->label('Open Designer')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => SeatingLayoutResource::getUrl('designer', ['record' => $this->seatingLayout])),
            Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => SeatingLayoutResource::getUrl('edit', ['record' => $this->seatingLayout])),
        ];
    }
}
