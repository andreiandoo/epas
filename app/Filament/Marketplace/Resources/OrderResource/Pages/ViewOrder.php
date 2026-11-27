<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
use App\Models\MarketplaceCustomer;
use App\Services\Marketplace\OrderTransferService;
use App\Services\PaymentRefundService;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dispute_evidence_pdf')
                ->label(__('Dovadă pentru dispute (PDF)'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('marketplace.orders.dispute-evidence', ['order' => $this->record->id]), shouldOpenInNewTab: true),

            ...$this->companyInvoiceActions(),

            Actions\Action::make('retry_refund')
                ->label(__('Reîncearcă rambursarea automată'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->pendingRefund() !== null)
                ->requiresConfirmation()
                ->modalHeading(__('Reîncearcă rambursarea automată'))
                ->modalDescription(fn () => 'Trimite din nou cererea ' . $this->pendingRefund()?->reference . ' către procesatorul de plăți.')
                ->action(function (): void {
                    $refund = $this->pendingRefund();
                    if (!$refund) {
                        return;
                    }
                    $result = app(PaymentRefundService::class)->retryPendingRefund($refund);
                    if ($result->success) {
                        Notification::make()
                            ->success()
                            ->title(__('Rambursare procesată cu succes'))
                            ->body($result->refundId ? "Referință: {$result->refundId}" : null)
                            ->send();
                        $this->refreshFormData(['status', 'refund_status', 'refunded_amount']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title(__('Procesatorul a refuzat din nou'))
                            ->body(new HtmlString(e($result->error ?? '') . '<br><br>Fă rambursarea manual și apoi apasă „Finalizează rambursarea manuală”.'))
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('complete_manual_refund')
                ->label(__('Finalizează rambursarea manuală'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->pendingRefund() !== null)
                ->modalHeading(__('Finalizează rambursarea manuală'))
                ->modalDescription(fn () => new HtmlString(
                    'Folosește doar după ce banii au fost trimiși efectiv clientului.<br><br>'
                    . app(PaymentRefundService::class)->manualRefundInstructions($this->pendingRefund())
                ))
                ->modalWidth('xl')
                ->form([
                    Forms\Components\Select::make('method')
                        ->label(__('Cum ai rambursat'))
                        ->options([
                            'original_payment' => __('Din panoul procesatorului (Netopia / Stripe)'),
                            'bank_transfer' => __('Transfer bancar'),
                            'manual' => __('Altă metodă (numerar etc.)'),
                        ])
                        ->default('original_payment')
                        ->required()
                        ->live(),
                    Forms\Components\TextInput::make('reference')
                        ->label(__('Referința tranzacției'))
                        ->helperText(__('ID-ul rambursării din panoul procesatorului sau referința transferului bancar.'))
                        ->required(fn (Get $get) => $get('method') !== 'manual')
                        ->maxLength(190),
                    Forms\Components\Toggle::make('notify_customer')
                        ->label(__('Trimite clientului emailul de rambursare'))
                        ->default(true),
                    Forms\Components\Checkbox::make('confirm_sent')
                        ->label(__('Confirm că suma a fost trimisă clientului.'))
                        ->accepted()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $refund = $this->pendingRefund();
                    if (!$refund) {
                        return;
                    }
                    $result = app(PaymentRefundService::class)->completeManualRefund(
                        $refund,
                        $data['method'],
                        $data['reference'] ?? null,
                        auth()->id(),
                        (bool) ($data['notify_customer'] ?? true)
                    );
                    if ($result->success) {
                        Notification::make()
                            ->success()
                            ->title(__('Rambursare finalizată'))
                            ->body("Cererea {$refund->reference} a fost marcată rambursată.")
                            ->send();
                        $this->refreshFormData(['status', 'refund_status', 'refunded_amount']);
                    } else {
                        Notification::make()->danger()->title(__('Eroare'))->body($result->error)->send();
                    }
                }),

            Actions\Action::make('dismiss_external_refund_notice')
                ->label(__('Ignoră notificarea de rambursare'))
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn () => $this->externalRefundNotice() !== null)
                ->requiresConfirmation()
                ->modalDescription(__('Folosește doar dacă banii NU au fost returnați clientului (notificare greșită sau deja înregistrată).'))
                ->action(function (): void {
                    app(PaymentRefundService::class)->clearExternalRefundNotice($this->record);
                    Notification::make()->success()->title(__('Notificarea a fost ignorată'))->send();
                }),

            Actions\Action::make('refund_order')
                ->label(__('Rambursare'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, ['completed', 'paid', 'confirmed'])
                    && ($this->record->refund_status ?? 'none') !== 'full'
                    && $this->record->source !== 'external_import'
                    && $this->pendingRefund() === null)
                ->modalHeading(__('Rambursare comandă'))
                ->modalWidth('xl')
                ->form(fn () => $this->getRefundFormSchema())
                ->action(function (array $data): void {
                    $this->processRefundAction($data);
                }),

            Actions\Action::make('change_status')
                ->label(__('Change Status'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => !in_array($this->record->status, ['refunded', 'partially_refunded']) && $this->record->source !== 'external_import')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label(__('New Status'))
                        ->options([
                            'pending' => __('În așteptare'),
                            'paid' => __('Plătită'),
                            'confirmed' => __('Confirmată'),
                            'completed' => __('Finalizată'),
                            'cancelled' => __('Anulată'),
                            'failed' => __('Eșuată'),
                            'expired' => __('Expirată'),
                        ])
                        ->default(fn () => $this->record->status)
                        ->helperText(__('Pentru rambursări folosește butonul „Rambursare”: aici se schimbă doar statusul, banii nu pleacă.'))
                        ->required(),
                    Forms\Components\Textarea::make('reason')
                        ->label(__('Reason for change (optional)'))
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $oldStatus = $this->record->status;
                    $newStatus = $data['status'];

                    if ($oldStatus === $newStatus) {
                        Notification::make()
                            ->warning()
                            ->title(__('No change'))
                            ->body('Status is already ' . $newStatus)
                            ->send();
                        return;
                    }

                    $this->record->update(['status' => $newStatus]);

                    activity('tenant')
                        ->performedOn($this->record)
                        ->withProperties([
                            'marketplace_client_id' => $this->record->tenant_id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $data['reason'] ?? null,
                        ])
                        ->log("Order status changed from {$oldStatus} to {$newStatus}");

                    Notification::make()
                        ->success()
                        ->title(__('Status updated'))
                        ->body("Order status changed from {$oldStatus} to {$newStatus}")
                        ->send();
                }),

            Actions\Action::make('transfer_customer')
                ->label(__('Transferă către alt client'))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('gray')
                ->visible(fn () => $this->record->source !== 'external_import')
                ->modalHeading(__('Transferă comanda către alt client'))
                ->modalDescription(__('Comanda, biletele și log-urile asociate vor fi mutate atomic. Total-urile ambilor clienți se recalculează automat. Operațiunea e auditată în activity log și în istoricul comenzii.'))
                ->modalWidth('xl')
                ->form([
                    Forms\Components\Select::make('target_customer_id')
                        ->label(__('Client destinație'))
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            $clientId = $this->record->marketplace_client_id;
                            $term = '%' . mb_strtolower($search) . '%';

                            return MarketplaceCustomer::query()
                                ->where('marketplace_client_id', $clientId)
                                ->where(function ($q) use ($term) {
                                    $q->whereRaw('LOWER(email) LIKE ?', [$term])
                                      ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                                      ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                                      ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
                                })
                                ->where('id', '!=', $this->record->marketplace_customer_id)
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) . ' — ' . $c->email . ($c->phone ? ' (' . $c->phone . ')' : ''),
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value) {
                            $c = MarketplaceCustomer::find($value);
                            if (!$c) return null;
                            return trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) . ' — ' . $c->email;
                        })
                        ->helperText(__('Caută după email, nume sau telefon. Doar clienții marketplace-ului curent.')),
                    Forms\Components\Textarea::make('reason')
                        ->label(__('Motiv transfer'))
                        ->required()
                        ->minLength(3)
                        ->rows(2)
                        ->placeholder(__('Ex: cumparator gresit la check-out, cerere client, etc.')),
                    Forms\Components\Toggle::make('rewrite_tickets')
                        ->label(__('Rescrie și attendee_name/email pe bilete'))
                        ->helperText(__('Activează doar dacă noul client este și deținătorul biletelor. Altfel biletele rămân pe numele original.'))
                        ->default(false),
                ])
                ->modalSubmitActionLabel(__('Transferă'))
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $newCustomer = MarketplaceCustomer::find($data['target_customer_id']);

                    if (!$newCustomer) {
                        Notification::make()->danger()->title(__('Client destinație inexistent'))->send();
                        return;
                    }

                    try {
                        $result = app(OrderTransferService::class)->transfer(
                            $this->record,
                            $newCustomer,
                            $data['reason'],
                            auth()->id(),
                            (bool) ($data['rewrite_tickets'] ?? false),
                        );

                        Notification::make()
                            ->success()
                            ->title(__('Comandă transferată'))
                            ->body(
                                'Mutată către ' . $newCustomer->email
                                . '. Bilete actualizate: ' . $result['tickets_updated']
                                . ', cereri rambursare: ' . $result['refund_requests_updated']
                                . ', email logs: ' . $result['email_logs_updated']
                                . '.'
                            )
                            ->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record->id]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('Transfer eșuat'))
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('undo_last_transfer')
                ->label(fn () => $this->buildUndoTransferLabel())
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->canUndoLastTransfer())
                ->modalHeading(__('Anulează ultimul transfer'))
                ->modalDescription(fn () => $this->buildUndoTransferDescription())
                ->modalSubmitActionLabel(__('Anulează transferul'))
                ->requiresConfirmation()
                ->action(function (): void {
                    $previous = $this->resolvePreviousCustomerForUndo();

                    if (!$previous) {
                        Notification::make()
                            ->danger()
                            ->title(__('Nu mai există client anterior'))
                            ->body(__('Clientul precedent a fost șters între timp. Folosește transferul manual.'))
                            ->send();
                        return;
                    }

                    $lastTransfer = $this->lastTransferEntry();

                    try {
                        app(OrderTransferService::class)->transfer(
                            $this->record,
                            $previous,
                            'Undo of transfer at ' . ($lastTransfer['at'] ?? 'unknown'),
                            auth()->id(),
                            (bool) ($lastTransfer['rewrote_tickets'] ?? false),
                        );

                        Notification::make()
                            ->success()
                            ->title(__('Transfer anulat'))
                            ->body('Comanda a revenit la ' . $previous->email)
                            ->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record->id]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('Anulare eșuată'))
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\EditAction::make()
                ->visible(fn () => !in_array($this->record->status, ['refunded', 'partially_refunded']) && $this->record->source !== 'external_import'),
        ];
    }

    /**
     * Most recent entry in orders.meta['transfers'][] or null.
     */
    protected function lastTransferEntry(): ?array
    {
        $transfers = $this->record->meta['transfers'] ?? [];
        return end($transfers) ?: null;
    }

    /**
     * Resolve the customer the order came from before its last transfer,
     * if that customer still exists and shares the marketplace.
     */
    protected function resolvePreviousCustomerForUndo(): ?MarketplaceCustomer
    {
        $last = $this->lastTransferEntry();
        if (!$last || empty($last['from_customer_id'])) {
            return null;
        }

        return MarketplaceCustomer::query()
            ->where('id', $last['from_customer_id'])
            ->where('marketplace_client_id', $this->record->marketplace_client_id)
            ->first();
    }

    protected function canUndoLastTransfer(): bool
    {
        if ($this->record->source === 'external_import') {
            return false;
        }
        return $this->resolvePreviousCustomerForUndo() !== null;
    }

    protected function buildUndoTransferLabel(): string
    {
        $previous = $this->resolvePreviousCustomerForUndo();
        return $previous ? 'Anulează transferul (revino la ' . $previous->email . ')' : 'Anulează ultimul transfer';
    }

    protected function buildUndoTransferDescription(): string
    {
        $last = $this->lastTransferEntry();
        $previous = $this->resolvePreviousCustomerForUndo();

        if (!$last || !$previous) {
            return 'Nu există un transfer de anulat.';
        }

        $when = $last['at'] ?? 'unknown';
        return "Comanda va reveni la {$previous->email}. Total-urile ambilor clienți se vor recalcula. Operațiunea e auditată ca un nou transfer (motiv: 'Undo of transfer at {$when}').";
    }

    protected function getRefundFormSchema(): array
    {
        $order = $this->record;
        $tickets = $order->tickets()
            ->with('ticketType')
            ->where('refund_status', '!=', 'refunded')
            ->get();
        $order->setRelation('tickets', $tickets);

        // Detect commission-mode profile of this order:
        //  - 'included' : every non-refunded ticket has commission baked into price
        //                 → only "cu comision" refund is allowed (face_only would
        //                 return LESS than what the customer paid)
        //  - 'on_top'   : every non-refunded ticket has commission added on top
        //                 → operator chooses face_only OR with_commission
        //  - 'mixed'    : both modes present → operator must select tickets;
        //                 the radio choice applies only to on_top tickets;
        //                 included tickets are always refunded with commission
        $profile = $order->getRefundCommissionProfile();
        $modeByType = $order->getCommissionModeByTicketType();
        $fallbackMode = $order->getOrderFallbackCommissionMode();

        // Build per-ticket-type commission map from stored order data
        $commissionDetails = $order->meta['commission_details'] ?? [];
        $commissionByType = [];
        foreach ($commissionDetails as $cd) {
            $key = $cd['ticket_type'] ?? '';
            $mode = $cd['commission_mode'] ?? null;
            $commissionByType[$key] = [
                'amount_per_unit' => (float) ($cd['commission_amount'] ?? 0) / max(1, (int) ($cd['quantity'] ?? 1)),
                // Marketplace VAT paid on the on-top commission (0 when none)
                'vat_per_unit' => (float) ($cd['commission_vat'] ?? 0) / max(1, (int) ($cd['quantity'] ?? 1)),
                'mode' => in_array($mode, ['on_top', 'add_on_top', 'added_on_top'], true) ? 'on_top' : 'included',
                'rate' => (float) ($cd['commission_rate'] ?? 0),
            ];
        }

        // Calculate per-ticket discount proportionally
        $orderSubtotal = (float) ($order->subtotal ?? 0);
        $orderDiscount = (float) ($order->discount_amount ?? 0);
        $discountRatio = ($orderSubtotal > 0 && $orderDiscount > 0) ? ($orderDiscount / $orderSubtotal) : 0;

        $ticketOptions = $tickets->mapWithKeys(function ($t) use ($discountRatio, $order, $profile, $modeByType, $fallbackMode) {
            $price = (float) ($t->price ?? 0);
            $discountedPrice = round($price * (1 - $discountRatio), 2);
            $typeName = $t->ticketType?->name ?? 'Bilet';
            $label = "#{$t->code} — {$typeName} — " . number_format($discountedPrice, 2) . ' ' . ($order->currency ?? 'RON');
            if ($discountRatio > 0) {
                $label .= " (original: " . number_format($price, 2) . ")";
            }
            // On mixed orders, annotate each ticket with its own commission mode so
            // the operator can distinguish included tickets (locked to with-commission)
            // from on_top tickets (variant-driven).
            if ($profile === 'mixed') {
                $ticketMode = $modeByType[$typeName] ?? $fallbackMode;
                $label .= $ticketMode === 'included'
                    ? '  ⚫ COMISION INCLUS'
                    : '  ⚪ comision peste preț';
            }
            return [$t->id => $label];
        })->toArray();

        $schema = [];

        // Alert banner explaining refund constraints for the current order profile.
        if ($profile === 'included') {
            $schema[] = Forms\Components\Placeholder::make('refund_profile_alert')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div style="padding:12px 14px;border:1px solid #f59e0b;background:#fffbeb;border-radius:8px;color:#92400e;">'
                    . '<div style="font-weight:600;margin-bottom:4px;">⚠️ Comandă cu comision inclus în preț</div>'
                    . '<div style="font-size:12px;">Biletele au comisionul deja inclus în valoarea plătită de client. Se poate rambursa <strong>doar integral</strong> (cu comision) — altfel clientul ar primi mai puțin decât a plătit.</div>'
                    . '</div>'
                ));
        } elseif ($profile === 'mixed') {
            $schema[] = Forms\Components\Placeholder::make('refund_profile_alert')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div style="padding:12px 14px;border:1px solid #f59e0b;background:#fffbeb;border-radius:8px;color:#92400e;">'
                    . '<div style="font-weight:600;margin-bottom:4px;">⚠️ Comandă cu tipuri de comision diferite</div>'
                    . '<div style="font-size:12px;">Această comandă conține atât bilete cu <strong>comision inclus în preț</strong> (⚫) cât și bilete cu <strong>comision peste preț</strong> (⚪). Selectează individual biletele de rambursat. Biletele cu comision inclus se rambursează întotdeauna integral; pentru cele cu comision peste preț alegi tu varianta.</div>'
                    . '</div>'
                ));
        }

        // refund_type: 'full' = all non-refunded tickets, 'partial' = user picks.
        // For mixed orders we default to 'partial' so the operator sees each
        // ticket's mode annotation before confirming.
        $schema[] = Forms\Components\Select::make('refund_type')
            ->label(__('Tip rambursare'))
            ->options([
                'full' => __('Rambursare completă (toate biletele)'),
                'partial' => __('Rambursare parțială (selectează bilete)'),
            ])
            ->default($profile === 'mixed' ? 'partial' : 'full')
            ->required()
            ->live();

        $schema[] = Forms\Components\CheckboxList::make('ticket_ids')
            ->label(__('Selectează biletele de rambursat'))
            ->options($ticketOptions)
            ->helperText($order->tickets()->get(['id', 'meta'])->contains(fn ($t) => \App\Services\Marketplace\BuyXGetYOffer::offerGroup($t) !== null)
                ? 'Comanda conține o ofertă „Cumperi X primești Y”: oferta se rambursează doar întreagă. Dacă bifezi un bilet din ofertă, celelalte bilete ale ei (inclusiv Invitatia) se adaugă automat.'
                : null)
            ->visible(fn (Get $get) => $get('refund_type') === 'partial')
            ->required(fn (Get $get) => $get('refund_type') === 'partial')
            ->live();

        // refund_variant options depend on the profile.
        // included → only with_commission (radio locked to that option).
        // on_top / mixed → both variants; on mixed, the choice only applies to on_top tickets.
        $variantOptions = $profile === 'included'
            ? ['with_commission' => 'Bilete + comision (integral) — obligatoriu pentru bilete cu comision inclus în preț.']
            : [
                'face_only' => 'Doar valoare bilete — clientul primește doar prețul biletului. Comisionul rămâne la marketplace + organizator.',
                'with_commission' => 'Bilete + comision — clientul primește prețul biletului + comisionul. Asigurarea și taxa procesare card NU se rambursează.',
            ];

        $schema[] = Forms\Components\Radio::make('refund_variant')
            ->label(__('Tip rambursare'))
            ->options($variantOptions)
            ->default($profile === 'included' ? 'with_commission' : 'face_only')
            ->required()
            ->inline(false)
            ->helperText(function () use ($order, $commissionByType, $profile) {
                $totalCommission = (float) ($order->commission_amount ?? 0);
                $processingFee = (float) round(((int) ($order->processing_fee_cents ?? 0)) / 100, 2);
                $hasInsurance = (float) ($order->meta['insurance_amount'] ?? 0) > 0;
                $insurance = (float) ($order->meta['insurance_amount'] ?? 0);

                $notes = [];
                if ($profile === 'mixed') {
                    $notes[] = 'Se aplică doar pentru biletele cu comision peste preț. Biletele cu comision inclus se rambursează întotdeauna integral (cu comision).';
                }
                if ($totalCommission > 0) {
                    $parts = [];
                    foreach ($commissionByType as $typeName => $cd) {
                        if ($cd['rate'] > 0) {
                            $parts[] = "{$typeName}: {$cd['rate']}%";
                        } elseif ($cd['amount_per_unit'] > 0) {
                            $parts[] = "{$typeName}: " . number_format($cd['amount_per_unit'], 2) . " lei fix";
                        }
                    }
                    $notes[] = 'Comision total: ' . number_format($totalCommission, 2) . ' ' . ($order->currency ?? 'RON')
                        . ($parts ? ' (' . implode(', ', $parts) . ')' : '');
                }
                if ($hasInsurance) {
                    $notes[] = 'Asigurare ' . number_format($insurance, 2) . ' ' . ($order->currency ?? 'RON') . ' — NU se rambursează indiferent de varianta aleasă.';
                }
                if ($processingFee > 0) {
                    $notes[] = 'Taxă procesare card ' . number_format($processingFee, 2) . ' ' . ($order->currency ?? 'RON') . ' — pierdută către procesator (Stripe nu rambursează taxa proprie chiar și pe refund complet).';
                }
                return empty($notes)
                    ? 'Fără comision, asigurare sau taxe pe această comandă — ambele variante returnează aceeași sumă.'
                    : implode(' · ', $notes);
            })
            ->live();

        $schema[] = Forms\Components\Placeholder::make('refund_summary')
            ->label(__('Sumar rambursare'))
            ->content(function (Get $get) use ($order, $tickets, $discountRatio, $commissionByType, $modeByType, $fallbackMode, $profile) {
                $refundType = $get('refund_type') ?? 'full';
                $selectedIds = $get('ticket_ids') ?? [];
                $chosenRefundCommission = ($get('refund_variant') ?? ($profile === 'included' ? 'with_commission' : 'face_only')) === 'with_commission';

                $refundTickets = $refundType === 'full'
                    ? $tickets
                    : $tickets->whereIn('id', $selectedIds);

                if ($refundTickets->isEmpty()) {
                    return new HtmlString('<p style="color:#94A3B8;">Selectează biletele pentru a vedea sumarul.</p>');
                }

                $rows = '';
                $totalFace = 0;
                $totalCommissionReturned = 0;
                $totalCommissionKept = 0;
                $totalRefund = 0;

                foreach ($refundTickets as $ticket) {
                    $originalPrice = (float) ($ticket->price ?? 0);
                    $ticketDiscount = round($originalPrice * $discountRatio, 2);
                    $grossAfterDiscount = round($originalPrice - $ticketDiscount, 2);

                    $typeName = $ticket->ticketType?->name ?? 'Bilet';
                    $cd = $commissionByType[$typeName] ?? null;
                    $commission = $cd ? round($cd['amount_per_unit'], 2) : 0;
                    $ticketMode = $modeByType[$typeName] ?? $fallbackMode;
                    // For "included" tickets ticket.price is GROSS (customer-paid),
                    // so face_value (organizer share) = gross - commission and
                    // refund = face + commission = gross. For "on_top", ticket.price
                    // IS the face value and commission was charged on top.
                    $faceValue = $ticketMode === 'included'
                        ? round($grossAfterDiscount - $commission, 2)
                        : $grossAfterDiscount;
                    // Included tickets always refund with commission regardless of operator choice.
                    $effectiveRefundCommission = ($ticketMode === 'included') ? true : $chosenRefundCommission;
                    // On-top commission is shown and refunded with its VAT (as PaymentRefundService does)
                    if ($ticketMode !== 'included' && $cd) {
                        $commission = round($commission + round($cd['vat_per_unit'] ?? 0, 2), 2);
                    }
                    $refundAmount = $effectiveRefundCommission ? ($faceValue + $commission) : $faceValue;

                    $totalFace += $faceValue;
                    if ($effectiveRefundCommission) {
                        $totalCommissionReturned += $commission;
                    } else {
                        $totalCommissionKept += $commission;
                    }
                    $totalRefund += $refundAmount;

                    $typeNameEsc = e($typeName);
                    $code = e($ticket->code ?? '—');
                    $currency = $order->currency ?? 'RON';

                    $discountNote = $ticketDiscount > 0
                        ? " <span style='color:#F59E0B;font-size:11px;'>(-" . number_format($ticketDiscount, 2) . " discount)</span>"
                        : '';
                    $modeBadge = $ticketMode === 'included'
                        ? " <span style='color:#f59e0b;font-size:10px;font-weight:600;padding:1px 6px;border:1px solid #f59e0b;border-radius:10px;'>INCLUS</span>"
                        : "";

                    $rows .= "<tr>
                        <td style='padding:4px 8px;font-size:13px;'>{$typeNameEsc} <span style='color:#64748B;'>#{$code}</span>{$discountNote}{$modeBadge}</td>
                        <td style='padding:4px 8px;text-align:right;font-size:13px;'>" . number_format($faceValue, 2) . "</td>
                        <td style='padding:4px 8px;text-align:right;font-size:13px;color:" . ($effectiveRefundCommission ? '#10B981' : '#F59E0B') . ";'>" . number_format($commission, 2) . "</td>
                        <td style='padding:4px 8px;text-align:right;font-size:13px;font-weight:600;'>" . number_format($refundAmount, 2) . "</td>
                    </tr>";
                }

                $currency = $order->currency ?? 'RON';
                $commissionSummary = number_format($totalCommissionReturned, 2) . ' returnat';
                if ($totalCommissionKept > 0.005) {
                    $commissionSummary .= ' / ' . number_format($totalCommissionKept, 2) . ' reținut';
                }

                return new HtmlString("
                    <table style='width:100%;border-collapse:collapse;'>
                        <thead>
                            <tr style='border-bottom:1px solid #334155;'>
                                <th style='padding:4px 8px;text-align:left;font-size:12px;color:#94A3B8;'>Bilet</th>
                                <th style='padding:4px 8px;text-align:right;font-size:12px;color:#94A3B8;'>Valoare</th>
                                <th style='padding:4px 8px;text-align:right;font-size:12px;color:#94A3B8;'>Comision</th>
                                <th style='padding:4px 8px;text-align:right;font-size:12px;color:#94A3B8;'>Rambursare</th>
                            </tr>
                        </thead>
                        <tbody>{$rows}</tbody>
                        <tfoot>
                            <tr style='border-top:2px solid #334155;'>
                                <td style='padding:6px 8px;font-weight:600;'>Total</td>
                                <td style='padding:6px 8px;text-align:right;'>" . number_format($totalFace, 2) . "</td>
                                <td style='padding:6px 8px;text-align:right;font-size:12px;'>{$commissionSummary}</td>
                                <td style='padding:6px 8px;text-align:right;font-weight:700;font-size:15px;'>" . number_format($totalRefund, 2) . " {$currency}</td>
                            </tr>
                        </tfoot>
                    </table>
                ");
            });

        $schema[] = Forms\Components\Select::make('reason_category')
            ->label(__('Motiv rambursare'))
            ->options([
                'event_cancelled' => __('Eveniment anulat'),
                'event_postponed' => __('Eveniment amânat'),
                'personal_reason' => __('Motiv personal client'),
                'duplicate_purchase' => __('Achiziție duplicat'),
                'technical_issue' => __('Problemă tehnică'),
                'other' => __('Alt motiv (specificați mai jos)'),
            ])
            ->live()
            ->nullable();

        $schema[] = Forms\Components\Textarea::make('reason')
            ->label(__('Detalii suplimentare'))
            ->rows(2)
            ->nullable()
            ->hidden(fn (Get $get) => $get('reason_category') !== 'other');

        // Money already returned outside the platform (e.g. straight from the
        // Netopia panel): record it without calling the processor again.
        // The processor already reported money going back on this order: a new
        // processor refund could pay the customer twice, so only recording is allowed.
        $hasExternalNotice = $this->externalRefundNotice() !== null;

        $schema[] = Forms\Components\Toggle::make('already_refunded')
            ->label(__('Banii au fost deja returnați în afara platformei (ex. din panoul Netopia) — doar înregistrează'))
            ->helperText($hasExternalNotice
                ? 'Procesatorul a raportat deja o rambursare pe această comandă, așa că aici se poate doar înregistra. Dacă trebuie trimiși bani în plus prin procesator, apasă întâi „Ignoră notificarea de rambursare”.'
                : 'Procesatorul NU mai este apelat. Biletele se anulează, stocul se eliberează și decontul ține cont de rambursare. Suma aleasă mai sus trebuie să fie cea returnată efectiv.')
            ->default($hasExternalNotice)
            ->disabled($hasExternalNotice)
            ->dehydrated()
            ->live();

        $schema[] = Forms\Components\Select::make('external_method')
            ->label(__('Cum s-au returnat banii'))
            ->options([
                'original_payment' => __('Din panoul procesatorului (Netopia / Stripe)'),
                'bank_transfer' => __('Transfer bancar'),
                'manual' => __('Altă metodă (numerar etc.)'),
            ])
            ->default('original_payment')
            ->visible(fn (Get $get) => (bool) $get('already_refunded'))
            ->required(fn (Get $get) => (bool) $get('already_refunded'))
            ->live();

        $schema[] = Forms\Components\TextInput::make('external_reference')
            ->label(__('Referința tranzacției'))
            ->helperText(__('ID-ul rambursării din panoul procesatorului sau referința transferului.'))
            ->maxLength(190)
            ->visible(fn (Get $get) => (bool) $get('already_refunded'))
            ->required(fn (Get $get) => (bool) $get('already_refunded') && $get('external_method') !== 'manual');

        $schema[] = Forms\Components\Toggle::make('external_notify')
            ->label(__('Trimite clientului emailul de rambursare'))
            ->default(false)
            ->visible(fn (Get $get) => (bool) $get('already_refunded'));

        $schema[] = Forms\Components\Checkbox::make('external_confirm')
            ->label(__('Confirm că suma a fost deja trimisă clientului.'))
            ->visible(fn (Get $get) => (bool) $get('already_refunded'))
            ->accepted(fn (Get $get) => (bool) $get('already_refunded'))
            ->required(fn (Get $get) => (bool) $get('already_refunded'));

        return $schema;
    }

    /**
     * Factură pe persoană juridică: cerere din partea operatorului, starea
     * facturilor, reluarea emiterii, încărcarea facturii organizatorului în
     * numele lui și retrimiterea către client.
     */
    protected function companyInvoiceActions(): array
    {
        $service = app(\App\Services\Invoicing\CompanyInvoiceService::class);
        $rows = fn () => \App\Models\OrderCompanyInvoice::with(['organizer:id,name,company_name', 'invoice:id,number,meta'])
            ->where('order_id', $this->record->id)->get();
        $enabled = fn () => $service->isEnabled($this->record->marketplaceClient) && $this->record->source === 'marketplace';
        $actor = fn () => 'admin:' . (auth('marketplace_admin')->id() ?? auth()->id());

        return [
            Actions\ActionGroup::make([
                Actions\Action::make('company_invoice_request')
                    ->label(__('Facturează pe persoană juridică'))
                    ->icon('heroicon-o-building-office')
                    ->visible(fn () => $enabled() && $rows()->isEmpty())
                    ->modalHeading(__('Factură pe persoană juridică'))
                    ->modalDescription(function () use ($service) {
                        $reason = $service->ineligibleReason($this->record);

                        return $reason
                            ? new HtmlString('<span style="color:#b91c1c;">' . e($reason) . '</span>')
                            : 'Introdu CUI-ul și caută firma la ANAF sau completează datele manual. Platforma emite imediat factura pentru comision + TVA și servicii; organizatorul primește cererea pentru factura biletelor.';
                    })
                    ->fillForm(function (): array {
                        $saved = $this->record->meta['company_billing']
                            ?? data_get(MarketplaceCustomer::find($this->record->marketplace_customer_id)?->settings, 'company_billing', []);

                        return array_intersect_key((array) $saved, array_flip(['cui', 'name', 'reg_com', 'address', 'city', 'county', 'vat_payer']));
                    })
                    ->form([
                        Forms\Components\Hidden::make('verified_cui'),
                        Forms\Components\TextInput::make('cui')
                            ->label(__('CUI firmă'))
                            ->placeholder(__('RO12345678'))
                            ->helperText(__('Apasă lupa pentru datele de la ANAF. Dacă firma nu e găsită, completează manual câmpurile de mai jos.'))
                            ->required()
                            ->maxLength(20)
                            ->suffixAction(
                                Actions\Action::make('anaf_lookup')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->tooltip(__('Caută la ANAF'))
                                    ->action(function (Get $get, Set $set) use ($service): void {
                                        $set('verified_cui', null);
                                        try {
                                            $company = $service->lookupCompany((string) $get('cui'));
                                        } catch (\InvalidArgumentException $e) {
                                            Notification::make()->warning()->title(__('Firma nu a putut fi preluată de la ANAF'))->body($e->getMessage() . ' Poți completa datele manual.')->send();
                                            return;
                                        }
                                        foreach (['name', 'reg_com', 'address', 'city', 'county', 'vat_payer'] as $field) {
                                            $set($field, $company[$field]);
                                        }
                                        $set('verified_cui', $company['cui']);
                                        Notification::make()->success()->title(__('Date preluate de la ANAF'))->body($company['name'])->send();
                                    })
                            ),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Denumire firmă'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('reg_com')
                            ->label(__('Nr. Registrul Comerțului'))
                            ->placeholder(__('J40/1234/2020'))
                            ->maxLength(50),
                        Forms\Components\TextInput::make('address')
                            ->label(__('Adresă'))
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('city')
                            ->label(__('Localitate'))
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('county')
                            ->label(__('Județ'))
                            ->required()
                            ->maxLength(120),
                        Forms\Components\Toggle::make('vat_payer')
                            ->label(__('Plătitoare de TVA')),
                    ])
                    ->modalSubmitActionLabel(__('Emite facturile'))
                    ->action(function (array $data) use ($service, $actor): void {
                        try {
                            $company = $service->companyFromOperator($data);
                            $created = $service->requestForPaidOrder($this->record, $company['cui'], 'admin', $actor(), $company);
                            Notification::make()->success()
                                ->title(__('Cererea de factură a fost înregistrată'))
                                ->body(count($created) . ' factur' . (count($created) === 1 ? 'ă' : 'i') . ' de emis. Factura platformei pleacă automat la client în câteva minute.')
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->danger()->title(__('Nu se poate factura pe firmă'))->body($e->getMessage())->send();
                        }
                    }),

                Actions\Action::make('company_invoice_status')
                    ->label(__('Facturi pe firmă'))
                    ->icon('heroicon-o-document-text')
                    ->visible(fn () => $rows()->isNotEmpty())
                    ->modalHeading(__('Facturi pe persoană juridică'))
                    ->modalContent(fn () => new HtmlString($this->companyInvoiceTable($rows())))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Închide')),

                Actions\Action::make('company_invoice_retry')
                    ->label(__('Reia emiterea facturii platformei'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => $rows()->contains(fn ($r) => $r->isMarketplace() && !$r->sent_to_customer_at))
                    ->requiresConfirmation()
                    ->action(function () use ($service, $rows): void {
                        $row = $rows()->first(fn ($r) => $r->isMarketplace());
                        try {
                            $service->issueMarketplaceInvoice($row);
                            $row->update(['last_error' => null]);
                            Notification::make()->success()->title(__('Factura a fost emisă și trimisă clientului'))->send();
                        } catch (\Throwable $e) {
                            $row->update(['last_error' => mb_substr($e->getMessage(), 0, 1000)]);
                            Notification::make()->danger()->title(__('Emiterea a eșuat'))->body($e->getMessage())->send();
                        }
                    }),

                Actions\Action::make('company_invoice_upload')
                    ->label(__('Încarcă factura organizatorului'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn () => $rows()->contains(fn ($r) => !$r->isMarketplace() && in_array($r->status, ['requested', 'uploaded'], true)))
                    ->form(fn () => [
                        Forms\Components\Select::make('row_id')
                            ->label(__('Organizator'))
                            ->options($rows()->filter(fn ($r) => !$r->isMarketplace() && in_array($r->status, ['requested', 'uploaded'], true))
                                ->mapWithKeys(fn ($r) => [$r->id => ($r->organizer?->company_name ?: $r->organizer?->name ?: 'Organizator') . ' - ' . number_format((float) $r->amount, 2, ',', '.') . ' ' . $r->currency . ' (' . $r->statusLabel() . ')']))
                            ->required(),
                        Forms\Components\FileUpload::make('file')
                            ->label(__('Factura (PDF)'))
                            ->disk('local')
                            ->directory('company-invoices/tmp')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('file_name')
                            ->required(),
                    ])
                    ->modalDescription(__('Factura pleacă imediat pe email la client, ca și cum ar fi încărcat-o organizatorul.'))
                    ->action(function (array $data) use ($service, $actor): void {
                        $row = \App\Models\OrderCompanyInvoice::where('order_id', $this->record->id)->find($data['row_id']);
                        $disk = \Illuminate\Support\Facades\Storage::disk('local');
                        if (!$row || !$disk->exists($data['file'])) {
                            Notification::make()->danger()->title(__('Fișierul nu a fost găsit'))->send();
                            return;
                        }
                        $file = new \Illuminate\Http\UploadedFile($disk->path($data['file']), $data['file_name'] ?? 'factura.pdf', 'application/pdf', null, true);
                        try {
                            $service->storeOrganizerUpload($row, $file, $actor());
                            Notification::make()->success()->title(__('Factura a fost trimisă clientului'))->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title(__('Factura a fost salvată, dar trimiterea a eșuat'))->body($e->getMessage())->send();
                        } finally {
                            $disk->delete($data['file']);
                        }
                    }),

                Actions\Action::make('company_invoice_resend')
                    ->label(__('Retrimite facturile clientului'))
                    ->icon('heroicon-o-envelope')
                    ->visible(fn () => $rows()->contains(fn ($r) => $r->hasDocument()))
                    ->requiresConfirmation()
                    ->modalDescription(fn () => 'Facturile disponibile pleacă din nou la ' . $this->record->customer_email . '.')
                    ->action(function () use ($service, $rows): void {
                        $sent = 0;
                        $errors = [];
                        foreach ($rows()->filter(fn ($r) => $r->hasDocument()) as $row) {
                            try {
                                $service->sendToCustomer($row);
                                $sent++;
                            } catch (\Throwable $e) {
                                $errors[] = $e->getMessage();
                            }
                        }
                        Notification::make()->{$errors ? 'warning' : 'success'}()
                            ->title($sent . ' factur' . ($sent === 1 ? 'ă trimisă' : 'i trimise'))
                            ->body($errors ? implode("\n", $errors) : null)
                            ->send();
                    }),
            ])
                ->label(__('Factură pe firmă'))
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->button()
                ->visible(fn () => $enabled() || $rows()->isNotEmpty()),
        ];
    }

    protected function companyInvoiceTable(\Illuminate\Support\Collection $rows): string
    {
        $company = $rows->first()?->company ?? [];
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        $html = '<div style="font-size:13px;line-height:1.5;">'
            . '<p><strong>' . e($company['name'] ?? '') . '</strong> · CUI ' . e(app(\App\Services\Invoicing\CompanyInvoiceService::class)->fiscalCode($company))
            . ' · ' . e($company['reg_com'] ?? '') . '<br><span style="color:#6b7280;">' . e($company['address'] ?? '') . '</span></p>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:10px;">'
            . '<tr style="text-align:left;border-bottom:1px solid #e5e7eb;"><th style="padding:6px;">Emitent</th><th style="padding:6px;">Stare</th><th style="padding:6px;text-align:right;">Sumă</th><th style="padding:6px;">Trimisă clientului</th></tr>';
        foreach ($rows as $r) {
            $issuer = $r->isMarketplace()
                ? 'Platforma' . ($r->invoice ? ' · ' . e($r->invoice->meta['accounting']['invoice_number'] ?? $r->invoice->number) : '')
                : e($r->organizer?->company_name ?: $r->organizer?->name ?: 'Organizator');
            $html .= '<tr style="border-bottom:1px solid #f3f4f6;">'
                . '<td style="padding:6px;">' . $issuer . '</td>'
                . '<td style="padding:6px;">' . e($r->statusLabel())
                . ($r->last_error ? '<br><span style="color:#b91c1c;font-size:12px;">' . e($r->last_error) . '</span>' : '') . '</td>'
                . '<td style="padding:6px;text-align:right;">' . $fmt($r->amount) . ' ' . e($r->currency)
                . ($r->vat_amount > 0 ? '<br><span style="color:#6b7280;font-size:12px;">din care TVA ' . $fmt($r->vat_amount) . '</span>' : '') . '</td>'
                . '<td style="padding:6px;">' . ($r->sent_to_customer_at ? $r->sent_to_customer_at->format('d.m.Y H:i') : '—') . '</td>'
                . '</tr>';
        }

        return $html . '</table></div>';
    }

    protected function pendingRefund(): ?\App\Models\MarketplaceRefundRequest
    {
        return app(PaymentRefundService::class)->pendingManualRefundFor($this->record);
    }

    protected function externalRefundNotice(): ?array
    {
        $notice = (is_array($this->record->meta) ? $this->record->meta : [])[PaymentRefundService::EXTERNAL_REFUND_NOTICE] ?? null;
        return is_array($notice) ? $notice : null;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $refund = $this->pendingRefund();
        $notice = $this->externalRefundNotice();

        if (!$refund && $notice) {
            $amount = $notice['amount'] !== null
                ? number_format((float) $notice['amount'], 2) . ' ' . ($this->record->currency ?? 'RON')
                : 'sumă necunoscută';
            $when = !empty($notice['received_at']) ? \Carbon\Carbon::parse($notice['received_at'])->timezone('Europe/Bucharest')->format('d.m.Y H:i') : '';

            return new HtmlString(
                '<div style="margin-top:8px;padding:14px 16px;border:1px solid #f59e0b;background:#fffbeb;border-radius:10px;color:#78350f;">'
                . '<div style="font-weight:700;margin-bottom:4px;">' . e(ucfirst($notice['processor'] ?? 'Procesatorul')) . ' a raportat o rambursare făcută în afara platformei: ' . e($amount) . '</div>'
                . '<div style="font-size:12px;">' . e($when) . ($notice['reference'] ? ' · referință <code>' . e($notice['reference']) . '</code>' : '')
                . ($notice['reason'] ? ' · ' . e($notice['reason']) : '') . '</div>'
                . '<div style="font-size:12px;margin-top:6px;">Banii au plecat deja. Apasă <strong>„Rambursare”</strong>, alege biletele și suma care corespund și bifează <strong>„Banii au fost deja returnați în afara platformei”</strong>. Dacă notificarea e greșită, folosește „Ignoră notificarea de rambursare”.</div>'
                . '</div>'
            );
        }

        if (!$refund) {
            return parent::getSubheading();
        }

        $amount = number_format((float) $refund->approved_amount, 2) . ' ' . ($refund->currency ?? 'RON');
        $error = preg_replace('/^(Rambursarea automată a fost refuzată de procesator|Automatic refund requires manual processing): /u','', strtok((string) $refund->admin_notes, "\n"));

        return new HtmlString(
            '<div style="margin-top:8px;padding:14px 16px;border:1px solid #ef4444;background:#fef2f2;border-radius:10px;color:#7f1d1d;">'
            . '<div style="font-weight:700;margin-bottom:4px;">Rambursare neterminată: ' . e($refund->reference) . ' — ' . e($amount) . '</div>'
            . '<div style="font-size:12px;margin-bottom:8px;">Procesatorul de plăți a refuzat rambursarea, banii NU au ajuns la client. Motiv: <code>' . e($error) . '</code></div>'
            . app(PaymentRefundService::class)->manualRefundInstructions($refund)
            . '</div>'
        );
    }

    protected function processRefundAction(array $data): void
    {
        $order = $this->record;
        $refundService = app(PaymentRefundService::class);
        $refundCommission = ($data['refund_variant'] ?? 'face_only') === 'with_commission';
        $reasonCategory = $data['reason_category'] ?? null;

        $reasonLabels = [
            'event_cancelled' => 'Eveniment anulat',
            'event_postponed' => 'Eveniment amânat',
            'personal_reason' => 'Motiv personal client',
            'duplicate_purchase' => 'Achiziție duplicat',
            'technical_issue' => 'Problemă tehnică',
        ];
        $reason = $reasonLabels[$reasonCategory] ?? $data['reason'] ?? 'Rambursare';

        $external = !empty($data['already_refunded'])
            ? [
                'method' => $data['external_method'] ?? 'original_payment',
                'reference' => trim((string) ($data['external_reference'] ?? '')) ?: null,
                'processed_by' => auth()->id(),
                'notify' => (bool) ($data['external_notify'] ?? false),
                'source' => 'admin',
            ]
            : null;

        if ($data['refund_type'] === 'full') {
            $result = $refundService->processOrderLevelRefund($order, $refundCommission, $reason, $reasonCategory, $external);
        } else {
            $ticketIds = $data['ticket_ids'] ?? [];
            if (empty($ticketIds)) {
                Notification::make()->warning()->title(__('Selectează cel puțin un bilet.'))->send();
                return;
            }
            $result = $refundService->processTicketLevelRefund($order, $ticketIds, $refundCommission, $reason, $reasonCategory, $external);
        }

        if ($result->success) {
            Notification::make()
                ->success()
                ->title($external ? 'Rambursare externă înregistrată' : 'Rambursare procesată cu succes')
                ->body($result->refundId ? "Referință: {$result->refundId}" : 'Rambursarea a fost înregistrată.')
                ->send();

            $this->refreshFormData(['status', 'refund_status', 'refunded_amount']);
        } elseif ($result->requiresManual) {
            Notification::make()
                ->danger()
                ->title(__('Procesatorul a refuzat rambursarea — banii NU au plecat'))
                ->body(new HtmlString(
                    e($result->error ?? '') . '<br><br>Biletele sunt încă valide. Urmează pașii din caseta roșie de sus a paginii.'
                ))
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->danger()
                ->title(__('Eroare la rambursare'))
                ->body($result->error ?? 'A apărut o eroare neașteptată.')
                ->persistent()
                ->send();
        }
    }
}
