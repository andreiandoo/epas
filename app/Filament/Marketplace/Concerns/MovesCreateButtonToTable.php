<?php

namespace App\Filament\Marketplace\Concerns;

use Filament\Actions;
use Illuminate\Support\HtmlString;

/**
 * Moves the header "Create" button into the table's toolbar (left side).
 *
 * Usage: add `use MovesCreateButtonToTable;` in a ListRecords page.
 * The trait overrides getHeaderActions() to inject an Alpine x-init
 * that physically moves the button into .fi-ta-header-toolbar.
 */
trait MovesCreateButtonToTable
{
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->extraAttributes([
                    'x-data' => '{}',
                    'x-init' => "\$nextTick(() => {
                        const toolbar = document.querySelector('.fi-ta-header-toolbar');
                        if (!toolbar || !(\$el instanceof HTMLElement)) return;
                        toolbar.prepend(\$el);
                        \$el.style.order = '-1';
                    })",
                ]),
        ];
    }
}
