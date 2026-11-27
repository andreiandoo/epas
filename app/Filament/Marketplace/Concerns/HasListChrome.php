<?php

namespace App\Filament\Marketplace\Concerns;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;

/**
 * Aspectul comun al listelor Evenimente si Locatii: titlu + numar, vederi rapide ca
 * pastile cu contor in bara tabelului, cautare / filtre / coloane in dreapta.
 * Doar aspect; interogarile tab-urilor, filtrele si actiunile raman ale paginii.
 *
 * Stilul e in view-ul filament.marketplace.lists.styles si lucreaza doar sub .ep-lst.
 */
trait HasListChrome
{
    /**
     * Cheile tab-urilor care se aduna in meniul "De completat".
     *
     * @return array<int, string>
     */
    protected function listTodoTabKeys(): array
    {
        return [];
    }

    /**
     * Livewire apeleaza metoda la fiecare cerere: pastilele sunt randate de server in
     * bara tabelului, deci raman la locul lor dupa fiecare actualizare.
     */
    public function bootHasListChrome(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => view('filament.marketplace.lists.quick-views', [
                'tabs' => $this->getCachedTabs(),
                'activeTab' => $this->activeTab ?? $this->getDefaultActiveTab(),
                'todoKeys' => $this->listTodoTabKeys(),
            ])->render(),
            scopes: static::class,
        );
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'ep-lst'];
    }

    /**
     * Tab-urile standard sunt ascunse; aceleasi tab-uri (aceleasi interogari) sunt
     * afisate ca pastile de view-ul din bara tabelului.
     */
    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()->hidden();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.marketplace.lists.styles'),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
