<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
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
                    && ($this->record->refund_status ?? 'none') !== 'full')
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
                ->visible(fn () => !in_array($this->record->status, ['refunded', 'partially_refunded']))
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

            Actions\EditAction::make(),
        ];
    }

    protected function getRefundFormSchema(): array
    {
        $order = $this->record;
        $tickets = $order->tickets()
            ->with('ticketType')
            ->where('refund_status', '!=', 'refunded')
            ->get();

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

        $commissionRate = (float) ($order->commission_rate ?? 0);

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
                ->helperText($commissionRate > 0
                    ? "Comision: {$commissionRate}%. Dacă dezactivat, comisionul va fi reținut."
                    : 'Fără comision configurat pe această comandă.')
                ->default(false)
                ->live(),

            Forms\Components\Placeholder::make('refund_summary')
                ->label('Sumar rambursare')
                ->content(function (Get $get) use ($order, $tickets, $discountRatio) {
                    $refundType = $get('refund_type') ?? 'full';
                    $selectedIds = $get('ticket_ids') ?? [];
                    $refundCommission = (bool) $get('refund_commission');

                    $refundTickets = $refundType === 'full'
                        ? $tickets
                        : $tickets->whereIn('id', $selectedIds);

                    if ($refundTickets->isEmpty()) {
                        return new HtmlString('<p style="color:#94A3B8;">Selectează biletele pentru a vedea sumarul.</p>');
                    }

                    // Use stored order amounts for accurate proportional calculation
                    $orderTotal = (float) ($order->total ?? 0);
                    $orderCommission = (float) ($order->commission_amount ?? 0);
                    $orderSubtotal = (float) ($order->subtotal ?? 0);
                    $allTicketCount = $tickets->count();
                    $refundTicketCount = $refundTickets->count();

                    // Proportional amounts based on ticket share
                    // For partial refund: proportional to selected tickets vs all tickets
                    $ticketShareRatio = $allTicketCount > 0 ? ($refundTicketCount / $allTicketCount) : 1;

                    $rows = '';
                    $totalFace = 0;
                    $totalCommission = 0;
                    $totalRefund = 0;
                    $totalDiscount = 0;

                    foreach ($refundTickets as $ticket) {
                        $originalPrice = (float) ($ticket->price ?? 0);
                        $ticketDiscount = round($originalPrice * $discountRatio, 2);
                        $priceAfterDiscount = round($originalPrice - $ticketDiscount, 2);
                        // Proportional commission from stored order commission
                        $commission = ($orderSubtotal > 0)
                            ? round($orderCommission * ($originalPrice / $orderSubtotal), 2)
                            : 0;
                        $faceValue = $priceAfterDiscount;
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
