<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Filament\Resources\Affiliates\AffiliateResource;
use Filament\Resources\Pages\EditRecord;

class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('viewStats')
                ->label('View Statistics')
                ->icon('heroicon-o-chart-bar')
                ->url(fn () => static::getResource()::getUrl('stats', ['record' => $this->record])),
            \Filament\Actions\DeleteAction::make()
                ->label('Delete Affiliate')
                ->icon('heroicon-o-trash')
                ->color('danger'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
