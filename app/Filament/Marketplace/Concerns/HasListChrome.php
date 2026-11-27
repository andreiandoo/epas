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

    /** Label of the dropdown that groups listTodoTabKeys() (e.g. "De completat", "Tip persoană"). */
    protected function listTodoTabsLabel(): string
    {
        return __('De completat');
    }

    /**
     * Labelled "Filtre" / "Coloane" buttons like on the events list. Pages whose
     * resource table already sets its own trigger labels (events, venues) return false.
     */
    protected function listLabelsTriggers(): bool
    {
        return true;
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        $table = parent::table($table);
        if (! $this->listLabelsTriggers()) {
            return $table;
        }

        return $table
            ->filtersTriggerAction(fn (\Filament\Actions\Action $action) => $action->label(__('Filtre'))->icon('heroicon-o-funnel')->button())
            ->columnManagerTriggerAction(fn (\Filament\Actions\Action $action) => $action->label(__('Coloane'))->icon('heroicon-o-view-columns')->button());
    }

    /** Page title + record count, the same on every redesigned list. */
    protected function listHeading(string $label): \Illuminate\Support\HtmlString
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count(), 0, ',', '.');

        return new \Illuminate\Support\HtmlString(e($label) . ' <span class="ep-lst-count">' . $count . '</span>');
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
                'todoLabel' => $this->listTodoTabsLabel(),
            ])->render(),
            scopes: static::class,
        );

        // Active-filter row with the total number of results in front of it.
        FilamentView::registerRenderHook(
            TablesRenderHook::FILTER_INDICATORS,
            fn (array $data): string => view('filament.marketplace.lists.filter-indicators', [
                'filterIndicators' => $data['filterIndicators'] ?? [],
                'total' => $this->getAllTableRecordsCount(),
                'removeAll' => $this->getTable()->getFiltersRemoveAllAction(),
            ])->render(),
            scopes: static::class,
        );
    }

    /**
     * New session keys: Filament remembers each user's column visibility and order
     * per table, so without this the old (pre-redesign) layout kept showing for
     * anyone who had opened the page before. Users can still toggle columns.
     */
    public function getTableColumnsSessionKey(): string
    {
        return 'tables.' . md5(static::class) . '_columns_v2';
    }

    public function getHasReorderedTableColumnsSessionKey(): string
    {
        return 'tables.' . md5(static::class) . '_has_reordered_columns_v2';
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
