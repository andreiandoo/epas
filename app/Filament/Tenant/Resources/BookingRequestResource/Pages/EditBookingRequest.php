<?php

namespace App\Filament\Tenant\Resources\BookingRequestResource\Pages;

use App\Filament\Tenant\Resources\BookingRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBookingRequest extends EditRecord
{
    protected static string $resource = BookingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')->label('Răspunde pe email')->icon('heroicon-o-envelope')->color('gray')
                ->url(fn () => 'mailto:' . $this->getRecord()->email . '?subject=' . rawurlencode('Booking' . ($this->getRecord()->artist_name ? ' — ' . $this->getRecord()->artist_name : ''))),
            Actions\DeleteAction::make(),
        ];
    }

    // Se salvează doar starea și notițele; restul e ce a trimis solicitantul
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_intersect_key($data, array_flip(['status', 'notes']));
    }
}
