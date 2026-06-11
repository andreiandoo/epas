<?php

namespace App\Filament\Marketplace\Resources\AttractionResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\AttractionResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttraction extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = AttractionResource::class;

    /** Public frontend URL of this attraction on the marketplace domain, or null. */
    protected function frontendUrl(): ?string
    {
        $slug = $this->record->slug ?? null;
        if (! $slug) {
            return null;
        }
        $domain = preg_replace('#^https?://#i', '', trim((string) (static::getMarketplaceClient()?->domain ?? '')));
        $domain = rtrim($domain, '/');
        if ($domain === '') {
            return null;
        }

        return 'https://' . $domain . '/atractie/' . $slug;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_frontend')
                ->label('Vizualizează')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->frontendUrl(), shouldOpenInNewTab: true)
                ->visible(fn () => filled($this->frontendUrl())),

            DeleteAction::make(),
        ];
    }
}
