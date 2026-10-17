<?php

namespace App\Filament\Tenant\Resources\NewsletterResource\Pages;

use App\Filament\Tenant\Resources\NewsletterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsletters extends ListRecords
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Campanie nouă'),
        ];
    }
}
