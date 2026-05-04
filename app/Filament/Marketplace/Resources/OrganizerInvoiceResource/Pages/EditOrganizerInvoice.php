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

            Actions\Action::make('downloadPdf')
                ->label('Descarcă PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(function () {
                    $meta = $this->record->meta ?? [];
                    return !empty($meta['accounting']['pdf_url']) || !empty($meta['accounting_proforma']['pdf_url']);
                })
                ->url(function () {
                    $meta = $this->record->meta ?? [];
                    return $meta['accounting']['pdf_url'] ?? $meta['accounting_proforma']['pdf_url'] ?? null;
                }, shouldOpenInNewTab: true),

            Actions\DeleteAction::make()
                ->label('Șterge factura')
                ->requiresConfirmation()
                ->modalHeading('Șterge factura')
                ->modalDescription(fn () => "Factura #{$this->record->number} va fi ștearsă. Această acțiune nu poate fi anulată.")
                ->successRedirectUrl(fn () => OrganizerInvoiceResource::getUrl('index')),

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

            Actions\Action::make('sendProforma')
                ->label('Trimite Proformă')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Trimite factură proformă')
                ->modalDescription(fn () => "Factura #{$this->record->number} va fi trimisă ca PROFORMĂ în software-ul de contabilitate.")
                ->visible(function () {
                    if (!static::marketplaceHasMicroservice('accounting-connectors')) {
                        return false;
                    }
                    $marketplace = static::getMarketplaceClient();
                    if (!$marketplace) return false;
                    if (!app(AccountingService::class)->hasMarketplaceConnector($marketplace->id)) return false;
                    // Check if proforma series is configured
                    $connector = \Illuminate\Support\Facades\DB::table('acc_connectors')
                        ->where('marketplace_client_id', $marketplace->id)
                        ->where('status', 'connected')
                        ->first();
                    if (!$connector) return false;
                    try {
                        $auth = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($connector->auth), true);
                        return !empty($auth['proforma_series_name']);
                    } catch (\Exception $e) {
                        return false;
                    }
                })
                ->action(function () {
                    $this->sendToAccounting($this->record, 'proforma');
                }),

            Actions\Action::make('sendAccounting')
                ->label('Trimite Factură Fiscală')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Trimite factură fiscală')
                ->modalDescription(fn () => "Factura #{$this->record->number} va fi trimisă ca FACTURĂ FISCALĂ în software-ul de contabilitate.")
                ->visible(function () {
                    if (!static::marketplaceHasMicroservice('accounting-connectors')) {
                        return false;
                    }
                    $marketplace = static::getMarketplaceClient();
                    if (!$marketplace) return false;
                    return app(AccountingService::class)->hasMarketplaceConnector($marketplace->id);
                })
                ->action(function () {
                    $this->sendToAccounting($this->record, 'invoice');
                }),

            Actions\Action::make('viewProformaPdf')
                ->label('PDF Proformă')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->url(function () {
                    $meta = $this->record->meta ?? [];
                    return $meta['accounting_proforma']['pdf_url'] ?? null;
                })
                ->openUrlInNewTab()
                ->visible(fn () => !empty(($this->record->meta ?? [])['accounting_proforma']['pdf_url'])),

            Actions\Action::make('viewAccountingPdf')
                ->label('PDF Factură Fiscală')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(function () {
                    $meta = $this->record->meta ?? [];
                    return $meta['accounting']['pdf_url'] ?? null;
                })
                ->openUrlInNewTab()
                ->visible(fn () => !empty(($this->record->meta ?? [])['accounting']['pdf_url'])),

            Actions\Action::make('refreshAccountingPdf')
                ->label('Actualizează PDF')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(function () {
                    $meta = $this->record->meta ?? [];
                    return !empty($meta['accounting']['external_ref']) || !empty($meta['accounting_proforma']['external_ref']);
                })
                ->action(function () {
                    $this->fetchAccountingPdf($this->record);
                }),

            Actions\Action::make('emailAccountingPdf')
                ->label('Trimite PDF pe Email')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Trimite PDF-ul din contabilitate pe email')
                ->modalDescription(function () {
                    $organizer = $this->record->organizer;
                    $email = $organizer?->billing_email ?? $organizer?->email ?? 'N/A';
                    $meta = $this->record->meta ?? [];
                    $provider = $meta['accounting']['provider'] ?? $meta['accounting_proforma']['provider'] ?? 'contabilitate';
                    return "PDF-ul facturii #{$this->record->number} din {$provider} va fi trimis la: {$email}";
                })
                ->visible(function () {
                    $meta = $this->record->meta ?? [];
                    return !empty($meta['accounting']['pdf_url']) || !empty($meta['accounting_proforma']['pdf_url']);
                })
                ->action(function () {
                    $this->sendAccountingPdfEmail($this->record);
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

    protected function sendToAccounting(Invoice $invoice, string $docType = 'invoice'): void
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
        $metaUpdated = false;

        // recipient_type drives whether the customer is the organizer
        // (organizer → fiscal entity, must have CUI) or a generic public
        // buyer (general_client → no CUI, no address). The block below
        // auto-fills missing client fields from the organizer's company
        // profile, which is correct for organizer-recipient invoices but
        // POISONS general_client invoices with the organizer's CUI/address.
        // Default 'organizer' to preserve old behavior for unflagged rows.
        $recipientType = $meta['recipient_type'] ?? 'organizer';
        $isGeneralClient = $recipientType === 'general_client';

        // Auto-fill issuer data from marketplace settings if missing
        if (empty($issuer['cui']) && !empty($marketplace->cui)) {
            $issuer['cui'] = $marketplace->cui;
            $meta['issuer']['cui'] = $marketplace->cui;
            $metaUpdated = true;
        }
        if (empty($issuer['name']) && ($marketplace->company_name ?? $marketplace->name)) {
            $issuer['name'] = $marketplace->company_name ?? $marketplace->name;
            $meta['issuer']['name'] = $issuer['name'];
            $metaUpdated = true;
        }
        if (empty($issuer['reg_com']) && !empty($marketplace->reg_com)) {
            $issuer['reg_com'] = $marketplace->reg_com;
            $meta['issuer']['reg_com'] = $marketplace->reg_com;
            $metaUpdated = true;
        }
        if (empty($issuer['address'])) {
            $addr = implode(', ', array_filter([$marketplace->address, $marketplace->city, $marketplace->state]));
            if ($addr) {
                $issuer['address'] = $addr;
                $meta['issuer']['address'] = $addr;
                $metaUpdated = true;
            }
        }
        if (empty($issuer['bank_name']) && !empty($marketplace->bank_name)) {
            $issuer['bank_name'] = $marketplace->bank_name;
            $meta['issuer']['bank_name'] = $marketplace->bank_name;
            $metaUpdated = true;
        }
        if (empty($issuer['iban']) && !empty($marketplace->bank_account)) {
            $issuer['iban'] = $marketplace->bank_account;
            $meta['issuer']['iban'] = $marketplace->bank_account;
            $metaUpdated = true;
        }
        if (!isset($issuer['vat_payer']) && isset($marketplace->vat_payer)) {
            $issuer['vat_payer'] = (bool) $marketplace->vat_payer;
            $meta['issuer']['vat_payer'] = (bool) $marketplace->vat_payer;
            $metaUpdated = true;
        }

        // Auto-fill client data from organizer profile when missing — but
        // only for organizer-recipient invoices. For general_client we want
        // an explicitly empty CUI/address; auto-filling here was the source
        // of the bug where Oblio matched by name and stamped the organizer's
        // ANAF data on every "Client general" invoice.
        $org = $invoice->organizer;
        if ($org && !$isGeneralClient) {
            if (empty($client['cui']) && !empty($org->company_tax_id)) {
                $client['cui'] = $org->company_tax_id;
                $meta['client']['cui'] = $org->company_tax_id;
                $metaUpdated = true;
            }
            if (empty($client['name']) && !empty($org->company_name)) {
                $client['name'] = $org->company_name ?? $org->name;
                $meta['client']['name'] = $client['name'];
                $metaUpdated = true;
            }
            if (empty($client['reg_com']) && !empty($org->company_registration)) {
                $client['reg_com'] = $org->company_registration;
                $meta['client']['reg_com'] = $org->company_registration;
                $metaUpdated = true;
            }
            if (empty($client['address'])) {
                $addr = implode(', ', array_filter([$org->company_address, $org->company_city, $org->company_county]));
                if ($addr) {
                    $client['address'] = $addr;
                    $meta['client']['address'] = $addr;
                    $metaUpdated = true;
                }
            }
        }

        if ($metaUpdated) {
            $invoice->update(['meta' => $meta]);
        }

        // Validate required data before sending. CUI is mandatory only for
        // organizer-recipient invoices; general_client legitimately has no
        // CUI and Oblio accepts that.
        $errors = [];
        if (empty($client['name'])) $errors[] = 'Numele clientului lipsește.';
        if (!$isGeneralClient && empty($client['cui'])) {
            $errors[] = 'CUI-ul clientului lipsește (și din factură, și din profilul organizatorului).';
        }
        if (empty($items)) $errors[] = 'Factura nu conține articole.';

        if (!empty($errors)) {
            Notification::make()->danger()
                ->title('Date incomplete pentru contabilitate')
                ->body(implode("\n", $errors))
                ->send();
            return;
        }

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

        // Parse client address into components if possible
        $addressParts = array_map('trim', explode(',', $client['address'] ?? ''));
        $street = $addressParts[0] ?? '';
        $city = $addressParts[1] ?? '';
        $county = $addressParts[2] ?? '';

        // Build accounting invoice data
        $invoiceData = [
            'seller_vat' => $issuer['cui'] ?? '',
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'number' => $invoice->number,
            'is_draft' => $useDraft,
            'doc_type' => $docType,
            'customer' => [
                'name' => $client['name'] ?? '',
                'vat_number' => $client['cui'] ?? '',
                'reg_number' => $client['reg_com'] ?? '',
                'email' => $invoice->organizer?->billing_email ?? $invoice->organizer?->email ?? '',
                'address' => [
                    'street' => $street,
                    'city' => $city,
                    'county' => $county,
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
                $docTypeLabel = $docType === 'proforma' ? 'proforma' : 'fiscal';

                // Store accounting reference in meta (separate keys for proforma vs fiscal)
                $metaKey = $docType === 'proforma' ? 'accounting_proforma' : 'accounting';
                $meta[$metaKey] = [
                    'external_ref' => $result['external_ref'],
                    'invoice_number' => $result['invoice_number'],
                    'doc_type' => $docType,
                    'provider' => $connector->provider ?? 'unknown',
                    'sent_at' => now()->toIso8601String(),
                ];

                // Try to fetch PDF link immediately
                try {
                    $pdfResult = $service->getMarketplaceInvoicePdf($marketplace->id, $result['external_ref'], $docType);
                    if (!empty($pdfResult['pdf_url'])) {
                        $meta[$metaKey]['pdf_url'] = $pdfResult['pdf_url'];
                    }
                } catch (\Throwable $e) {
                    // PDF might not be immediately available, that's OK
                    \Log::info("PDF not yet available for {$result['external_ref']}: {$e->getMessage()}");
                }

                $invoice->update(['meta' => $meta]);

                $msg = "Nr. extern: {$result['invoice_number']}";
                if (!empty($meta[$metaKey]['pdf_url'])) {
                    $msg .= ' — PDF disponibil.';
                }

                Notification::make()->success()
                    ->title($docType === 'proforma' ? 'Proformă trimisă' : 'Factură fiscală trimisă')
                    ->body($msg)
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

    protected function fetchAccountingPdf(Invoice $invoice): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            Notification::make()->danger()->title('Marketplace negăsit.')->send();
            return;
        }

        $meta = $invoice->meta ?? [];
        $updated = false;

        $service = app(AccountingService::class);

        // Fetch PDF for each type that has an external ref
        foreach (['accounting' => 'invoice', 'accounting_proforma' => 'proforma'] as $metaKey => $docType) {
            $externalRef = $meta[$metaKey]['external_ref'] ?? null;
            if (!$externalRef) continue;

            try {
                $pdfResult = $service->getMarketplaceInvoicePdf($marketplace->id, $externalRef, $docType);
                if (!empty($pdfResult['pdf_url'])) {
                    $meta[$metaKey]['pdf_url'] = $pdfResult['pdf_url'];
                    $meta[$metaKey]['pdf_fetched_at'] = now()->toIso8601String();
                    $updated = true;
                }
            } catch (\Throwable $e) {
                \Log::error("Fetch {$docType} PDF failed: {$e->getMessage()}");
            }
        }

        if ($updated) {
            $invoice->update(['meta' => $meta]);
            Notification::make()->success()
                ->title('PDF actualizat')
                ->body('Link-urile PDF au fost preluate cu succes.')
                ->send();
        } else {
            Notification::make()->warning()
                ->title('PDF indisponibil')
                ->body('PDF-urile nu sunt încă disponibile. Încercați din nou mai târziu.')
                ->send();
        }
    }

    protected function sendAccountingPdfEmail(Invoice $invoice): void
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

        $meta = $invoice->meta ?? [];
        // Prefer fiscal PDF, fallback to proforma
        $pdfUrl = $meta['accounting']['pdf_url'] ?? $meta['accounting_proforma']['pdf_url'] ?? null;

        if (!$pdfUrl) {
            Notification::make()->danger()->title('PDF-ul nu este disponibil.')->send();
            return;
        }

        $marketplace = static::getMarketplaceClient();
        $transport = $marketplace?->getSmtpTransport();

        if (!$transport) {
            Notification::make()->danger()->title('SMTP nu este configurat.')->send();
            return;
        }

        try {
            $accMeta = !empty($meta['accounting']['pdf_url']) ? $meta['accounting'] : ($meta['accounting_proforma'] ?? []);
            $provider = ucfirst($accMeta['provider'] ?? 'contabilitate');
            $accNumber = $accMeta['invoice_number'] ?? $invoice->number;

            $html = <<<HTML
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <h2 style="color:#1f2937;">Factură #{$invoice->number}</h2>
                <p>Bună ziua,</p>
                <p>Vă transmitem factura <strong>#{$invoice->number}</strong> emisă prin {$provider} (nr. extern: {$accNumber}).</p>
                <p>Puteți vizualiza și descărca factura accesând link-ul de mai jos:</p>
                <p style="margin:24px 0;">
                    <a href="{$pdfUrl}" style="background:#2563eb;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;">
                        Vizualizează / Descarcă PDF
                    </a>
                </p>
                <p style="color:#6b7280;font-size:13px;">Dacă butonul nu funcționează, copiați acest link în browser:<br>{$pdfUrl}</p>
            </div>
            HTML;

            $wrappedHtml = $this->wrapInEmailTemplate($html, $marketplace);

            $emailMessage = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $marketplace->getEmailFromAddress(),
                    $marketplace->getEmailFromName()
                ))
                ->to($email)
                ->subject("Factură #{$invoice->number} — PDF {$provider}")
                ->html($wrappedHtml);

            $transport->send($emailMessage);

            Notification::make()->success()
                ->title('Email trimis')
                ->body("PDF-ul facturii a fost trimis la {$email}")
                ->send();
        } catch (\Throwable $e) {
            \Log::error("Failed to send accounting PDF email: {$e->getMessage()}");
            Notification::make()->danger()
                ->title('Eroare la trimitere')
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
