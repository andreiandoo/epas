<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Models\Invoice;
use App\Models\OrganizerDocument;
use App\Services\Accounting\AccountingService;
use App\Services\EFactura\EFacturaService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        $decont = $this->record->decontDocument;
        $invoice = $this->record->invoice;

        return [
            // ========== STATUS ACTIONS ==========
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeApproved())
                ->action(function () {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->approve($admin->id);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('process')
                ->label('Mark Processing')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeProcessed())
                ->action(function () {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->markAsProcessing($admin->id);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('complete')
                ->label('Complete Payout')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canBeCompleted())
                ->form([
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference')
                        ->required()
                        ->helperText('Bank transfer reference or transaction ID'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    $this->refreshFormData(['status', 'payment_reference', 'payment_notes', 'completed_at']);
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canBeRejected())
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->reject($admin->id, $data['reason']);

                    // Delete associated decont document if exists
                    $decont = $this->record->decontDocument;
                    if ($decont) {
                        if ($decont->file_path) {
                            Storage::disk('public')->delete($decont->file_path);
                        }
                        $decont->delete();
                    }

                    // Delete associated invoice if exists
                    $invoice = $this->record->invoice;
                    if ($invoice) {
                        $invoice->delete();
                    }

                    Notification::make()->title('Decont respins')->body('Documentele asociate au fost șterse.')->success()->send();
                    $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('add_note')
                ->label('Admin Note')
                ->icon('heroicon-o-pencil-square')
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes')
                        ->default(fn () => $this->record->admin_notes)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update(['admin_notes' => $data['admin_notes']]);
                    $this->refreshFormData(['admin_notes']);
                }),

            // Recalcul snapshot din SalesBreakdownService — util pentru deconturile
            // create inainte de refactor (snapshot pe baza prețului catalog) sau
            // dupa modificari de preturi pe bilete. Doar status-uri editabile.
            Actions\Action::make('recalc_breakdown')
                ->label('Recalculează snapshot bilete')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Se va înlocui snapshot-ul actual cu valorile recalculate din vânzările reale (preț plătit per bilet, comision, discounturi, asigurări). Documentele de decont/factură generate trebuie regenerate manual după recalcul.')
                ->visible(fn () => in_array($this->record->status, ['pending', 'approved', 'processing'])
                    && !empty($this->record->event_id))
                ->action(function () {
                    $payout = $this->record;
                    $event = $payout->event;
                    if (!$event) {
                        Notification::make()->title('Eroare')->body('Decontul nu este legat de un eveniment.')->danger()->send();
                        return;
                    }

                    $service = app(\App\Services\Marketplace\SalesBreakdownService::class);
                    $rows = $service->buildForPayout($event, $payout->period_start, $payout->period_end);
                    if (empty($rows)) {
                        Notification::make()->title('Nu s-au găsit vânzări')->body('Nu există bilete valide în perioada decontului pentru a recalcula snapshot-ul.')->warning()->send();
                        return;
                    }
                    $summary = $service->summarizeForPayout($event, $payout->period_start, $payout->period_end);

                    $payout->update([
                        'ticket_breakdown' => $rows,
                        'commission_mode' => $summary['commission_mode'],
                    ]);

                    Notification::make()
                        ->title('Snapshot recalculat')
                        ->body('Net final per tip de bilet: ' . number_format($summary['net_amount'], 2) . ' RON, comision: ' . number_format($summary['commission_amount'], 2) . ' RON.')
                        ->success()
                        ->send();
                    $this->redirect(PayoutResource::getUrl('view', ['record' => $payout]));
                }),

            // ========== GENERATE DECONT (when none exists) ==========
            Actions\Action::make('generate_decont')
                ->label('Generează Decont')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Se va genera documentul de decont pentru acest payout.')
                ->visible(fn () => $this->record->decontDocument === null && in_array($this->record->status, ['approved', 'processing', 'completed']) && !in_array($this->record->status, ['rejected', 'cancelled']))
                ->action(function () {
                    $this->runDecontGeneration(isRegeneration: false);
                }),

            // ========== DECONT ACTIONS (when decont exists) ==========
            Actions\ActionGroup::make([
                Actions\Action::make('view_decont')
                    ->label('Vezi decont')
                    ->icon('heroicon-o-eye')
                    ->url(fn () => OrganizerDocumentResource::getUrl('view', ['record' => $this->record->decontDocument]))
                    ->openUrlInNewTab(),

                Actions\Action::make('download_decont')
                    ->label('Descarcă decont')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => $this->record->decontDocument?->file_path !== null)
                    ->url(fn () => $this->record->decontDocument?->download_url, shouldOpenInNewTab: true),

                Actions\Action::make('send_decont')
                    ->label('Trimite decont')
                    ->icon('heroicon-o-envelope')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Adresa email')
                            ->email()
                            ->required()
                            ->default(fn () => $this->record->organizer?->billing_email ?? $this->record->organizer?->email),
                    ])
                    ->action(function (array $data) {
                        $this->sendDocumentByEmail($this->record->decontDocument, $data['email'], 'Decont');
                    }),

                Actions\Action::make('regenerate_decont')
                    ->label('Regenerează decont')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Decontul existent va fi șters și regenerat.')
                    ->action(function () {
                        $existingDecont = $this->record->decontDocument;
                        if ($existingDecont) {
                            if ($existingDecont->file_path) {
                                Storage::disk('public')->delete($existingDecont->file_path);
                            }
                            $existingDecont->delete();
                            $this->record->unsetRelation('decontDocument');
                        }
                        $this->runDecontGeneration(isRegeneration: true);
                    }),
            ])
                ->label('Decont')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->button()
                ->visible(fn () => $this->record->decontDocument !== null && !in_array($this->record->status, ['rejected', 'cancelled'])),

            // ========== GENERATE INVOICE (when decont exists but no invoice) ==========
            Actions\Action::make('generate_invoice')
                ->label('Generează factură')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Se va genera o factură asociată acestui decont.')
                ->visible(fn () => $this->record->decontDocument !== null && $this->record->invoice === null && !in_array($this->record->status, ['rejected', 'cancelled']))
                ->action(function () {
                    $payout = $this->record;
                    $organizer = $payout->organizer;
                    $marketplace = $payout->marketplaceClient;

                    $recipientType = $payout->invoice_recipient_type
                        ?? ($payout->commission_mode === 'added_on_top' ? 'general_client' : 'organizer');

                    if ($recipientType === 'general_client') {
                        $client = [
                            'name' => $marketplace->settings['general_invoice_client_name'] ?? 'Client general',
                            'cui' => $marketplace->settings['general_invoice_client_cui'] ?? '',
                            'address' => $marketplace->settings['general_invoice_client_address'] ?? '',
                        ];
                    } else {
                        $client = [
                            'name' => $organizer->company_name ?? $organizer->name,
                            'cui' => $organizer->cui ?? '',
                            'address' => $organizer->address ?? '',
                        ];
                    }

                    $lastInvoice = Invoice::where('marketplace_client_id', $marketplace->id)
                        ->orderByDesc('id')
                        ->first();
                    $nextNumber = $lastInvoice ? ((int) preg_replace('/\D/', '', $lastInvoice->number) + 1) : 1;
                    $invoiceNumber = 'F-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

                    // Use commission EXCLUDING POS — POS commission is billed on a
                    // separate Factură POS, since those sales never flowed through
                    // the marketplace.
                    $commissionSubtotal = $payout->getCommissionExclPos();
                    $vatRate = $marketplace->vat_payer ? 19 : 0;
                    $vatAmount = $vatRate > 0 ? round($commissionSubtotal * $vatRate / 100, 2) : 0;

                    $invoice = Invoice::create([
                        'marketplace_client_id' => $marketplace->id,
                        'marketplace_organizer_id' => $organizer->id,
                        'marketplace_payout_id' => $payout->id,
                        'number' => $invoiceNumber,
                        'type' => 'fiscal',
                        'description' => 'Factura pentru decont ' . $payout->reference,
                        'issue_date' => now(),
                        'period_start' => $payout->period_start,
                        'period_end' => $payout->period_end,
                        'due_date' => now()->addDays(30),
                        'subtotal' => $commissionSubtotal,
                        'vat_rate' => $vatRate,
                        'vat_amount' => $vatAmount,
                        'amount' => $commissionSubtotal + $vatAmount,
                        'currency' => $payout->currency ?? 'RON',
                        'status' => 'outstanding',
                        'meta' => [
                            'payout_reference' => $payout->reference,
                            'issuer' => [
                                'name' => $marketplace->company_name ?? $marketplace->name,
                                'cui' => $marketplace->cui ?? '',
                                'address' => $marketplace->address ?? '',
                            ],
                            'client' => $client,
                            'recipient_type' => $recipientType,
                            'items' => [[
                                'description' => 'Comision servicii ticketing - ' . $payout->reference,
                                'quantity' => 1,
                                'unit_price' => $commissionSubtotal,
                                'amount' => $commissionSubtotal,
                            ]],
                        ],
                    ]);

                    Notification::make()->title('Factura generata: ' . $invoiceNumber)->success()->send();
                    $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('view_invoice')
                    ->label('Vezi factura')
                    ->icon('heroicon-o-eye')
                    ->visible(fn () => $this->record->invoice !== null)
                    ->url(fn () => OrganizerInvoiceResource::getUrl('edit', ['record' => $this->record->invoice]))
                    ->openUrlInNewTab(),

                Actions\Action::make('download_invoice')
                    ->label('Descarca factura')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(function () {
                        $inv = $this->record->invoice;
                        if (!$inv) return false;
                        $meta = $inv->meta ?? [];
                        return !empty($meta['accounting']['pdf_url']) || !empty($meta['accounting_proforma']['pdf_url']);
                    })
                    ->url(function () {
                        $meta = $this->record->invoice?->meta ?? [];
                        return $meta['accounting']['pdf_url'] ?? $meta['accounting_proforma']['pdf_url'] ?? null;
                    }, shouldOpenInNewTab: true),

                Actions\Action::make('send_to_accounting')
                    ->label(function () {
                        $providerLabel = $this->getAccountingProviderLabel();
                        return $providerLabel
                            ? "Trimite la {$providerLabel}"
                            : 'Trimite la contabilitate';
                    })
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        $providerLabel = $this->getAccountingProviderLabel() ?? 'software-ul de contabilitate';
                        $docLabel = $this->isAccountingDraftMode() ? 'DRAFT' : 'FACTURĂ FISCALĂ';
                        return "Factura va fi trimisa ca {$docLabel} in {$providerLabel}.";
                    })
                    ->visible(function () {
                        $invoice = $this->record->invoice;
                        if (!$invoice) return false;
                        // Hide if already submitted to accounting provider
                        $meta = $invoice->meta ?? [];
                        if (!empty($meta['accounting']['external_ref'])) return false;
                        $marketplace = $this->record->marketplaceClient;
                        if (!$marketplace) return false;
                        return app(AccountingService::class)->hasMarketplaceConnector($marketplace->id);
                    })
                    ->action(function () {
                        $this->sendInvoiceToAccounting($this->record->invoice);
                    }),

                Actions\Action::make('send_invoice')
                    ->label('Trimite factura')
                    ->icon('heroicon-o-envelope')
                    ->visible(function () {
                        $inv = $this->record->invoice;
                        if (!$inv) return false;
                        $meta = $inv->meta ?? [];
                        return !empty($meta['accounting']['pdf_url']) || !empty($meta['accounting_proforma']['pdf_url']);
                    })
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Adresa email')
                            ->email()
                            ->required()
                            ->default(fn () => $this->record->organizer?->billing_email ?? $this->record->organizer?->email),
                    ])
                    ->action(function (array $data) {
                        $this->sendInvoiceByEmail($this->record->invoice, $data['email']);
                    }),
            ])
                ->label('Factura')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('success')
                ->button()
                ->visible(fn () => $this->record->invoice !== null),

            // ========== GENERATE POS INVOICE (when POS tickets exist, no POS invoice yet) ==========
            // POS/app sales don't flow through marketplace. Commission for those is charged
            // to the organizer via a separate invoice.
            Actions\Action::make('generate_invoice_pos')
                ->label('Generează factură POS')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(function () {
                    $posComm = $this->record->getPosCommissionTotal();
                    return 'Se va genera o factură către organizator cu valoarea comisioanelor biletelor vândute prin aplicație: ' . number_format($posComm, 2) . ' RON.';
                })
                ->visible(fn () => $this->record->posInvoice === null
                    && $this->record->getPosCommissionTotal() > 0
                    && !in_array($this->record->status, ['rejected', 'cancelled']))
                ->action(function () {
                    $this->generatePosInvoice();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('view_invoice_pos')
                    ->label('Vezi factura')
                    ->icon('heroicon-o-eye')
                    ->visible(fn () => $this->record->posInvoice !== null)
                    ->url(fn () => OrganizerInvoiceResource::getUrl('edit', ['record' => $this->record->posInvoice]))
                    ->openUrlInNewTab(),

                Actions\Action::make('download_invoice_pos')
                    ->label('Descarcă factura')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(function () {
                        $inv = $this->record->posInvoice;
                        if (!$inv) return false;
                        $meta = $inv->meta ?? [];
                        return !empty($meta['accounting']['pdf_url']) || !empty($meta['accounting_proforma']['pdf_url']);
                    })
                    ->url(function () {
                        $meta = $this->record->posInvoice?->meta ?? [];
                        return $meta['accounting']['pdf_url'] ?? $meta['accounting_proforma']['pdf_url'] ?? null;
                    }, shouldOpenInNewTab: true),

                Actions\Action::make('send_to_accounting_pos')
                    ->label(function () {
                        $providerLabel = $this->getAccountingProviderLabel();
                        return $providerLabel
                            ? "Trimite la {$providerLabel}"
                            : 'Trimite la contabilitate';
                    })
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        $providerLabel = $this->getAccountingProviderLabel() ?? 'software-ul de contabilitate';
                        $docLabel = $this->isAccountingDraftMode() ? 'DRAFT' : 'FACTURĂ FISCALĂ';
                        return "Factura POS va fi trimisa ca {$docLabel} in {$providerLabel}.";
                    })
                    ->visible(function () {
                        $invoice = $this->record->posInvoice;
                        if (!$invoice) return false;
                        $meta = $invoice->meta ?? [];
                        if (!empty($meta['accounting']['external_ref'])) return false;
                        $marketplace = $this->record->marketplaceClient;
                        if (!$marketplace) return false;
                        return app(AccountingService::class)->hasMarketplaceConnector($marketplace->id);
                    })
                    ->action(function () {
                        $this->sendInvoiceToAccounting($this->record->posInvoice);
                    }),

                Actions\Action::make('send_invoice_pos')
                    ->label('Trimite factura')
                    ->icon('heroicon-o-envelope')
                    ->visible(function () {
                        $inv = $this->record->posInvoice;
                        if (!$inv) return false;
                        $meta = $inv->meta ?? [];
                        return !empty($meta['accounting']['pdf_url']) || !empty($meta['accounting_proforma']['pdf_url']);
                    })
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Adresa email')
                            ->email()
                            ->required()
                            ->default(fn () => $this->record->organizer?->billing_email ?? $this->record->organizer?->email),
                    ])
                    ->action(function (array $data) {
                        $this->sendInvoiceByEmail($this->record->posInvoice, $data['email']);
                    }),
            ])
                ->label('Factură POS')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('warning')
                ->button()
                ->visible(fn () => $this->record->posInvoice !== null),
        ];
    }

    /**
     * Create an invoice billing the organizer for commission on POS/app ticket sales.
     * One line item per POS ticket type: qty = tickets sold, unit_price = commission
     * per ticket, amount = qty * unit_price. Description carries ticket name, rate %,
     * commission mode and event/venue context.
     */
    protected function generatePosInvoice(): void
    {
        $payout = $this->record;
        $organizer = $payout->organizer;
        $marketplace = $payout->marketplaceClient;

        if (!$organizer || !$marketplace) {
            Notification::make()->title('Lipsesc datele organizatorului/marketplace')->danger()->send();
            return;
        }

        $posTypeIds = $payout->getPosTicketTypeIds();
        if (empty($posTypeIds)) {
            Notification::make()->title('Nu există comisioane POS de facturat')->warning()->send();
            return;
        }
        $posSet = array_flip($posTypeIds);

        // Event/venue context for each line description
        $eventSuffix = $this->buildEventContextSuffix($payout->event);

        $items = [];
        $subtotal = 0.0;
        foreach ($payout->ticket_breakdown ?? [] as $item) {
            $ttId = $item['ticket_type_id'] ?? null;
            if (!$ttId || !isset($posSet[$ttId])) {
                continue;
            }

            $name = $item['ticket_type_name'] ?? $item['name'] ?? 'Bilet';
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
            if ($qty <= 0 || $commPerTicket <= 0) {
                continue;
            }

            $commMode = $item['commission_mode'] ?? null;
            $commRate = $item['commission_rate'] ?? null;

            $modeWord = match ($commMode) {
                'added_on_top' => 'peste preț',
                'included' => 'inclus',
                default => null,
            };
            $rateModePart = '';
            if ($commRate !== null && $modeWord) {
                $rateModePart = ' (' . $commRate . '% ' . $modeWord . ')';
            } elseif ($commRate !== null) {
                $rateModePart = ' (' . $commRate . '%)';
            } elseif ($modeWord) {
                $rateModePart = ' (' . $modeWord . ')';
            }

            $lineTotal = round($qty * $commPerTicket, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'description' => 'Comision servicii ticketing pentru bilet "' . $name . '"' . $rateModePart . $eventSuffix,
                'quantity' => $qty,
                'unit_price' => $commPerTicket,
                'amount' => $lineTotal,
            ];
        }

        if ($subtotal <= 0) {
            Notification::make()->title('Nu există comisioane POS de facturat')->warning()->send();
            return;
        }

        // POS commission is always charged to the organizer (the one that collected cash via app)
        $client = [
            'name' => $organizer->company_name ?? $organizer->name,
            'cui' => $organizer->cui ?? '',
            'address' => $organizer->address ?? '',
        ];

        $lastInvoice = Invoice::where('marketplace_client_id', $marketplace->id)
            ->orderByDesc('id')
            ->first();
        $nextNumber = $lastInvoice ? ((int) preg_replace('/\D/', '', $lastInvoice->number) + 1) : 1;
        $invoiceNumber = 'F-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        $vatRate = $marketplace->vat_payer ? 19 : 0;
        $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
        $total = $subtotal + $vatAmount;

        $invoice = Invoice::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_organizer_id' => $organizer->id,
            'marketplace_payout_id' => $payout->id,
            'number' => $invoiceNumber,
            'type' => 'fiscal',
            'description' => 'Factură POS pentru decont ' . $payout->reference,
            'issue_date' => now(),
            'period_start' => $payout->period_start,
            'period_end' => $payout->period_end,
            'due_date' => now()->addDays(30),
            'subtotal' => round($subtotal, 2),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => round($total, 2),
            'currency' => $payout->currency ?? 'RON',
            'status' => 'outstanding',
            'meta' => [
                'is_pos_commission' => true,
                'payout_reference' => $payout->reference,
                'issuer' => [
                    'name' => $marketplace->company_name ?? $marketplace->name,
                    'cui' => $marketplace->cui ?? '',
                    'address' => $marketplace->address ?? '',
                ],
                'client' => $client,
                'recipient_type' => 'organizer',
                'items' => $items,
            ],
        ]);

        Notification::make()->title('Factură POS generată: ' . $invoiceNumber)->success()->send();
        $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
    }

    /**
     * Build the " la eveniment NAME (DATE) VENUE, CITY" suffix used in invoice
     * line descriptions. Returns empty string when event is null.
     */
    protected function buildEventContextSuffix(?\App\Models\Event $event): string
    {
        if (!$event) {
            return '';
        }

        $title = $event->title;
        $eventName = is_array($title)
            ? ($title['ro'] ?? $title['en'] ?? (reset($title) ?: ''))
            : ($title ?? '');

        $eventDate = '';
        if ($event->event_date) {
            $eventDate = $event->event_date->format('d.m.Y');
        } elseif ($event->range_start_date) {
            $eventDate = $event->range_start_date->format('d.m.Y');
        }

        $venueName = '';
        $venueCity = '';
        if ($event->venue) {
            $vName = $event->venue->name;
            $venueName = is_array($vName)
                ? ($vName['ro'] ?? $vName['en'] ?? (reset($vName) ?: ''))
                : ($vName ?? '');
            $venueCity = $event->venue->city ?? '';
        }

        if (!$eventName) {
            return '';
        }

        $suffix = ' la eveniment ' . $eventName;
        if ($eventDate !== '') {
            $suffix .= ' (' . $eventDate . ')';
        }
        if ($venueName !== '' && $venueCity !== '') {
            $suffix .= ' ' . $venueName . ', ' . $venueCity;
        } elseif ($venueName !== '') {
            $suffix .= ' ' . $venueName;
        } elseif ($venueCity !== '') {
            $suffix .= ' ' . $venueCity;
        }

        return $suffix;
    }

    /**
     * Run decont generation via the observer and surface a truthful notification
     * based on whether a decont document was actually created.
     */
    protected function runDecontGeneration(bool $isRegeneration): void
    {
        $observer = new \App\Observers\MarketplacePayoutObserver();
        $method = new \ReflectionMethod($observer, 'generateDecont');
        $method->setAccessible(true);

        try {
            $method->invoke($observer, $this->record);
        } catch (\Throwable $e) {
            \Log::error('ViewPayout: Decont generation threw', [
                'payout_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);
            Notification::make()
                ->title('Eroare la generarea decontului')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        // Reload the relationship to see if a decont was actually created.
        $this->record->unsetRelation('decontDocument');
        $decont = $this->record->decontDocument;

        if ($decont) {
            Notification::make()
                ->title($isRegeneration ? 'Decont regenerat' : 'Decont generat')
                ->success()
                ->send();
            $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
            return;
        }

        // Decont generation silently failed — diagnose why so the admin knows.
        $reason = $this->diagnoseDecontFailure();
        Notification::make()
            ->title('Decontul NU a fost generat')
            ->body($reason)
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Replicate the precondition checks in MarketplacePayoutObserver::generateDecont()
     * to explain why it silently returned.
     */
    protected function diagnoseDecontFailure(): string
    {
        $payout = $this->record;
        $marketplace = $payout->marketplaceClient;
        $organizer = $payout->organizer;

        if (!$marketplace) {
            return 'Payout-ul nu are marketplace asociat.';
        }
        if (!$organizer) {
            return 'Payout-ul nu are organizator asociat.';
        }

        // Resolve commission mode the same way the observer does
        $commissionMode = $payout->commission_mode;
        if (!$commissionMode) {
            $modesFromTickets = collect($payout->ticket_breakdown ?? [])
                ->pluck('commission_mode')
                ->filter()
                ->unique()
                ->values();
            if ($modesFromTickets->count() === 1) {
                $commissionMode = $modesFromTickets->first();
            } elseif ($modesFromTickets->contains('added_on_top')) {
                $commissionMode = 'added_on_top';
            }
        }

        $templateType = $commissionMode === 'added_on_top' ? 'decont_ontop' : 'decont_inclus';

        $template = \App\Models\MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
            ->where('type', $templateType)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            $template = \App\Models\MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
                ->where('type', 'decont')
                ->where('is_active', true)
                ->first();
        }

        if (!$template) {
            return "Nu există template activ de tip \"{$templateType}\" sau \"decont\" pentru acest marketplace (commission_mode=" . ($commissionMode ?: 'necunoscut') . '). Mergi la Tax Templates și creează/activează unul.';
        }

        if ($template->by_proxy && !$organizer->proxy_admin_id) {
            return 'Template-ul de decont necesită un admin proxy (by_proxy=true), dar organizatorul nu are unul asignat. Setează proxy_admin_id pe organizator.';
        }

        return 'Generarea a eșuat fără un motiv cunoscut. Verifică storage/logs/laravel.log pentru detalii.';
    }

    /**
     * Send a document (decont) PDF by email.
     */
    protected function sendDocumentByEmail(OrganizerDocument $document, string $email, string $docType): void
    {
        try {
            $filePath = Storage::disk('public')->path($document->file_path);

            if (!file_exists($filePath)) {
                Notification::make()->title('Fișierul nu a fost găsit')->danger()->send();
                return;
            }

            $marketplace = $this->record->marketplaceClient;
            $transport = $marketplace?->getMailTransport();

            if (!$transport) {
                Notification::make()->title('Mail-ul nu este configurat')->body('Configurează SMTP/Brevo în Settings > Emails.')->danger()->send();
                return;
            }

            $payout = $this->record;
            $event = $payout->event;
            $organizer = $payout->organizer;

            // Event details
            $eventName = $event ? (is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? '') : ($event->title ?? '')) : '';
            $eventDate = $event?->event_date?->format('d.m.Y') ?? '';
            $venue = $event?->venue;
            $venueName = $venue ? (is_array($venue->name) ? ($venue->name['ro'] ?? $venue->name['en'] ?? '') : ($venue->name ?? '')) : '';
            $venueCity = $venue?->city ?? '';

            $locationPart = implode(', ', array_filter([$venueName, $venueCity]));
            $subject = "{$docType} - {$eventName} {$eventDate}" . ($locationPart ? " {$locationPart}" : '');

            $bodyHtml = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">'
                . '<p>Bună ziua,</p>'
                . '<p>Atașat găsiți <strong>' . e($docType) . '</strong> pentru decontul <strong>' . e($payout->reference) . '</strong>.</p>'
                . '<table style="border-collapse:collapse;margin:16px 0;font-size:13px;">'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Eveniment:</td><td style="padding:4px 0;font-weight:bold;">' . e($eventName) . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Data:</td><td style="padding:4px 0;">' . e($eventDate) . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Locație:</td><td style="padding:4px 0;">' . e($venueName) . ($venueCity ? ', ' . e($venueCity) : '') . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Sumă netă:</td><td style="padding:4px 0;font-weight:bold;">' . number_format((float) $payout->amount, 2) . ' ' . ($payout->currency ?? 'RON') . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Organizator:</td><td style="padding:4px 0;">' . e($organizer?->company_name ?? $organizer?->name ?? '') . '</td></tr>'
                . '</table>'
                . '<p>Cu respect,<br><strong>' . e($marketplace->name ?? 'Tixello') . '</strong></p>'
                . '</div>';

            $symfonyEmail = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $marketplace->getEmailFromAddress(),
                    $marketplace->getEmailFromName()
                ))
                ->to($email)
                ->subject($subject)
                ->html($bodyHtml)
                ->attachFromPath($filePath, $document->file_name, 'application/pdf');

            $sentMessage = $transport->send($symfonyEmail);

            // Log to marketplace email logs
            \App\Models\MarketplaceEmailLog::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer?->id,
                'marketplace_event_id' => null,
                'template_slug' => 'decont_send',
                'from_email' => $marketplace->getEmailFromAddress(),
                'from_name' => $marketplace->getEmailFromName(),
                'to_email' => $email,
                'to_name' => $organizer?->name ?? $email,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $sentMessage?->getMessageId() ?? null,
            ]);

            Notification::make()->title("{$docType} trimis la {$email}")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Eroare la trimitere')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Send an invoice by email (HTML content or PDF attachment).
     */
    protected function sendInvoiceByEmail(Invoice $invoice, string $email): void
    {
        try {
            $marketplace = $this->record->marketplaceClient;
            $transport = $marketplace?->getMailTransport();

            if (!$transport) {
                Notification::make()->title('Mail-ul nu este configurat')->danger()->send();
                return;
            }

            $payout = $this->record;
            $event = $payout->event;
            $organizer = $payout->organizer;

            $eventName = $event ? (is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? '') : ($event->title ?? '')) : '';
            $eventDate = $event?->event_date?->format('d.m.Y') ?? '';
            $venue = $event?->venue;
            $venueName = $venue ? (is_array($venue->name) ? ($venue->name['ro'] ?? $venue->name['en'] ?? '') : ($venue->name ?? '')) : '';
            $venueCity = $venue?->city ?? '';

            $subject = "Factură #{$invoice->number} — {$payout->reference}";

            $bodyHtml = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">'
                . '<p>Bună ziua,</p>'
                . '<p>Atașat găsiți <strong>factura #' . e($invoice->number) . '</strong> pentru decontul <strong>' . e($payout->reference) . '</strong>.</p>'
                . '<table style="border-collapse:collapse;margin:16px 0;font-size:13px;">'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Eveniment:</td><td style="padding:4px 0;font-weight:bold;">' . e($eventName) . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Data:</td><td style="padding:4px 0;">' . e($eventDate) . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Locație:</td><td style="padding:4px 0;">' . e($venueName) . ($venueCity ? ', ' . e($venueCity) : '') . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Sumă factură:</td><td style="padding:4px 0;font-weight:bold;">' . number_format((float) ($invoice->amount ?? $invoice->total ?? 0), 2) . ' ' . ($invoice->currency ?? 'RON') . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Scadență:</td><td style="padding:4px 0;">' . ($invoice->due_date?->format('d.m.Y') ?? '-') . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;color:#888;">Organizator:</td><td style="padding:4px 0;">' . e($organizer?->company_name ?? $organizer?->name ?? '') . '</td></tr>'
                . '</table>'
                . '<p>Cu respect,<br><strong>' . e($marketplace->name ?? 'Tixello') . '</strong></p>'
                . '</div>';

            $symfonyEmail = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $marketplace->getEmailFromAddress(),
                    $marketplace->getEmailFromName()
                ))
                ->to($email)
                ->subject($subject)
                ->html($bodyHtml);

            $sentMessage = $transport->send($symfonyEmail);

            // Log to marketplace email logs
            \App\Models\MarketplaceEmailLog::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer?->id,
                'marketplace_event_id' => null,
                'template_slug' => 'invoice_send',
                'from_email' => $marketplace->getEmailFromAddress(),
                'from_name' => $marketplace->getEmailFromName(),
                'to_email' => $email,
                'to_name' => $organizer?->name ?? $email,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $sentMessage?->getMessageId() ?? null,
            ]);

            Notification::make()->title("Factură trimisă la {$email}")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Eroare la trimitere')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Get the human-readable label for the configured accounting provider, or null if none.
     */
    protected function getAccountingProviderLabel(): ?string
    {
        $marketplace = $this->record->marketplaceClient;
        if (!$marketplace) return null;

        $connector = \Illuminate\Support\Facades\DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplace->id)
            ->where('status', 'connected')
            ->first();

        if (!$connector) return null;

        return match ($connector->provider) {
            'oblio' => 'Oblio.eu',
            'smartbill' => 'SmartBill',
            'fgo' => 'FGO',
            'keez' => 'Keez',
            default => ucfirst($connector->provider),
        };
    }

    /**
     * Check if the configured accounting connector has use_draft enabled.
     */
    protected function isAccountingDraftMode(): bool
    {
        $marketplace = $this->record->marketplaceClient;
        if (!$marketplace) return false;

        $connector = \Illuminate\Support\Facades\DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplace->id)
            ->where('status', 'connected')
            ->first();

        if (!$connector) return false;

        try {
            $auth = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($connector->auth), true);
            return (bool) ($auth['use_draft'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send the invoice to the configured accounting provider as a fiscal invoice
     * and store the resulting external ref + PDF URL on invoice.meta.
     */
    protected function sendInvoiceToAccounting(\App\Models\Invoice $invoice): void
    {
        $marketplace = $this->record->marketplaceClient;
        if (!$marketplace) {
            Notification::make()->danger()->title('Marketplace negăsit.')->send();
            return;
        }

        $meta = $invoice->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];

        // Auto-fill issuer from marketplace if missing
        $issuer['name'] = $issuer['name'] ?? ($marketplace->company_name ?? $marketplace->name);
        $issuer['cui'] = $issuer['cui'] ?? ($marketplace->cui ?? '');
        $issuer['reg_com'] = $issuer['reg_com'] ?? ($marketplace->reg_com ?? '');
        if (empty($issuer['address'])) {
            $issuer['address'] = implode(', ', array_filter([$marketplace->address, $marketplace->city, $marketplace->state]));
        }
        $issuer['bank_name'] = $issuer['bank_name'] ?? ($marketplace->bank_name ?? '');
        $issuer['iban'] = $issuer['iban'] ?? ($marketplace->bank_account ?? '');

        $errors = [];
        if (empty($client['name'])) $errors[] = 'Numele clientului lipsește.';
        if (empty($items)) $errors[] = 'Factura nu conține articole.';

        // For 'organizer' recipient type the CUI is required by Oblio; for 'general_client' allow empty
        $recipientType = $meta['recipient_type'] ?? 'organizer';
        if ($recipientType !== 'general_client' && empty($client['cui'])) {
            $errors[] = 'CUI-ul clientului lipsește.';
        }

        if (!empty($errors)) {
            Notification::make()->danger()
                ->title('Date incomplete pentru contabilitate')
                ->body(implode("\n", $errors))
                ->send();
            return;
        }

        // Read use_draft from connector
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

        $addressParts = array_map('trim', explode(',', $client['address'] ?? ''));

        // For general_client invoices, don't leak organizer email/country
        if ($recipientType === 'general_client') {
            $customerEmail = '';
            $customerCountry = '';
        } else {
            $customerEmail = $invoice->organizer?->billing_email ?? $invoice->organizer?->email ?? '';
            $customerCountry = 'Romania';
        }

        // Build a friendly description from the payout's event (single line: name, date, venue, city)
        $event = $this->record->event;
        $eventDescription = '';
        if ($event) {
            $title = $event->title;
            if (is_array($title)) {
                $title = $title['ro'] ?? $title['en'] ?? reset($title) ?: '';
            }
            $eventDate = '';
            if ($event->event_date) {
                $eventDate = $event->event_date->format('d.m.Y');
            } elseif ($event->range_start_date) {
                $eventDate = $event->range_start_date->format('d.m.Y');
            }
            $venueName = '';
            $venueCity = '';
            if ($event->venue) {
                $venueName = $event->venue->name;
                if (is_array($venueName)) {
                    $venueName = $venueName['ro'] ?? $venueName['en'] ?? reset($venueName) ?: '';
                }
                $venueCity = $event->venue->city ?? '';
            }
            $eventDescription = trim(implode(', ', array_filter([$title, $eventDate, $venueName, $venueCity])));
        }

        $invoiceData = [
            'seller_vat' => $issuer['cui'] ?? '',
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'number' => $invoice->number,
            'is_draft' => $useDraft,
            'doc_type' => 'invoice',
            'customer' => [
                'name' => $client['name'] ?? '',
                'vat_number' => $client['cui'] ?? '',
                'reg_number' => $client['reg_com'] ?? '',
                'email' => $customerEmail,
                'address' => [
                    'street' => $addressParts[0] ?? '',
                    'city' => $addressParts[1] ?? '',
                    'county' => $addressParts[2] ?? '',
                    'country' => $customerCountry,
                ],
            ],
            'lines' => array_map(function ($item) use ($eventDescription) {
                // Short product name + event details as description (avoids duplication)
                return [
                    'product_name' => 'Comision servicii ticketing',
                    'description' => $eventDescription ?: ($item['description'] ?? ''),
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                    'tax_rate' => 19,
                    'unit' => 'buc',
                ];
            }, $items),
        ];

        try {
            $service = app(AccountingService::class);
            $result = $service->issueMarketplaceInvoice($marketplace->id, $invoice->number, $invoiceData);

            if (!($result['success'] ?? false)) {
                Notification::make()->danger()->title('Eroare la trimitere')->send();
                return;
            }

            $meta['issuer'] = $issuer;
            $meta['accounting'] = [
                'external_ref' => $result['external_ref'],
                'invoice_number' => $result['invoice_number'],
                'doc_type' => 'invoice',
                'provider' => $connector->provider ?? 'unknown',
                'sent_at' => now()->toIso8601String(),
            ];

            // Try to fetch PDF immediately
            try {
                $pdfResult = $service->getMarketplaceInvoicePdf($marketplace->id, $result['external_ref'], 'invoice');
                if (!empty($pdfResult['pdf_url'])) {
                    $meta['accounting']['pdf_url'] = $pdfResult['pdf_url'];
                }
            } catch (\Throwable $e) {
                \Log::info("PDF not yet available: {$e->getMessage()}");
            }

            $invoice->update(['meta' => $meta]);

            $msg = "Nr. extern: {$result['invoice_number']}";
            if (!empty($meta['accounting']['pdf_url'])) {
                $msg .= ' — PDF disponibil.';
            }

            Notification::make()->success()->title('Factură trimisă')->body($msg)->send();
        } catch (\Throwable $e) {
            \Log::error("Accounting submission failed: {$e->getMessage()}");
            Notification::make()->danger()
                ->title('Eroare la trimiterea în contabilitate')
                ->body($e->getMessage())
                ->send();
        }
    }
}
