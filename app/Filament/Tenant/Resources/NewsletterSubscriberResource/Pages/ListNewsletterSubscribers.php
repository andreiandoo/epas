<?php

namespace App\Filament\Tenant\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\Tenant\Resources\NewsletterSubscriberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label('Adaugă abonat')->icon('heroicon-o-plus')
                ->schema(NewsletterSubscriberResource::fields())
                ->action(fn (array $data) => NewsletterSubscriberResource::addManually($data)),
        ];
    }
}
