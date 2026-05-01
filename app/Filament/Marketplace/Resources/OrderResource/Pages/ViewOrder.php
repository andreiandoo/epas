<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
use App\Models\MarketplaceCustomer;
use App\Services\Marketplace\OrderTransferService;
use App\Services\PaymentRefundService;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refund_order')
                ->label('Rambursare')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, ['completed', 'paid', 'confirmed'])
                    && ($this->record->refund_status ?? 'none') !== 'full'
                    && $this->record->source !== 'external_import')
                ->modalHeading('Rambursare comandă')
                ->modalWidth('xl')
                ->form(fn () => $this->getRefundFormSchema())
                ->action(function (array $data): void {
                    $this->processRefundAction($data);
                }),

            Actions\Action::make('change_status')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => !in_array($this->record->status, ['refunded', 'partially_refunded']) && $this->record->source !== 'external_import')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('New Status')
                        ->options([
                            'pending' => 'În așteptare',
                            'paid' => 'Plătită',
                            'confirmed' => 'Confirmată',
                            'completed' => 'Finalizată',
                            'cancelled' => 'Anulată',
                            'refunded' => 'Rambursată',
                            'partially_refunded' => 'Rambursată parțial',
                            'failed' => 'Eșuată',
                            'expired' => 'Expirată',
                        ])
                        ->default(fn () => $this->record->status)
                        ->required(),
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason for change (optional)')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $oldStatus = $this->record->status;
                    $newStatus = $data['status'];

                    if ($oldStatus === $newStatus) {
                        Notification::make()
                            ->warning()
                            ->title('No change')
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
                        ->title('Status updated')
                        ->body("Order status changed from {$oldStatus} to {$newStatus}")
                        ->send();
                }),

            Actions\Action::make('transfer_customer')
                ->label('Transferă către alt client')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('gray')
                ->visible(fn () => $this->record->source !== 'external_import')
                ->modalHeading('Transferă comanda către alt client')
                ->modalDescription('Comanda, biletele și log-urile asociate vor fi mutate atomic. Total-urile ambilor clienți se recalculează automat. Operațiunea e auditată în activity log și în istoricul comenzii.')
                ->modalWidth('xl')
                ->form([
                    Forms\Components\Select::make('target_customer_id')
                        ->label('Client destinație')
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
                        ->helperText('Caută după email, nume sau telefon. Doar clienții marketplace-ului curent.'),
                    Forms\Components\Textarea::make('reason')
                        ->label('Motiv transfer')
                        ->required()
                        ->minLength(3)
                        ->rows(2)
                        ->placeholder('Ex: cumparator gresit la check-out, cerere client, etc.'),
                    Forms\Components\Toggle::make('rewrite_tickets')
                        ->label('Rescrie și attendee_name/email pe bilete')
                        ->helperText('Activează doar dacă noul client este și deținătorul biletelor. Altfel biletele rămân pe numele original.')
                        ->default(false),
                ])
                ->modalSubmitActionLabel('Transferă')
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $newCustomer = MarketplaceCustomer::find($data['target_customer_id']);

                    if (!$newCustomer) {
                        Notification::make()->danger()->title('Client destinație inexistent')->send();
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
                            ->title('Comandă transferată')
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
                            ->title('Transfer eșuat')
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
                ->modalHeading('Anulează ultimul transfer')
                ->modalDescription(fn () => $this->buildUndoTransferDescription())
                ->modalSubmitActionLabel('Anulează transferul')
                ->requiresConfirmation()
                ->action(function (): void {
                    $previous = $this->resolvePreviousCustomerForUndo();

                    if (!$previous) {
                        Notification::make()
                            ->danger()
                            ->title('Nu mai există client anterior')
                            ->body('Clientul precedent a fost șters între timp. Folosește transferul manual.')
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
                            ->title('Transfer anulat')
                            ->body('Comanda a revenit la ' . $previous->email)
                            ->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record->id]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Anulare eșuată')
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
     * Most recent entry in orders.metadata['transfers'][] or null.
     */
    protected function lastTransferEntry(): ?array
    {
        $transfers = $this->record->metadata['transfers'] ?? [];
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

        // Build per-ticket-type commission map from stored order data
        $commissionDetails = $order->meta['commission_details'] ?? [];
        $commissionByType = [];
        foreach ($commissionDetails as $cd) {
            $key = $cd['ticket_type'] ?? '';
            $commissionByType[$key] = [
                'amount_per_unit' => (float) ($cd['commission_amount'] ?? 0) / max(1, (int) ($cd['quantity'] ?? 1)),
                'mode' => $cd['commission_mode'] ?? 'included',
                'rate' => (float) ($cd['commission_rate'] ?? 0),
            ];
        }

        // Calculate per-ticket discount proportionally
        $orderSubtotal = (float) ($order->subtotal ?? 0);
        $orderDiscount = (float) ($order->discount_amount ?? 0);
        $discountRatio = ($orderSubtotal > 0 && $orderDiscount > 0) ? ($orderDiscount / $orderSubtotal) : 0;

        $ticketOptions = $tickets->mapWithKeys(function ($t) use ($discountRatio, $order) {
            $price = (float) ($t->price ?? 0);
            $discountedPrice = round($price * (1 - $discountRatio), 2);
            $label = "#{$t->code} — " . ($t->ticketType?->name ?? 'Bilet') . " — " . number_format($discountedPrice, 2) . ' ' . ($order->currency ?? 'RON');
            if ($discountRatio > 0) {
                $label .= " (original: " . number_format($price, 2) . ")";
            }
            return [$t->id => $label];
        })->toArray();

        return [
            Forms\Components\Select::make('refund_type')
                ->label('Tip rambursare')
                ->options([
                    'full' => 'Rambursare completă (toate biletele)',
                    'partial' => 'Rambursare parțială (selectează bilete)',
                ])
                ->default('full')
                ->required()
                ->live(),

            Forms\Components\CheckboxList::make('ticket_ids')
                ->label('Selectează biletele de rambursat')
                ->options($ticketOptions)
                ->visible(fn (Get $get) => $get('refund_type') === 'partial')
                ->required(fn (Get $get) => $get('refund_type') === 'partial')
                ->live(),

            Forms\Components\Toggle::make('refund_commission')
                ->label('Include comisionul în rambursare')
                ->helperText(function () use ($order, $commissionByType) {
                    $totalCommission = (float) ($order->commission_amount ?? 0);
                    if ($totalCommission <= 0) return 'Fără comision pe această comandă.';
                    $parts = [];
                    foreach ($commissionByType as $typeName => $cd) {
                        if ($cd['rate'] > 0) {
                            $parts[] = "{$typeName}: {$cd['rate']}%";
                        } elseif ($cd['amount_per_unit'] > 0) {
                            $parts[] = "{$typeName}: " . number_format($cd['amount_per_unit'], 2) . " lei fix";
                        }
                    }
                    return 'Comision total: ' . number_format($totalCommission, 2) . ' ' . ($order->currency ?? 'RON')
                        . ($parts ? ' (' . implode(', ', $parts) . ')' : '')
                        . '. Dacă dezactivat, comisionul va fi reținut.';
                })
                ->default(false)
                ->live(),

            Forms\Components\Placeholder::make('refund_summary')
                ->label('Sumar rambursare')
                ->content(function (Get $get) use ($order, $tickets, $discountRatio, $commissionByType) {
                    $refundType = $get('refund_type') ?? 'full';
                    $selectedIds = $get('ticket_ids') ?? [];
                    $refundCommission = (bool) $get('refund_commission');

                    $refundTickets = $refundType === 'full'
                        ? $tickets
                        : $tickets->whereIn('id', $selectedIds);

                    if ($refundTickets->isEmpty()) {
                        return new HtmlString('<p style="color:#94A3B8;">Selectează biletele pentru a vedea sumarul.</p>');
                    }

                    $rows = '';
                    $totalFace = 0;
                    $totalCommission = 0;
                    $totalRefund = 0;
                    $totalDiscount = 0;

                    foreach ($refundTickets as $ticket) {
                        $originalPrice = (float) ($ticket->price ?? 0);
                        $ticketDiscount = round($originalPrice * $discountRatio, 2);
                        $faceValue = round($originalPrice - $ticketDiscount, 2);

                        // Get exact commission from stored commission_details per ticket type
                        $typeName = $ticket->ticketType?->name ?? 'Bilet';
                        $cd = $commissionByType[$typeName] ?? null;
                        $commission = $cd ? round($cd['amount_per_unit'], 2) : 0;

                        $refundAmount = $refundCommission ? ($faceValue + $commission) : $faceValue;

                        $totalFace += $faceValue;
                        $totalCommission += $commission;
                        $totalRefund += $refundAmount;
                        $totalDiscount += $ticketDiscount;

                        $typeName = e($ticket->ticketType?->name ?? 'Bilet');
                        $code = e($ticket->code ?? '—');
                        $currency = $order->currency ?? 'RON';

                        $discountNote = $ticketDiscount > 0
                            ? " <span style='color:#F59E0B;font-size:11px;'>(-" . number_format($ticketDiscount, 2) . " discount)</span>"
                            : '';

                        $rows .= "<tr>
                            <td style='padding:4px 8px;font-size:13px;'>{$typeName} <span style='color:#64748B;'>#{$code}</span>{$discountNote}</td>
                            <td style='padding:4px 8px;text-align:right;font-size:13px;'>" . number_format($faceValue, 2) . "</td>
                            <td style='padding:4px 8px;text-align:right;font-size:13px;'>" . number_format($commission, 2) . "</td>
                            <td style='padding:4px 8px;text-align:right;font-size:13px;font-weight:600;'>" . number_format($refundAmount, 2) . "</td>
                        </tr>";
                    }

                    $commissionLabel = $refundCommission ? 'returnat' : 'reținut';
                    $currency = $order->currency ?? 'RON';

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
                                    <td style='padding:6px 8px;text-align:right;color:" . ($refundCommission ? '#10B981' : '#F59E0B') . ";'>" . number_format($totalCommission, 2) . " ({$commissionLabel})</td>
                                    <td style='padding:6px 8px;text-align:right;font-weight:700;font-size:15px;'>" . number_format($totalRefund, 2) . " {$currency}</td>
                                </tr>
                            </tfoot>
                        </table>
                    ");
                }),

            Forms\Components\Select::make('reason_category')
                ->label('Motiv rambursare')
                ->options([
                    'event_cancelled' => 'Eveniment anulat',
                    'event_postponed' => 'Eveniment amânat',
                    'personal_reason' => 'Motiv personal client',
                    'duplicate_purchase' => 'Achiziție duplicat',
                    'technical_issue' => 'Problemă tehnică',
                    'other' => 'Alt motiv (specificați mai jos)',
                ])
                ->live()
                ->nullable(),

            Forms\Components\Textarea::make('reason')
                ->label('Detalii suplimentare')
                ->rows(2)
                ->nullable()
                ->hidden(fn (Get $get) => $get('reason_category') !== 'other'),
        ];
    }

    protected function processRefundAction(array $data): void
    {
        $order = $this->record;
        $refundService = app(PaymentRefundService::class);
        $refundCommission = (bool) ($data['refund_commission'] ?? false);
        $reasonCategory = $data['reason_category'] ?? null;

        $reasonLabels = [
            'event_cancelled' => 'Eveniment anulat',
            'event_postponed' => 'Eveniment amânat',
            'personal_reason' => 'Motiv personal client',
            'duplicate_purchase' => 'Achiziție duplicat',
            'technical_issue' => 'Problemă tehnică',
        ];
        $reason = $reasonLabels[$reasonCategory] ?? $data['reason'] ?? 'Rambursare';

        if ($data['refund_type'] === 'full') {
            $result = $refundService->processOrderLevelRefund($order, $refundCommission, $reason, $reasonCategory);
        } else {
            $ticketIds = $data['ticket_ids'] ?? [];
            if (empty($ticketIds)) {
                Notification::make()->warning()->title('Selectează cel puțin un bilet.')->send();
                return;
            }
            $result = $refundService->processTicketLevelRefund($order, $ticketIds, $refundCommission, $reason, $reasonCategory);
        }

        if ($result->success) {
            Notification::make()
                ->success()
                ->title('Rambursare procesată cu succes')
                ->body($result->refundId ? "Referință: {$result->refundId}" : 'Rambursarea a fost înregistrată.')
                ->send();

            $this->refreshFormData(['status', 'refund_status', 'refunded_amount']);
        } elseif ($result->requiresManual) {
            Notification::make()
                ->warning()
                ->title('Rambursare înregistrată — procesare manuală necesară')
                ->body($result->error ?? 'Procesarea automată nu este disponibilă. Procesează manual din panoul Netopia.')
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->danger()
                ->title('Eroare la rambursare')
                ->body($result->error ?? 'A apărut o eroare neașteptată.')
                ->persistent()
                ->send();
        }
    }
}
