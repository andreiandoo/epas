<?php

namespace App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Models\AnafQueue;
use App\Models\Invoice;
use App\Services\Accounting\AccountingService;
use App\Services\EFactura\EFacturaService;
use App\Services\EFactura\InvoiceEFacturaTransformer;
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

            Actions\Action::make('sendEfactura')
                ->label('Trimite în eFactura')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Trimite factura în eFactura')
                ->modalDescription(fn () => "Factura #{$this->record->number} va fi trimisă către ANAF prin sistemul eFactura.")
                ->visible(function () {
                    // Show only if marketplace has eFactura active AND invoice not already sent
                    if (!static::marketplaceHasMicroservice('efactura-ro')) {
                        return false;
                    }
                    $queue = $this->record->anafQueue;
                    return !$queue || in_array($queue->status, [AnafQueue::STATUS_ERROR, AnafQueue::STATUS_REJECTED]);
                })
                ->action(function () {
                    $this->submitToEfactura($this->record);
                }),

            Actions\Action::make('efacturaStatus')
                ->label(function () {
                    $queue = $this->record->anafQueue;
                    if (!$queue) return 'Status eFactura';
                    $statusLabels = [
                        'queued' => 'eFactura: În coadă',
                        'submitted' => 'eFactura: Trimisă',
                        'accepted' => 'eFactura: Acceptată',
                        'rejected' => 'eFactura: Respinsă',
                        'error' => 'eFactura: Eroare',
                    ];
                    return $statusLabels[$queue->status] ?? 'eFactura: ' . $queue->status;
                })
                ->icon('heroicon-o-document-check')
                ->color(function () {
                    $queue = $this->record->anafQueue;
                    if (!$queue) return 'gray';
                    return match ($queue->status) {
                        'accepted' => 'success',
                        'rejected', 'error' => 'danger',
                        'submitted' => 'warning',
                        'queued' => 'info',
                        default => 'gray',
                    };
                })
                ->modalHeading('Status eFactura')
                ->modalContent(function () {
                    $queue = $this->record->anafQueue;
                    if (!$queue) return new HtmlString('<p>Factura nu a fost trimisă în eFactura.</p>');

                    $statusLabels = [
                        'queued' => 'În coadă',
                        'submitted' => 'Trimisă către ANAF',
                        'accepted' => 'Acceptată de ANAF',
                        'rejected' => 'Respinsă de ANAF',
                        'error' => 'Eroare',
                    ];

                    $html = '<div style="font-family:sans-serif;">';
                    $html .= '<p><strong>Status:</strong> ' . e($statusLabels[$queue->status] ?? $queue->status) . '</p>';
                    $html .= '<p><strong>Încercări:</strong> ' . $queue->attempts . '</p>';

                    if ($queue->submitted_at) {
                        $html .= '<p><strong>Trimisă la:</strong> ' . $queue->submitted_at->format('d.m.Y H:i') . '</p>';
                    }
                    if ($queue->accepted_at) {
                        $html .= '<p><strong>Acceptată la:</strong> ' . $queue->accepted_at->format('d.m.Y H:i') . '</p>';
                    }
                    if ($queue->error_message) {
                        $html .= '<p><strong>Eroare:</strong> ' . e($queue->error_message) . '</p>';
                    }
                    if ($remoteId = $queue->getRemoteId()) {
                        $html .= '<p><strong>ID ANAF:</strong> ' . e($remoteId) . '</p>';
                    }

                    $html .= '</div>';
                    return new HtmlString($html);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Închide')
                ->visible(fn () => $this->record->anafQueue !== null),

            Actions\Action::make('sendAccounting')
                ->label('Trimite în contabilitate')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Trimite factura în contabilitate')
                ->modalDescription(fn () => "Factura #{$this->record->number} va fi trimisă în software-ul de contabilitate.")
                ->visible(function () {
                    if (!static::marketplaceHasMicroservice('accounting-connectors')) {
                        return false;
                    }
                    $marketplace = static::getMarketplaceClient();
                    if (!$marketplace) return false;
                    return app(AccountingService::class)->hasMarketplaceConnector($marketplace->id);
                })
                ->action(function () {
                    $this->sendToAccounting($this->record);
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

    protected function submitToEfactura(Invoice $invoice): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            Notification::make()->danger()->title('Marketplace negăsit.')->send();
            return;
        }

        // Validate invoice data
        $transformer = new InvoiceEFacturaTransformer();
        $validationErrors = $transformer->validate($invoice);

        if (!empty($validationErrors)) {
            Notification::make()->danger()
                ->title('Date incomplete pentru eFactura')
                ->body(implode("\n", $validationErrors))
                ->send();
            return;
        }

        // Transform invoice data
        $invoiceData = $transformer->transform($invoice);

        try {
            $service = app(EFacturaService::class);
            $result = $service->queueMarketplaceInvoice($marketplace->id, $invoice->id, $invoiceData);

            if ($result['success']) {
                // Store queue reference in invoice meta
                $meta = $invoice->meta ?? [];
                $meta['efactura'] = [
                    'queue_id' => $result['queue_id'],
                    'status' => $result['status'],
                    'queued_at' => now()->toIso8601String(),
                ];
                $invoice->update(['meta' => $meta]);

                // Process the queue entry immediately
                $queue = AnafQueue::find($result['queue_id']);
                if ($queue && $queue->status === AnafQueue::STATUS_QUEUED) {
                    $processResult = $service->processQueueEntry($queue);
                    $queue->refresh();

                    // Update meta with latest status
                    $meta['efactura']['status'] = $queue->status;
                    $invoice->update(['meta' => $meta]);
                }

                Notification::make()->success()
                    ->title('Factură trimisă în eFactura')
                    ->body($result['message'])
                    ->send();
            } else {
                $errorMsg = $result['message'] ?? 'Eroare necunoscută';
                if (!empty($result['errors'])) {
                    $errorMsg .= ': ' . implode(', ', $result['errors']);
                }
                Notification::make()->danger()
                    ->title('Eroare la trimiterea în eFactura')
                    ->body($errorMsg)
                    ->send();
            }
        } catch (\Throwable $e) {
            \Log::error("eFactura submission failed: {$e->getMessage()}");
            Notification::make()->danger()
                ->title('Eroare la trimiterea în eFactura')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function sendToAccounting(Invoice $invoice): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            Notification::make()->danger()->title('Marketplace negăsit.')->send();
            return;
        }

        $meta = $invoice->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];

        // Check if connector uses draft mode (Oblio)
        $connector = \Illuminate\Support\Facades\DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplace->id)
            ->where('status', 'connected')
            ->first();

        $useDraft = false;
        if ($connector) {
            try {
                $auth = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($connector->auth), true);
                $useDraft = $auth['use_draft'] ?? false;
            } catch (\Exception $e) {
                // ignore
            }
        }

        // Build accounting invoice data
        $invoiceData = [
            'seller_vat' => $issuer['cui'] ?? '',
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'number' => $invoice->number,
            'is_draft' => $useDraft,
            'customer' => [
                'name' => $client['name'] ?? '',
                'vat_number' => $client['cui'] ?? '',
                'reg_number' => $client['reg_com'] ?? '',
                'email' => '',
                'address' => [
                    'street' => $client['address'] ?? '',
                    'city' => '',
                    'county' => '',
                    'country' => 'Romania',
                ],
            ],
            'lines' => array_map(function ($item) {
                return [
                    'product_name' => $item['description'] ?? '',
                    'description' => $item['description'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['price'] ?? $item['unit_price'] ?? 0),
                    'tax_rate' => 19,
                    'unit' => 'buc',
                ];
            }, $items),
        ];

        try {
            $service = app(AccountingService::class);
            $result = $service->issueMarketplaceInvoice(
                $marketplace->id,
                $invoice->number,
                $invoiceData
            );

            if ($result['success']) {
                // Store accounting reference in meta
                $meta['accounting'] = [
                    'external_ref' => $result['external_ref'],
                    'invoice_number' => $result['invoice_number'],
                    'sent_at' => now()->toIso8601String(),
                ];
                $invoice->update(['meta' => $meta]);

                Notification::make()->success()
                    ->title('Factură trimisă în contabilitate')
                    ->body("Nr. extern: {$result['invoice_number']}")
                    ->send();
            }
        } catch (\Throwable $e) {
            \Log::error("Accounting submission failed: {$e->getMessage()}");
            Notification::make()->danger()
                ->title('Eroare la trimiterea în contabilitate')
                ->body($e->getMessage())
                ->send();
        }
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
