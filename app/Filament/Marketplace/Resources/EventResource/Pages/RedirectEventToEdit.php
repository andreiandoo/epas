<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

/**
 * Bare route at /marketplace/events/{record} that immediately redirects to the
 * edit page. Filament only registers /{record}/edit (and other suffixed
 * routes), so hitting the record URL without a suffix used to 404.
 */
class RedirectEventToEdit extends Page
{
    use InteractsWithRecord;
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;

    // Never actually rendered — mount() redirects first. Minimal blade with no
    // method calls, in case the redirect is ever late.
    protected string $view = 'filament.marketplace.resources.event-resource.pages.redirect-to-edit';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Enforce the same marketplace scoping as the other event pages.
        $marketplace = static::getMarketplaceClient();
        if ($this->record->marketplace_client_id !== $marketplace?->id) {
            abort(403);
        }

        $this->redirect(EventResource::getUrl('edit', ['record' => $this->record]));
    }
}
