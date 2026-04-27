<?php

namespace App\Filament\Marketplace\Resources\TourResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TourResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = TourResource::class;

    protected bool $shouldClose = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('saveAndClose')
                ->label('Salvează și închide')
                ->action(function () {
                    $this->shouldClose = true;
                    $this->save();
                })
                ->color('gray')
                ->icon('heroicon-o-check'),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record !== null && $this->record->events()->count() === 0)
                ->modalDescription('Turneul nu are evenimente atașate. Sigur vrei să-l ștergi?'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->shouldClose) {
            return $this->getResource()::getUrl('index');
        }

        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
