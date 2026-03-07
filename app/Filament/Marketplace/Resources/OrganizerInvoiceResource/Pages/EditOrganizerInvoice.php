<?php

namespace App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EditOrganizerInvoice extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = OrganizerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Previzualizare')
                ->icon('heroicon-o-eye')
                ->modalHeading(fn () => "Factură #{$this->record->number}")
                ->modalContent(fn () => new HtmlString(
                    OrganizerInvoiceResource::renderInvoiceHtml($this->record)
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Închide'),

            Actions\Action::make('email')
                ->label('Trimite Email')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->modalHeading('Trimite factura pe email')
                ->modalDescription(function () {
                    $organizer = $this->record->organizer;
                    $email = $organizer?->billing_email ?? $organizer?->email ?? 'N/A';
                    return "Factura #{$this->record->number} va fi trimisă la: {$email}";
                })
                ->action(function () {
                    $this->sendInvoiceEmail($this->record);
                }),

            Actions\Action::make('markPaid')
                ->label('Marchează Achitată')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status !== 'paid')
                ->action(function () {
                    $this->record->markAsPaid('manual');
                    Notification::make()->success()->title('Factură marcată ca achitată.')->send();
                    $this->fillForm();
                }),
        ];
    }

    protected function sendInvoiceEmail(Invoice $invoice): void
    {
        $organizer = $invoice->organizer;
        if (!$organizer) {
            Notification::make()->danger()->title('Organizator negăsit.')->send();
            return;
        }

        $email = $organizer->billing_email ?? $organizer->email;
        if (!$email) {
            Notification::make()->danger()->title('Organizatorul nu are adresă de email.')->send();
            return;
        }

        $marketplace = static::getMarketplaceClient();
        $transport = $marketplace?->getSmtpTransport();

        if (!$transport) {
            Notification::make()->danger()->title('SMTP nu este configurat.')->send();
            return;
        }

        try {
            $html = OrganizerInvoiceResource::renderInvoiceHtml($invoice);
            $wrappedHtml = $this->wrapInEmailTemplate($html, $marketplace);

            $emailMessage = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $marketplace->getEmailFromAddress(),
                    $marketplace->getEmailFromName()
                ))
                ->to($email)
                ->subject("Factură #{$invoice->number}")
                ->html($wrappedHtml);

            $transport->send($emailMessage);

            Notification::make()->success()
                ->title('Email trimis')
                ->body("Factura a fost trimisă la {$email}")
                ->send();
        } catch (\Throwable $e) {
            \Log::error("Failed to send invoice email: {$e->getMessage()}");
            Notification::make()->danger()
                ->title('Eroare la trimitere')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function wrapInEmailTemplate(string $content, $marketplace): string
    {
        $name = e($marketplace->name ?? 'Invoice');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ro">
        <head><meta charset="UTF-8"><title>{$name} - Factură</title></head>
        <body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;">
            <div style="max-width:700px;margin:0 auto;background:#fff;padding:32px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                {$content}
            </div>
        </body>
        </html>
        HTML;
    }
}
