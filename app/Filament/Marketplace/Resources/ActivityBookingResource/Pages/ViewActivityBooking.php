<?php

namespace App\Filament\Marketplace\Resources\ActivityBookingResource\Pages;

use App\Filament\Marketplace\Resources\ActivityBookingResource;
use App\Models\ActivityBooking;
use App\Models\Ticket;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewActivityBooking extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ActivityBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkIn')
                ->label(__('Validează la intrare'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ActivityBooking::STATUS_PAID,
                    ActivityBooking::STATUS_CONFIRMED,
                ], true))
                ->requiresConfirmation()
                ->modalHeading(__('Validează rezervarea'))
                ->modalDescription(fn () => sprintf(
                    'Marchezi rezervarea %s ca validată. Toate biletele asociate (%d) vor fi marcate ca verificate la intrare.',
                    $this->record->confirmation_code,
                    Ticket::where('activity_booking_id', $this->record->id)->count(),
                ))
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->update([
                            'status'        => ActivityBooking::STATUS_CHECKED_IN,
                            'checked_in_at' => now(),
                        ]);
                        Ticket::where('activity_booking_id', $this->record->id)
                            ->whereIn('status', ['valid', 'pending'])
                            ->update([
                                'status'        => 'checked_in',
                                'checked_in_at' => now(),
                                'checked_in_by' => auth()->user()?->name ?? 'Admin marketplace',
                            ]);
                    });
                    $this->refreshFormData(['status', 'checked_in_at']);
                    Notification::make()
                        ->title(__('Rezervarea a fost validată'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('undoCheckIn')
                ->label(__('Anulează validarea'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === ActivityBooking::STATUS_CHECKED_IN)
                ->requiresConfirmation()
                ->modalHeading(__('Anulează validarea'))
                ->modalDescription(__('Rezervarea va reveni la statusul de plătită. Biletele asociate redevin valide.'))
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->update([
                            'status'        => ActivityBooking::STATUS_PAID,
                            'checked_in_at' => null,
                        ]);
                        Ticket::where('activity_booking_id', $this->record->id)
                            ->where('status', 'checked_in')
                            ->update([
                                'status'        => 'valid',
                                'checked_in_at' => null,
                                'checked_in_by' => null,
                            ]);
                    });
                    $this->refreshFormData(['status', 'checked_in_at']);
                    Notification::make()
                        ->title(__('Validarea a fost anulată'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('markNoShow')
                ->label(__('No-show'))
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ActivityBooking::STATUS_PAID,
                    ActivityBooking::STATUS_CONFIRMED,
                ], true))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => ActivityBooking::STATUS_NO_SHOW]);
                    $this->refreshFormData(['status']);
                    Notification::make()->title(__('Marcat ca no-show'))->success()->send();
                }),

            Actions\Action::make('cancelBooking')
                ->label(__('Anulează rezervarea'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! in_array($this->record->status, [
                    ActivityBooking::STATUS_CANCELLED,
                    ActivityBooking::STATUS_CHECKED_IN,
                ], true))
                ->requiresConfirmation()
                ->modalDescription(__('Locurile vor fi eliberate. Nu se efectuează rambursare automată.'))
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->update([
                            'status'     => ActivityBooking::STATUS_CANCELLED,
                            'held_until' => null,
                        ]);
                        Ticket::where('activity_booking_id', $this->record->id)
                            ->whereIn('status', ['valid', 'pending'])
                            ->update(['status' => 'cancelled']);
                    });
                    $this->refreshFormData(['status']);
                    Notification::make()->title(__('Rezervarea a fost anulată'))->success()->send();
                }),
        ];
    }
}
