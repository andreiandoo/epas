<?php

namespace App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerLeadResource;
use App\Models\Marketplace\OrganizerLead;
use App\Models\Marketplace\OrganizerLeadEvent;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * Lead detail page. Shows the standard Filament view-record layout
 * for the form fields PLUS a custom infolist-style activity timeline
 * via getFooter().
 *
 * Quick actions on the header: change status (one click per major
 * transition), add note, log call, log email, schedule demo. Each
 * action writes the matching timeline event so the audit log stays
 * complete regardless of which path the operator takes.
 */
class ViewOrganizerLead extends ViewRecord
{
    protected static string $resource = OrganizerLeadResource::class;

    protected function getHeaderActions(): array
    {
        /** @var OrganizerLead $lead */
        $lead = $this->record;

        $statuses = collect(OrganizerLead::STATUSES)
            ->reject(fn ($_, $key) => $key === $lead->status)
            ->all();

        return [
            EditAction::make(),

            Action::make('change_status')
                ->label('Schimbă status')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('Status nou')
                        ->options($statuses)
                        ->required(),
                    Forms\Components\Textarea::make('summary')
                        ->label('Notă (opțional)')
                        ->rows(2),
                ])
                ->action(function (array $data) use ($lead) {
                    $lead->transitionTo($data['status'], $data['summary'] ?? null, auth()->id());
                    Notification::make()->success()->title('Status actualizat')->send();
                    $this->refreshFormData(['status', 'contacted_at', 'accepted_at', 'rejected_at', 'ghosted_at']);
                }),

            Action::make('add_note')
                ->label('Adaugă notă')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->form([
                    Forms\Components\Textarea::make('note')->label('Notă')->required()->rows(3),
                ])
                ->action(function (array $data) use ($lead) {
                    OrganizerLeadEvent::create([
                        'lead_id'               => $lead->id,
                        'marketplace_client_id' => $lead->marketplace_client_id,
                        'event_type'            => OrganizerLeadEvent::TYPE_NOTE,
                        'summary'               => $data['note'],
                        'performed_by_user_id'  => auth()->id(),
                    ]);
                    Notification::make()->success()->title('Notă adăugată')->send();
                }),

            Action::make('log_email')
                ->label('Log email trimis')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->form([
                    Forms\Components\TextInput::make('subject')->label('Subiect')->required()->maxLength(200),
                    Forms\Components\Textarea::make('body')->label('Rezumat conținut')->rows(3),
                ])
                ->action(function (array $data) use ($lead) {
                    OrganizerLeadEvent::create([
                        'lead_id'               => $lead->id,
                        'marketplace_client_id' => $lead->marketplace_client_id,
                        'event_type'            => OrganizerLeadEvent::TYPE_EMAIL_SENT,
                        'summary'               => $data['subject'],
                        'payload'               => ['body' => $data['body'] ?? null],
                        'performed_by_user_id'  => auth()->id(),
                    ]);
                    Notification::make()->success()->title('Email loggat')->send();
                }),

            Action::make('log_call')
                ->label('Log apel')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->form([
                    Forms\Components\Textarea::make('note')->label('Rezumat apel')->required()->rows(3),
                ])
                ->action(function (array $data) use ($lead) {
                    OrganizerLeadEvent::create([
                        'lead_id'               => $lead->id,
                        'marketplace_client_id' => $lead->marketplace_client_id,
                        'event_type'            => OrganizerLeadEvent::TYPE_CALL,
                        'summary'               => $data['note'],
                        'performed_by_user_id'  => auth()->id(),
                    ]);
                    Notification::make()->success()->title('Apel loggat')->send();
                }),

            Action::make('schedule_demo')
                ->label('Programează demo')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->form([
                    Forms\Components\DateTimePicker::make('demo_at')->label('Data + ora demo')->required(),
                    Forms\Components\Textarea::make('note')->label('Notă (opțional)')->rows(2),
                ])
                ->action(function (array $data) use ($lead) {
                    $lead->update([
                        'status'         => OrganizerLead::STATUS_DEMO_SCHEDULED,
                        'next_action_at' => $data['demo_at'],
                    ]);
                    OrganizerLeadEvent::create([
                        'lead_id'               => $lead->id,
                        'marketplace_client_id' => $lead->marketplace_client_id,
                        'event_type'            => OrganizerLeadEvent::TYPE_DEMO_SCHEDULED,
                        'summary'               => 'Demo programat ' . \Carbon\Carbon::parse($data['demo_at'])->format('d M Y H:i'),
                        'payload'               => ['demo_at' => $data['demo_at'], 'note' => $data['note'] ?? null],
                        'performed_by_user_id'  => auth()->id(),
                    ]);
                    Notification::make()->success()->title('Demo programat')->send();
                    $this->refreshFormData(['status', 'next_action_at']);
                }),
        ];
    }

    /**
     * Render a vertical timeline below the form. Each event row has the
     * type label, timestamp, who performed it, and the summary/payload
     * detail. Kept inline for now — if it grows we can split into a
     * Livewire component.
     */
    public function getFooter(): ?View
    {
        /** @var OrganizerLead $lead */
        $lead = $this->record;
        $events = $lead->events()->limit(200)->get();

        return view('filament.marketplace.resources.organizer-leads.timeline', [
            'lead'   => $lead,
            'events' => $events,
        ]);
    }
}
