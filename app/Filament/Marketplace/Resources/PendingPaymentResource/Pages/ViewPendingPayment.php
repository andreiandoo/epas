<?php

namespace App\Filament\Marketplace\Resources\PendingPaymentResource\Pages;

use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\PendingPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPendingPayment extends ViewRecord
{
    protected static string $resource = PendingPaymentResource::class;

    public function getTitle(): string
    {
        return 'Plată decont ' . ($this->record->reference ?? '#' . $this->record->id);
    }

    public function getHeading(): string
    {
        return 'Plată decont ' . ($this->record->reference ?? '#' . $this->record->id);
    }

    protected function getHeaderActions(): array
    {
        // Acțiunile Achitat / Respins trăiesc în infolist sidebar — vezi
        // PendingPaymentResource::infolist(). Header lăsat doar cu link
        // înapoi spre decontul standard pentru fluxul complet (recalc
        // snapshot, edit bilete, etc).
        return [
            Actions\Action::make('view_full_decont')
                ->label('Vezi decontul complet')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => PayoutResource::getUrl('view', ['record' => $this->record->id]))
                ->openUrlInNewTab(),
        ];
    }
}
