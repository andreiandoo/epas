<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use App\Models\Artist;
use Filament\Resources\Pages\Page;

class ArtistStats extends Page
{
    protected static string $resource = ArtistResource::class;

    protected string $view = 'filament.artists.pages.artist-stats';

    public Artist $record;

    public function mount(int|string $record): void
    {
        $this->record = Artist::query()->findOrFail($record);
        $this->authorize('view', $this->record);
    }

    public function getHeading(): string
    {
        return $this->record->name . ' — Stats';
    }
}
