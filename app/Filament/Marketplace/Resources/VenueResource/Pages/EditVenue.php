<?php

namespace App\Filament\Marketplace\Resources\VenueResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\VenueResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditVenue extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = VenueResource::class;

    /** Scopes the header CSS (venues/styles.blade.php) to this page. */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'ep-venue-page'];
    }

    public function getBreadcrumbs(): array
    {
        return [
            VenueResource::getUrl('index') => __('Locații'),
            (string) $this->getHeading(),
        ];
    }

    public function getHeading(): string | Htmlable
    {
        $name = $this->record?->getTranslation('name', 'ro') ?: $this->record?->getTranslation('name', 'en');

        return filled($name) ? (string) $name : parent::getHeading();
    }

    public function getSubheading(): string | Htmlable | null
    {
        $r = $this->record;
        if (! $r) {
            return null;
        }

        $parts = [];
        $place = collect([$r->city, $r->state])->filter()->implode(', ');
        if ($place !== '') {
            $parts[] = e($place);
        }
        $cats = $r->venueCategories
            ->map(fn ($c) => $c->getTranslation('name', 'ro') ?? $c->getTranslation('name', 'en'))
            ->filter()
            ->implode(', ');
        if ($cats !== '') {
            $parts[] = e($cats);
        }
        if (filled($r->capacity_total)) {
            $parts[] = e(number_format((int) $r->capacity_total, 0, ',', '.') . ' locuri');
        }
        if (filled($r->slug)) {
            $parts[] = '<span style="font-family:ui-monospace,monospace;font-size:.9em">/' . e($r->slug) . '</span>';
        }

        return new HtmlString(implode(' <span style="opacity:.5">|</span> ', $parts));
    }

    protected function getHeaderActions(): array
    {
        // No delete action for marketplace users - they cannot delete venues
        return [
            // Doar când adresa publică se poate deduce (domeniul marketplace-ului + slug)
            Action::make('view_public')
                ->label(__('Pagina publică'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => VenueResource::publicUrl($this->record), shouldOpenInNewTab: true)
                ->visible(fn () => VenueResource::publicUrl($this->record) !== null),
            Action::make('view_events')
                ->label(__('Evenimente'))
                ->color('gray')
                ->url(fn () => EventResource::getUrl('index', ['tableFilters[venue_id][value]' => $this->record->id])),
            Action::make('create_event')
                ->label(__('Eveniment nou'))
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('create', ['venue_id' => $this->record->id])),
            Action::make('make_partner')
                ->label(__('Setează ca partener'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => ! $this->record->is_partner)
                ->requiresConfirmation()
                ->modalHeading(__('Setează locația ca partener'))
                ->modalDescription(__('Locația va fi marcată ca parteneră și va apărea la filtrul „Doar partenere”.'))
                ->action(fn () => $this->makePartner()),
        ];
    }

    protected function makePartner(): void
    {
        $this->record->forceFill(['is_partner' => true])->save();

        // Keep the per-marketplace pivot flag in sync
        $marketplaceId = static::getResource()::getMarketplaceClient()?->id;
        if ($marketplaceId) {
            $this->record->marketplaceClients()->syncWithoutDetaching([
                $marketplaceId => ['is_partner' => true],
            ]);
        }

        Notification::make()
            ->title(__('Locația este acum parteneră'))
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        // Stay on the edit page after save instead of bouncing back to the list.
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
