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
            Actions\Action::make('viewPublic')
                ->label('Vezi pe site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url(fn () => $this->buildPublicUrl())
                ->openUrlInNewTab()
                ->visible(fn () => $this->record !== null && !empty($this->record->slug) && $this->buildPublicUrl() !== null),
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

    /**
     * Build the public storefront URL for this tour: https://{marketplace.domain}/turnee/{slug}.
     * Returns null when we can't resolve a domain so the button hides itself.
     */
    protected function buildPublicUrl(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace || empty($this->record?->slug)) {
            return null;
        }
        $domain = $marketplace->domain ?? $marketplace->primary_domain ?? null;
        if (!$domain) {
            return null;
        }
        $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));
        $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
        return "{$protocol}://{$domain}/turnee/{$this->record->slug}";
    }

    protected function getRedirectUrl(): string
    {
        if ($this->shouldClose) {
            return $this->getResource()::getUrl('index');
        }

        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
