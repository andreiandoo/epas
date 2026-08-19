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

    /**
     * The form is display-only now (a single HTML placeholder with all
     * the invoice data laid out). Save/Cancel at the bottom would do
     * nothing and confuse operators — the real actions (Trimite proformă,
     * Trimite factură fiscală, Marchează Achitată, Trimite eFactura, etc.)
     * live in getHeaderActions() below.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Auto-check the invoice's live state at the accounting provider every
     * time an operator opens this page. Per operator spec: no caching —
     * they want the freshest state on every mount. Only runs when we have
     * an external_ref (nothing to check otherwise); silently no-ops when
     * the connector is missing or the adapter returns "unknown", so a
     * transient error never blocks the page from loading.
     *
     * Rule 4 of the spec: if the check flips the invoice to
     * deleted/stornoed and it was locally 'paid', applyOblioStatus() resets
     * status → outstanding + clears paid_at. We flash a toast so the
     * operator knows why the status pill they were expecting is missing.
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->autoCheckOblioStatus('mount', silentUnchanged: true);
    }

    /**
     * Ping the accounting provider for this invoice's current state and
     * mutate meta.accounting accordingly via Invoice::applyOblioStatus().
     * Shared by the mount()-auto-check and the manual "Verifică status
     * Oblio" header button.
     *
     * $silentUnchanged: on mount we don't want a toast when nothing
     * changed (that would fire on every page open). The manual button
     * always shows the outcome.
     */
    protected function autoCheckOblioStatus(string $source, bool $silentUnchanged = false): ?string
    {
        $meta = $this->record->meta ?? [];
        $externalRef = $meta['accounting']['external_ref'] ?? null;
        $docType = $meta['accounting']['doc_type'] ?? 'invoice';
        if (empty($externalRef)) {
            return null;
        }

        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        try {
            $wasPaid = $this->record->status === 'paid';
            $result = app(AccountingService::class)->getMarketplaceInvoiceStatus(
                $marketplace->id,
                $externalRef,
                $docType
            );
            $applied = $this->record->applyOblioStatus($result, $source);
            $this->record->refresh();

            if (in_array($applied, ['deleted', 'stornoed'], true)) {
                $label = $applied === 'stornoed' ? 'storno-tă în Oblio' : 'ștearsă în Oblio';
                Notification::make()
                    ->title("Factura este {$label}")
                    ->body($wasPaid
                        ? 'Statusul local a fost resetat automat la Neachitată (nu poți avea Achitată local pe un document inexistent la contabilitate).'
                        : 'Poți acum să regenerezi conținutul și să retrimiți factura.')
                    ->warning()
                    ->send();
            } elseif ($applied === 'live' && !$silentUnchanged) {
                Notification::make()
                    ->title('Factura este activă în Oblio')
                    ->success()
                    ->send();
            } elseif ($applied === 'unknown' && !$silentUnchanged) {
                Notification::make()
                    ->title('Status Oblio necunoscut')
                    ->body($result['message'] ?? 'Verificare eșuată — reîncearcă mai târziu.')
                    ->warning()
                    ->send();
            }
            return $applied;
        } catch (\Throwable $e) {
            \Log::warning('[EditOrganizerInvoice.autoCheckOblioStatus] failed', [
                'invoice_id' => $this->record->id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
            if (!$silentUnchanged) {
                Notification::make()
                    ->title('Nu s-a putut verifica status Oblio')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();
            }
            return null;
        }
    }

    protected function getHeaderActions(): array
    {
        // Primary standalone action — completing the invoice payment status.
        // Hidden when the invoice is voided at the provider — you can't be
        // "paid" locally on a document that no longer exists at Oblio (rule 4
        // of the spec). If someone flipped the state after the button was
        // rendered, the action() itself refuses too.
        $markPaid = Actions\Action::make('markPaid')
            ->label('Marchează Achitată')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn () => $this->record->status !== 'paid' && !$this->record->isVoidedInOblio())
            ->action(function () {
                if ($this->record->isVoidedInOblio()) {
                    Notification::make()->danger()
                        ->title('Nu se poate marca achitată')
                        ->body('Factura este ' . ($this->record->isStornoedInOblio() ? 'storno-tă' : 'ștearsă') . ' în Oblio.')
                        ->send();
                    return;
                }
                $this->record->markAsPaid('manual');
                Notification::make()->success()->title('Factură marcată ca achitată.')->send();
                $this->fillForm();
            });

        // Persistent "Voided in Oblio" indicator — informational button
        // that just shows the current void state as a red pill. Clicking
        // it re-runs the check (same as $checkOblioStatus). Only rendered
        // when the invoice is actually voided.
        $oblioVoidBadge = Actions\Action::make('oblioVoidBadge')
            ->label(function () {
                $meta = $this->record->meta ?? [];
                if ($this->record->isStornoedInOblio()) {
                    $when = $meta['accounting']['stornoed_at'] ?? null;
                    $whenLabel = $when ? \Carbon\Carbon::parse($when)->format('d.m.Y H:i') : '';
                    return trim('Storno-tă în Oblio ' . $whenLabel);
                }
                if ($this->record->isDeletedInOblio()) {
                    $when = $meta['accounting']['deleted_at'] ?? null;
                    $whenLabel = $when ? \Carbon\Carbon::parse($when)->format('d.m.Y H:i') : '';
                    return trim('Ștearsă din Oblio ' . $whenLabel);
                }
                return '';
            })
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->visible(fn () => $this->record->isVoidedInOblio())
            ->action(function () {
                $this->autoCheckOblioStatus('manual', silentUnchanged: false);
            });

        // eFactura status indicator (dynamic label/color) — kept standalone as
        // it reads as a status badge, not an action.
        $efacturaStatus = Actions\Action::make('efacturaStatus')
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
            ->visible(fn () => $this->record->anafQueue !== null);

        // ── Grup "Trimite" (toate acțiunile outbound) ──
        $email = Actions\Action::make('email')
            ->label('Trimite factura pe email')
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
            });

        $sendProforma = Actions\Action::make('sendProforma')
            ->label('Trimite Proformă')
            ->icon('heroicon-o-document-duplicate')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Trimite factură proformă')
            ->modalDescription(fn () => "Factura #{$this->record->number} va fi trimisă ca PROFORMĂ în software-ul de contabilitate.")
            ->visible(function () {
                $meta = $this->record->meta ?? [];
                // Deja emisă ca proformă, sau deja finalizată ca fiscală → nu
                // retrimite aceeași proformă.
                if (!empty($meta['accounting_proforma']['external_ref']) || !empty($meta['accounting']['external_ref'])) {
                    return false;
                }
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
            });

        $sendAccounting = Actions\Action::make('sendAccounting')
            ->label(function () {
                // "Retrimite" label when we're resending after a
                // storno/delete in Oblio — surfaces the fact that a fresh
                // Oblio number will be assigned (the old one is archived
                // in meta.accounting.history and won't be reused).
                if ($this->record->isVoidedInOblio()) {
                    $prev = $this->record->meta['accounting']['history'] ?? [];
                    $lastRef = !empty($prev) ? end($prev)['external_ref'] ?? null : null;
                    return $lastRef
                        ? "Retrimite Factură Fiscală (fost {$lastRef})"
                        : 'Retrimite Factură Fiscală';
                }
                return 'Trimite Factură Fiscală';
            })
            ->icon('heroicon-o-calculator')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn () => $this->record->isVoidedInOblio()
                ? 'Retrimite factură fiscală (număr nou)'
                : 'Trimite factură fiscală')
            ->modalDescription(fn () => $this->record->isVoidedInOblio()
                ? "Factura #{$this->record->number} a fost " . ($this->record->isStornoedInOblio() ? 'storno-tă' : 'ștearsă') . " în Oblio. Se va reemite cu un număr NOU la contabilitate; vechea referință rămâne arhivată."
                : "Factura #{$this->record->number} va fi trimisă ca FACTURĂ FISCALĂ în software-ul de contabilitate.")
            ->visible(function () {
                // Show when NOT live at provider — that covers both
                // "never sent" and "sent then deleted/stornoed at Oblio".
                if ($this->record->hasLiveOblioInvoice()) {
                    return false;
                }
                if (!static::marketplaceHasMicroservice('accounting-connectors')) {
                    return false;
                }
                $marketplace = static::getMarketplaceClient();
                if (!$marketplace) return false;
                return app(AccountingService::class)->hasMarketplaceConnector($marketplace->id);
            })
            ->action(function () {
                // Before overwriting meta.accounting with the new send
                // result, archive the previous external_ref (+ pdf_url +
                // deleted_at markers) into meta.accounting.history so we
                // keep the full trail. The current sendToAccounting call
                // assigns new values on the same meta.accounting slot.
                if (!empty($this->record->meta['accounting']['external_ref'] ?? null)) {
                    $reason = $this->record->isStornoedInOblio()
                        ? 'stornoed_in_oblio'
                        : ($this->record->isDeletedInOblio() ? 'deleted_in_oblio' : 'resend');
                    $this->record->archiveCurrentOblioRef($reason);
                    $this->record->refresh();
                }
                $this->sendToAccounting($this->record, 'invoice');
            });

        // Manual "Verifică status Oblio" — force-refresh state on demand.
        // Auto-check on mount already runs silently; this button is for
        // when the operator just deleted/storno-ed in Oblio and doesn't
        // want to reload the whole page.
        $checkOblioStatus = Actions\Action::make('checkOblioStatus')
            ->label('Verifică status Oblio')
            ->icon('heroicon-o-magnifying-glass')
            ->color('gray')
            ->visible(fn () => !empty(($this->record->meta ?? [])['accounting']['external_ref']))
            ->action(function () {
                $this->autoCheckOblioStatus('manual', silentUnchanged: false);
            });

        // "Regenerează factură" — rebuild items + amount from payout
        // snapshot. Never touches the provider (organizer must Send
        // Fiscal after this to actually emit the new document). Hidden
        // whenever the invoice is live at Oblio — you can't rewrite the
        // content of a document that still exists there.
        $regenerateContent = Actions\Action::make('regenerateContent')
            ->label('Regenerează factură')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Regenerează conținutul facturii')
            ->modalDescription(fn () => "Se vor reface articolele + sumele din decontul {$this->record->payout?->reference} pe baza vânzărilor curente. Factura NU va fi trimisă automat la Oblio — după regenerare folosește 'Retrimite Factură Fiscală'.")
            ->visible(fn () => !$this->record->hasLiveOblioInvoice() && $this->record->marketplace_payout_id)
            ->action(function () {
                try {
                    $diff = $this->record->rebuildFromPayout();
                    $old = $diff['old'];
                    $new = $diff['new'];
                    $delta = $new['amount'] - $old['amount'];
                    Notification::make()
                        ->title('Factură regenerată')
                        ->body(sprintf(
                            'Articole: %d → %d · Total: %s → %s RON (%s%s)',
                            $old['items_count'],
                            $new['items_count'],
                            number_format($old['amount'], 2),
                            number_format($new['amount'], 2),
                            $delta >= 0 ? '+' : '',
                            number_format($delta, 2)
                        ))
                        ->success()
                        ->send();
                    $this->redirect(OrganizerInvoiceResource::getUrl('edit', ['record' => $this->record]));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Regenerarea a eșuat')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });

        $sendEfactura = Actions\Action::make('sendEfactura')
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
            });

        $emailAccountingPdf = Actions\Action::make('emailAccountingPdf')
            ->label('Trimite PDF contabilitate pe email')
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
            });

        // ── Grup "Documente" (PDF-uri de vizualizat / regenerat) ──
        $viewProformaPdf = Actions\Action::make('viewProformaPdf')
            ->label('PDF Proformă')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->url(function () {
                $meta = $this->record->meta ?? [];
                return $meta['accounting_proforma']['pdf_url'] ?? null;
            })
            ->openUrlInNewTab()
            ->visible(fn () => !empty(($this->record->meta ?? [])['accounting_proforma']['pdf_url']));

        $viewAccountingPdf = Actions\Action::make('viewAccountingPdf')
            ->label('PDF Factură Fiscală')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->url(function () {
                $meta = $this->record->meta ?? [];
                return $meta['accounting']['pdf_url'] ?? null;
            })
            ->openUrlInNewTab()
            ->visible(fn () => !empty(($this->record->meta ?? [])['accounting']['pdf_url']));

        $refreshAccountingPdf = Actions\Action::make('refreshAccountingPdf')
            ->label('Actualizează PDF')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(function () {
                $meta = $this->record->meta ?? [];
                return !empty($meta['accounting']['external_ref']) || !empty($meta['accounting_proforma']['external_ref']);
            })
            ->action(function () {
                $this->fetchAccountingPdf($this->record);
            });

        // ── Overflow (rar + distructiv) ──
        $delete = Actions\DeleteAction::make()
            ->label('Șterge factura')
            ->requiresConfirmation()
            ->modalHeading('Șterge factura')
            ->modalDescription(fn () => "Factura #{$this->record->number} va fi ștearsă. Această acțiune nu poate fi anulată.")
            ->successRedirectUrl(fn () => OrganizerInvoiceResource::getUrl('index'));

        return [
            // Red void-state indicator renders first so it's the most
            // visible thing an operator sees when opening a broken invoice.
            $oblioVoidBadge,
            $markPaid,
            $efacturaStatus,
            Actions\ActionGroup::make([
                $email,
                $sendProforma,
                $sendAccounting,
                $sendEfactura,
                $emailAccountingPdf,
            ])
                ->label('Trimite ▾')
                ->icon('heroicon-o-paper-airplane')
                ->button(),
            Actions\ActionGroup::make([
                $viewProformaPdf,
                $viewAccountingPdf,
                $refreshAccountingPdf,
                $checkOblioStatus,
                $regenerateContent,
            ])
                ->label('Documente ▾')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->button(),
            Actions\ActionGroup::make([
                $delete,
            ])
                ->label('Mai multe')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),
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
            // Resolve the correct society for this invoice's decont. Leisure
            // secondary deconturi (payout.issuing_company='secondary') bill the
            // SECONDARY society (e.g. Csomadcom SRL); ordinary invoices resolve
            // to the organizer's primary company. Falls back to the raw primary
            // columns when there's no linked payout.
            $party = $invoice->payout?->organizerInvoiceParty() ?? [
                'name' => $org->company_name ?? $org->name,
                'cui' => $org->company_tax_id ?? '',
                'reg_com' => $org->company_registration ?? '',
                'address' => implode(', ', array_filter([$org->company_address, $org->company_city, $org->company_county])),
            ];

            if (empty($client['cui']) && !empty($party['cui'])) {
                $client['cui'] = $party['cui'];
                $meta['client']['cui'] = $party['cui'];
                $metaUpdated = true;
            }
            if (empty($client['name']) && !empty($party['name'])) {
                $client['name'] = $party['name'];
                $meta['client']['name'] = $party['name'];
                $metaUpdated = true;
            }
            if (empty($client['reg_com']) && !empty($party['reg_com'])) {
                $client['reg_com'] = $party['reg_com'];
                $meta['client']['reg_com'] = $party['reg_com'];
                $metaUpdated = true;
            }
            if (empty($client['address']) && !empty($party['address'])) {
                $client['address'] = $party['address'];
                $meta['client']['address'] = $party['address'];
                $metaUpdated = true;
            }
        }

        if ($metaUpdated) {
            $invoice->update(['meta' => $meta]);
        }

        // Validate required data before sending. CUI is mandatory only for
        // organizer-recipient invoices; general_client legitimately has no
        // CUI and Oblio accepts that.
        $errors = [];
        if (!$isGeneralClient && empty($client['name'])) $errors[] = 'Numele clientului lipsește.';
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

        // For general_client invoices we send a FIXED generic B2C client to the
        // accounting provider ("Client Divers - Persoană Fizică"), so retail /
        // individual sales never leak the organizer's data and Oblio always
        // receives consistent details. The 13-zero CUI is non-numeric-real, so
        // the OblioAdapter treats it as B2C (no ANAF auto-complete / customer
        // save). For organizer-recipient invoices, use the real client data.
        if ($isGeneralClient) {
            $customerEmail = '';
            $customerName = 'Client Divers Persoană Fizică';
            $vatNumber = '0000000000000';
            $customerRegNumber = '00';
            $customerCode = '001';
            $street = '';
            $city = 'sector 4';
            $county = 'Bucuresti';
            // SAVE the client in Oblio so it dedupes by this (consistent) CUI on
            // every future general_client invoice — without save=1 Oblio creates
            // a brand-new client each time. autocomplete=0 so it does NOT ANAF-
            // lookup the placeholder CUI (which would fail / overwrite the data).
            $customerSave = 1;
            $customerAutocomplete = 0;
        } else {
            $customerEmail = $invoice->organizer?->billing_email ?? $invoice->organizer?->email ?? '';
            $customerName = $client['name'] ?? '';
            $vatNumber = $client['cui'] ?? '';
            $customerRegNumber = $client['reg_com'] ?? '';
            $customerCode = '';
            // Let the adapter decide save/autocomplete from whether the CUI is real.
            $customerSave = null;
            $customerAutocomplete = null;
        }

        // Invoice preparer (name + CNP) from marketplace settings. When
        // set, Oblio renders them at the bottom of the generated invoice
        // as "Întocmit de: {name}, CNP {cnp}". Both empty → Oblio falls
        // back to whatever's configured on their account.
        $marketplaceSettings = $marketplace->settings ?? [];
        $issuerName = trim((string) ($marketplaceSettings['invoice_preparer'] ?? ''));
        $issuerCnp = trim((string) ($marketplaceSettings['invoice_preparer_cnp'] ?? ''));

        // Build accounting invoice data
        $invoiceData = [
            'seller_vat' => $issuer['cui'] ?? '',
            'issuer_name' => $issuerName,
            'issuer_cnp' => $issuerCnp,
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'number' => $invoice->number,
            'is_draft' => $useDraft,
            'doc_type' => $docType,
            'customer' => [
                'name' => $customerName,
                'vat_number' => $vatNumber,
                'reg_number' => $customerRegNumber,
                'code' => $customerCode,
                'email' => $customerEmail,
                'save' => $customerSave,
                'autocomplete' => $customerAutocomplete,
                'address' => [
                    'street' => $street,
                    'city' => $city,
                    'county' => $county,
                    'country' => 'Romania',
                ],
            ],
            'lines' => array_map(function ($item) {
                // New split (2026-08-07): item['name'] = canonical article
                // title shown as product_name in Oblio; item['description'] =
                // per-payout event/decont context stored as the article's
                // description. Legacy items with only 'description' fall
                // back to using the same text for both (old behaviour).
                $name = $item['name'] ?? $item['description'] ?? '';
                $desc = $item['description'] ?? '';
                return [
                    'product_name' => $name,
                    'description' => $desc,
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['price'] ?? $item['unit_price'] ?? 0),
                    'tax_rate' => 19,
                    'unit' => 'buc',
                ];
            }, $items),
        ];

        // Diagnostic: dump exactly what we hand to Oblio so we can confirm
        // general_client invoices send vat_number='vanzare online' and email=''.
        // Will be removed once verified end-to-end.
        \Log::info('[EditOrganizerInvoice.sendToAccounting] payload to AccountingService', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'doc_type' => $docType,
            'is_general_client' => $isGeneralClient,
            'recipient_type' => $recipientType,
            'customer.name' => $invoiceData['customer']['name'] ?? null,
            'customer.vat_number' => $invoiceData['customer']['vat_number'] ?? null,
            'customer.email' => $invoiceData['customer']['email'] ?? null,
            'customer.address' => $invoiceData['customer']['address'] ?? null,
        ]);

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

                // When a fiscal invoice is issued and a proforma exists, the
                // proforma is superseded — delete it from the provider so it
                // doesn't linger as a shadow document. Only providers that
                // return supported=true actually perform the delete; others
                // are logged for manual cleanup.
                $proformaCleanupNote = '';
                if ($docType === 'invoice' && !empty($meta['accounting_proforma']['external_ref'])) {
                    $proformaRef = $meta['accounting_proforma']['external_ref'];
                    $del = $service->deleteMarketplaceInvoice($marketplace->id, $proformaRef, 'proforma');
                    if ($del['success'] ?? false) {
                        // Preserve a stub in meta for audit; drop external_ref/pdf_url
                        // so the derived "Tip" column flips from proforma → fiscala.
                        $meta['accounting_proforma_deleted'] = array_merge(
                            $meta['accounting_proforma'],
                            ['deleted_at' => now()->toIso8601String()]
                        );
                        unset($meta['accounting_proforma']);
                        $proformaCleanupNote = ' Proforma ' . $proformaRef . ' ștearsă din contabilitate.';
                    } elseif (($del['supported'] ?? true) === false) {
                        \Log::info("Proforma cleanup not supported by provider for invoice {$invoice->id}: {$del['message']}");
                        $proformaCleanupNote = ' Șterge manual proforma ' . $proformaRef . ' din contabilitate.';
                    } else {
                        \Log::warning("Proforma cleanup failed for invoice {$invoice->id}", [
                            'proforma_ref' => $proformaRef,
                            'error' => $del['message'] ?? 'unknown',
                        ]);
                        $proformaCleanupNote = ' Atenție: ștergerea proformei ' . $proformaRef . ' a eșuat (' . ($del['message'] ?? 'necunoscut') . ').';
                    }
                }

                $invoice->update(['meta' => $meta]);

                $msg = "Nr. extern: {$result['invoice_number']}";
                if (!empty($meta[$metaKey]['pdf_url'])) {
                    $msg .= ' — PDF disponibil.';
                }
                $msg .= $proformaCleanupNote;

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
        // Facturi = tranzacțional → providerul tranzacțional cu fallback runtime la primary.
        if (!$marketplace?->hasMailConfigured() && !$marketplace?->hasTransactionalMailConfigured()) {
            Notification::make()->danger()->title('SMTP nu este configurat.')->send();
            return;
        }

        $fromAddress = $marketplace->getTransactionalEmailFromAddress();
        $fromName = $marketplace->getTransactionalEmailFromName();

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
                ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                ->to($email)
                ->subject("Factură #{$invoice->number} — PDF {$provider}")
                ->html($wrappedHtml);

            $result = $marketplace->sendTransactionalEmail($emailMessage);

            if (!$result['success']) {
                Notification::make()->danger()
                    ->title('Eroare la trimitere')
                    ->body($result['error'] ?? 'Trimiterea a eșuat')
                    ->send();
                return;
            }

            $suffix = $result['transport_used'] === 'primary_fallback' ? ' (via Brevo fallback)' : '';
            Notification::make()->success()
                ->title('Email trimis')
                ->body("PDF-ul facturii a fost trimis la {$email}{$suffix}")
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
        // Facturi = tranzacțional → providerul tranzacțional cu fallback runtime la primary.
        if (!$marketplace?->hasMailConfigured() && !$marketplace?->hasTransactionalMailConfigured()) {
            Notification::make()->danger()->title('SMTP nu este configurat.')->send();
            return;
        }

        $fromAddress = $marketplace->getTransactionalEmailFromAddress();
        $fromName = $marketplace->getTransactionalEmailFromName();

        try {
            $html = OrganizerInvoiceResource::renderInvoiceHtml($invoice);
            $wrappedHtml = $this->wrapInEmailTemplate($html, $marketplace);

            $emailMessage = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                ->to($email)
                ->subject("Factură #{$invoice->number}")
                ->html($wrappedHtml);

            $result = $marketplace->sendTransactionalEmail($emailMessage);

            if (!$result['success']) {
                Notification::make()->danger()
                    ->title('Eroare la trimitere')
                    ->body($result['error'] ?? 'Trimiterea a eșuat')
                    ->send();
                return;
            }

            $suffix = $result['transport_used'] === 'primary_fallback' ? ' (via Brevo fallback)' : '';
            Notification::make()->success()
                ->title('Email trimis')
                ->body("Factura a fost trimisă la {$email}{$suffix}")
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
