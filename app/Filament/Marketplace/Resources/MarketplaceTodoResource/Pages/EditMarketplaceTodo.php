<?php

namespace App\Filament\Marketplace\Resources\MarketplaceTodoResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceTodo extends EditRecord
{
    protected static string $resource = MarketplaceTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
