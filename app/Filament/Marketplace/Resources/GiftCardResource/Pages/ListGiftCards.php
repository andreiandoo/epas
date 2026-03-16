<?php

namespace App\Filament\Marketplace\Resources\GiftCardResource\Pages;

use App\Filament\Marketplace\Resources\GiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGiftCards extends ListRecords
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->extraAttributes([
                    'id' => 'create-record-btn',
                    'x-init' => "\$nextTick(() => {
                        const toolbar = document.querySelector('.fi-ta-header-toolbar');
                        if (!toolbar) return;
                        toolbar.prepend(\$el);
                        \$el.style.order = '-1';
                    })",
                ]),
        ];
    }
}
