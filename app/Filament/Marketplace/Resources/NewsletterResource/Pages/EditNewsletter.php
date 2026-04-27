<?php

namespace App\Filament\Marketplace\Resources\NewsletterResource\Pages;

use App\Filament\Marketplace\Resources\NewsletterResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Services\NewsletterRenderer;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditNewsletter extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Previzualizare')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalContent(function () {
                    $newsletter = $this->record;

                    if (empty($newsletter->body_sections)) {
                        return new HtmlString('<div class="p-4 text-center text-gray-500">Adaugă secțiuni în Email Content pentru a vedea previzualizarea.</div>');
                    }

                    $renderer = new NewsletterRenderer();
                    $html = $renderer->renderPreview($newsletter);

                    return new HtmlString(
                        '<div class="p-4">' .
                            '<div class="mb-3 p-3 bg-gray-100 rounded-lg">' .
                                '<p class="text-sm text-gray-600"><strong>Subject:</strong> ' . e($newsletter->subject ?? '') . '</p>' .
                                '<p class="text-sm text-gray-600"><strong>Secțiuni:</strong> ' . count($newsletter->body_sections) . '</p>' .
                            '</div>' .
                            '<div class="border rounded-lg overflow-hidden">' .
                                '<iframe srcdoc="' . e($html) . '" class="w-full border-0" style="min-height: 500px;" sandbox="allow-same-origin"></iframe>' .
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
                    try {
                        $renderer = new NewsletterRenderer();
                        $html = $renderer->renderPreview($this->record);
                        $subject = '[TEST] ' . ($this->record->subject ?: 'Newsletter');
                        \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                            static::getMarketplaceClient(),
                            $data['test_email'],
                            'Test',
                            $subject,
                            $html,
                            ['template_slug' => 'newsletter_test']
                        );
                        Notification::make()->title('Test trimis către ' . $data['test_email'])->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Trimiterea a eșuat')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }

    /**
     * Add Save Draft alongside Save / Cancel — explicitly marks the record
     * as draft regardless of the current status field.
     */
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Actions\Action::make('saveDraft')
                ->label('Save draft')
                ->color('gray')
                ->visible(fn () => !in_array($this->record->status, ['sending', 'sent']))
                ->action(function () {
                    $data = $this->form->getState();
                    $data['status'] = 'draft';
                    $this->record->update($data);
                    Notification::make()->title('Draft salvat')->success()->send();
                }),
        ];
    }
}
