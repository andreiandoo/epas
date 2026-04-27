<?php

namespace App\Filament\Marketplace\Resources\NewsletterResource\Pages;

use App\Filament\Marketplace\Resources\NewsletterResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Services\NewsletterRenderer;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreateNewsletter extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = NewsletterResource::class;

    /**
     * Pre-fill the form when arriving from "Newsletter" button on an event
     * edit page (?event=ID). The recipients section is auto-populated with
     * the event id so the email targets that event's ticket buyers.
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $eventId = (int) request()->query('event');
        if ($eventId <= 0) return;

        $marketplace = static::getMarketplaceClient();
        $event = Event::where('id', $eventId)
            ->where('marketplace_client_id', $marketplace?->id)
            ->first();
        if (!$event) return;

        $title = $event->getTranslation('title', 'ro')
            ?? $event->getTranslation('title', 'en')
            ?? '';

        $this->form->fill([
            'name' => 'Newsletter — ' . ($title ?: ('Eveniment #' . $event->id)),
            'subject' => $title ? ($title) : '',
            'target_event_ids' => [$event->id],
            'from_name' => $marketplace?->name,
            'from_email' => $marketplace?->contact_email,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace?->id;
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'draft';
        return $data;
    }

    /**
     * Header actions: Preview, Send test email. Save Draft is added as a
     * form action below (next to Create / Cancel).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Previzualizare')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalContent(function () {
                    $data = $this->form->getState();
                    $newsletter = $this->makeTransientNewsletter($data);
                    if (empty($newsletter->body_sections)) {
                        return new HtmlString('<div class="p-4 text-center text-gray-500">Adaugă secțiuni în Email Content pentru previzualizare.</div>');
                    }
                    $renderer = new NewsletterRenderer();
                    $html = $renderer->renderPreview($newsletter);
                    return new HtmlString(
                        '<div class="p-4">' .
                            '<div class="mb-3 p-3 bg-gray-100 rounded-lg">' .
                                '<p class="text-sm text-gray-600"><strong>Subject:</strong> ' . e($newsletter->subject ?? '') . '</p>' .
                            '</div>' .
                            '<div class="border rounded-lg overflow-hidden">' .
                                '<iframe srcdoc="' . e($html) . '" class="w-full border-0" style="min-height:500px;" sandbox="allow-same-origin"></iframe>' .
                            '</div>' .
                        '</div>'
                    );
                })
                ->modalHeading('Newsletter Preview')
                ->modalSubmitAction(false)
                ->modalWidth('5xl'),

            Actions\Action::make('sendTest')
                ->label('Trimite test')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('test_email')
                        ->label('Email de test')
                        ->email()
                        ->required()
                        ->placeholder('test@example.com')
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data) {
                    $formData = $this->form->getState();
                    $newsletter = $this->makeTransientNewsletter($formData);
                    $this->sendTestEmail($newsletter, $data['test_email']);
                }),
        ];
    }

    /**
     * Add "Save draft" alongside Create / Create & create another / Cancel.
     * Saves the record with status=draft and redirects to the edit page so
     * the organizer can keep iterating.
     */
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Actions\Action::make('saveDraft')
                ->label('Save draft')
                ->color('gray')
                ->action(function () {
                    $data = $this->form->getState();
                    $data = $this->mutateFormDataBeforeCreate($data);
                    $data['status'] = 'draft';
                    $record = static::getModel()::create($data);
                    Notification::make()->title('Draft salvat')->success()->send();
                    $this->redirect(NewsletterResource::getUrl('edit', ['record' => $record]));
                }),
        ];
    }

    /**
     * Build an unsaved MarketplaceNewsletter from the current form state so
     * preview/test-send actions can run before the user has hit Create.
     */
    protected function makeTransientNewsletter(array $data): \App\Models\MarketplaceNewsletter
    {
        $marketplace = static::getMarketplaceClient();
        $newsletter = new \App\Models\MarketplaceNewsletter();
        $newsletter->fill($data);
        $newsletter->marketplace_client_id = $marketplace?->id;
        $newsletter->setRelation('marketplaceClient', $marketplace);
        return $newsletter;
    }

    /**
     * Send a single rendered newsletter to one address. Reuses NewsletterRenderer
     * for HTML so the preview and the test email match.
     */
    protected function sendTestEmail(\App\Models\MarketplaceNewsletter $newsletter, string $email): void
    {
        try {
            $renderer = new \App\Services\NewsletterRenderer();
            $html = $renderer->renderPreview($newsletter);
            $subject = '[TEST] ' . ($newsletter->subject ?: 'Newsletter');

            \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                static::getMarketplaceClient(),
                $email,
                'Test',
                $subject,
                $html,
                ['template_slug' => 'newsletter_test']
            );
            Notification::make()->title("Test trimis către {$email}")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Trimiterea testului a eșuat')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
