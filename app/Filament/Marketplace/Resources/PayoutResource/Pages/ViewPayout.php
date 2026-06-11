<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Models\Invoice;
use App\Models\OrganizerDocument;
use App\Services\Accounting\AccountingService;
use App\Services\EFactura\EFacturaService;
use App\Support\MarketplaceTz;
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
            // Status workflow (approve / process / complete / reject) + Admin
            // Note + Șterge decont all live in the sidebar "Acțiuni" section
            // in the infolist. Serie-decont edit moved to an inline hintAction
            // on the TextEntry itself.

            // Recalcul snapshot din SalesBreakdownService — util pentru deconturile
            // create inainte de refactor (snapshot pe baza prețului catalog) sau
            // dupa modificari de preturi pe bilete. Doar status-uri editabile.
            // Recalculează și `amount` / `gross_amount` / `commission_amount`
            // la nivel de payout + ajustează balanța rezervată a organizatorului
            // ca să nu rămână bani blocați degeaba.
            // Operator-driven edit of which tickets this payout covers.
            // Use when the saved ticket_breakdown doesn't match reality
            // (legacy payouts created before the breakdown-from-repeater
            // fix), or when the admin wants to redistribute the amount
            // across different ticket types. Saves a clean breakdown
            // built from the operator's exact selection — same logic
            // as the manual-decont modal.
            Actions\Action::make('edit_ticket_selection')
                ->label('Editează bilete decontate')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(fn () => in_array($this->record->status, ['pending', 'approved', 'processing'])
                    && !empty($this->record->event_id))
                ->modalHeading('Editează biletele incluse în decont')
                ->modalDescription(new \Illuminate\Support\HtmlString(
                    'Setezi exact ce bilete intră în acest decont. Sumele payout-ului se recalculează din bilete.<br>'
                    . '<strong>Live preview</strong>: pe măsură ce schimbi qty-uri vezi totalul jos. '
                    . 'Sau tastează o sumă în "Sumă netă dorită" și apasă "Distribuie proporțional" ca să scalezi automat toate cantitățile.'
                    // Inline-actions hack: flatten the Filament Repeater item
                    // so the auto-rendered delete icon (normally stacked above
                    // the schema as a header bar) sits on the same row, to the
                    // right of the qty input. The .ep-inline-actions class is
                    // applied to the Repeater via extraAttributes below.
                    . '<style>'
                    . '.ep-inline-actions .fi-fo-repeater-item.fi-fo-repeater-item-has-header { display: flex; flex-direction: row; align-items: center; gap: 0.5rem; }'
                    . '.ep-inline-actions .fi-fo-repeater-item.fi-fo-repeater-item-has-header > .fi-fo-repeater-item-content { flex: 1; padding: 0.5rem 0.75rem; }'
                    . '.ep-inline-actions .fi-fo-repeater-item.fi-fo-repeater-item-has-header > .fi-fo-repeater-item-header { order: 2; flex: 0 0 auto; padding: 0 0.75rem; background: transparent !important; border: none !important; }'
                    . '.ep-inline-actions .fi-fo-repeater-item-header-end-actions { margin: 0; }'
                    . '</style>'
                ))
                ->modalWidth('5xl')
                ->fillForm(function () {
                    // Filter POS rows out of the pre-fill — they belong to the
                    // organizer's cash POS app and never to a marketplace
                    // decont. The saved breakdown may contain them as a
                    // legacy artefact from the pre-POS-filter write code.
                    $posTypeIdSet = array_flip($this->record->getPosTicketTypeIds() ?: []);

                    $rows = collect($this->record->ticket_breakdown ?? [])
                        ->filter(fn ($r) => !isset($posTypeIdSet[$r['ticket_type_id'] ?? null]))
                        ->map(fn ($r) => [
                            'ticket_type_id' => (int) ($r['ticket_type_id'] ?? 0),
                            'ticket_type_name' => (string) ($r['ticket_type_name'] ?? ''),
                            'qty' => (int) ($r['qty'] ?? $r['quantity'] ?? 0),
                            'unit_price' => (float) ($r['price'] ?? $r['unit_price'] ?? 0),
                            'commission_per_ticket' => (float) ($r['commission_per_ticket'] ?? 0),
                            'commission_mode' => (string) ($r['commission_mode'] ?? 'included'),
                            'discount' => (float) ($r['discount'] ?? 0),
                            // Preserve tier breakdown so a re-save without touching
                            // the row keeps the PDF's "50lei*2+40lei*2" detail.
                            'tiers' => is_array($r['tiers'] ?? null) ? $r['tiers'] : [],
                        ])
                        ->values()
                        ->all();

                    // Refunds already linked to this payout — pre-checked.
                    $linkedRefundIds = \App\Models\MarketplaceRefundRequest::where('marketplace_payout_id', $this->record->id)
                        ->pluck('id')
                        ->all();

                    return [
                        'payout_tickets' => $rows,
                        'net_amount' => (float) $this->record->amount,
                        'included_refund_ids' => $linkedRefundIds,
                        'discount_amount' => (float) $this->record->discount_amount,
                    ];
                })
                ->form([
                    // Hidden state — promo-code discount populated by the
                    // "Adu biletele rămase" / "Adu la dată" buttons (and
                    // surfaced in the live preview + persisted at submit).
                    \Filament\Forms\Components\Hidden::make('discount_amount')->default('0.00'),

                    // Snapshot of the payout's currently-stored amounts so the
                    // operator can compare against the live preview below.
                    \Filament\Forms\Components\Placeholder::make('current_state')
                        ->label('Stare curentă (înainte de modificări)')
                        ->content(fn () => new \Illuminate\Support\HtmlString(
                            '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">'
                            . '<div style="padding:8px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;"><div style="font-size:10px;color:#888;text-transform:uppercase;">Brut salvat</div><div style="font-family:monospace;font-weight:600;color:#111827;">' . number_format((float) $this->record->gross_amount, 2) . ' RON</div></div>'
                            . '<div style="padding:8px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;"><div style="font-size:10px;color:#888;text-transform:uppercase;">Comision salvat</div><div style="font-family:monospace;font-weight:600;color:#b91c1c;">-' . number_format((float) $this->record->commission_amount, 2) . ' RON</div></div>'
                            . '<div style="padding:8px;border:1px solid #059669;border-radius:6px;background:#f0fdf4;"><div style="font-size:10px;color:#059669;text-transform:uppercase;">Net salvat</div><div style="font-family:monospace;font-weight:700;color:#059669;">' . number_format((float) $this->record->amount, 2) . ' RON</div></div>'
                            . '</div>'
                        ))
                        ->columnSpanFull(),

                    // desired amount + redistribute button on one row
                    \Filament\Schemas\Components\Grid::make(3)->schema([
                        \Filament\Forms\Components\TextInput::make('net_amount')
                            ->label('Sumă netă dorită')
                            ->helperText('Modifică pentru a redistribui — apoi apasă pe Distribuie proporțional.')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('RON')
                            ->live(onBlur: true)
                            ->columnSpan(2),
                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('distribute_proportionally')
                                ->label('Distribuie proporțional')
                                ->icon('heroicon-o-arrows-right-left')
                                ->color('primary')
                                ->size('sm')
                                ->action(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                                    $target = (float) ($get('net_amount') ?? 0);
                                    if ($target <= 0) {
                                        Notification::make()->title('Sumă invalidă')->body('Introdu mai întâi suma netă dorită.')->warning()->send();
                                        return;
                                    }
                                    $tickets = $get('payout_tickets') ?? [];
                                    $payoutMode = $this->record->event?->getEffectiveCommissionMode()
                                        ?? $this->record->commission_mode
                                        ?? 'included';
                                    // Compute current net sum from row data
                                    $currentNet = 0.0;
                                    foreach ($tickets as $t) {
                                        $qty = (int) ($t['qty'] ?? 0);
                                        $unit = (float) ($t['unit_price'] ?? 0);
                                        $commPer = (float) ($t['commission_per_ticket'] ?? 0);
                                        $rowMode = $t['commission_mode'] ?? $payoutMode;
                                        $isOnTop = in_array($rowMode, ['added_on_top', 'on_top'], true);
                                        $g = $qty * $unit + ($isOnTop ? $qty * $commPer : 0);
                                        $c = $qty * $commPer;
                                        $currentNet += $g - $c;
                                    }
                                    if ($currentNet <= 0.01) {
                                        Notification::make()->title('Nu pot scala')->body('Cantitățile actuale sumează 0; setează măcar un qty înainte de redistribuire.')->warning()->send();
                                        return;
                                    }
                                    $scale = $target / $currentNet;
                                    foreach ($tickets as &$t) {
                                        $t['qty'] = max(0, (int) round((int) ($t['qty'] ?? 0) * $scale));
                                    }
                                    unset($t);
                                    $set('payout_tickets', $tickets);
                                    Notification::make()->title('Scalat')->body('Factor: ' . number_format($scale, 3) . '. Verifică cantitățile noi în preview-ul de mai jos.')->success()->send();
                                }),

                            // Pull every ticket sold on the event that isn't already
                            // included in ANOTHER active payout — the operator's
                            // one-click way to re-fill this payout with the remaining
                            // tickets (e.g. after a recalc accidentally narrowed the
                            // slice, or when picking up newly-sold tickets).
                            \Filament\Actions\Action::make('fill_remaining_tickets')
                                ->label('Adu biletele rămase')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->size('sm')
                                ->requiresConfirmation()
                                ->modalHeading('Înlocuiește biletele cu cele rămase neîncasate?')
                                ->modalDescription('Lista actuală din repeater va fi înlocuită cu toate biletele de pe acest eveniment care NU sunt deja incluse în alt decont (POS / external_import / test_order excluse). Cantitățile se setează la maximul rămas.')
                                ->modalSubmitActionLabel('Da, înlocuiește')
                                ->action(function (\Filament\Schemas\Components\Utilities\Set $set) {
                                    $event = $this->record->event;
                                    if (!$event) {
                                        Notification::make()->title('Lipsește evenimentul')->body('Decontul nu este legat de un eveniment.')->danger()->send();
                                        return;
                                    }
                                    $items = \App\Models\MarketplacePayout::buildRemainingTicketsItems($event, $this->record->id);
                                    if (empty($items)) {
                                        Notification::make()->title('Nimic de adus')->body('Toate biletele vândute pe acest eveniment sunt deja incluse în alte deconturi.')->warning()->send();
                                        return;
                                    }
                                    $set('payout_tickets', $items);
                                    // Surface the per-type discount aggregate to the
                                    // hidden discount_amount input so the payout's
                                    // net reflects promo-code reductions.
                                    $discountTotal = \App\Models\MarketplacePayout::sumDiscountFromItems($items);
                                    $set('discount_amount', number_format($discountTotal, 2, '.', ''));
                                    $totalQty = array_sum(array_column($items, 'qty'));
                                    Notification::make()->title('Bilete adăugate')->body(count($items) . ' tipuri · ' . $totalQty . ' bilete · ' . number_format($discountTotal, 2) . ' RON discount aduse în listă. Verifică suma netă și apasă Salvează.')->success()->send();
                                }),

                            // Snapshot the event state AS OF a chosen date:
                            // tickets with order.created_at <= cutoff +
                            // refunds with refund_request.created_at <= cutoff,
                            // minus what's already in OTHER deconturi. Use case:
                            // back-filling a decont generated offline at a past
                            // date and aligning the system with that history.
                            \Filament\Actions\Action::make('fill_tickets_as_of_date')
                                ->label('Adu la dată')
                                ->icon('heroicon-o-clock')
                                ->color('gray')
                                ->size('sm')
                                ->modalHeading('Calculează decontul la o dată din trecut')
                                ->modalDescription('Repeater-ul de bilete + lista de rambursări se vor înlocui cu starea evenimentului la data aleasă (excluzând ce e deja în alte deconturi). Util pentru a recrea în sistem un decont făcut offline la o dată anterioară.')
                                ->modalSubmitActionLabel('Calculează')
                                ->form([
                                    \Filament\Forms\Components\DatePicker::make('cutoff_date')
                                        ->label('Data limită (inclusiv)')
                                        ->required()
                                        ->default(fn () => $this->record->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'))
                                        ->maxDate(now())
                                        ->helperText('Vânzările și rambursările făcute până la sfârșitul acestei zile vor fi incluse.'),
                                ])
                                ->action(function (array $data, \Filament\Schemas\Components\Utilities\Set $set) {
                                    $event = $this->record->event;
                                    if (!$event) {
                                        Notification::make()->title('Lipsește evenimentul')->body('Decontul nu este legat de un eveniment.')->danger()->send();
                                        return;
                                    }
                                    $cutoff = \Carbon\Carbon::parse($data['cutoff_date']);
                                    $items = \App\Models\MarketplacePayout::buildRemainingTicketsItems($event, $this->record->id, $cutoff);
                                    $refundIds = \App\Models\MarketplacePayout::getRefundIdsAsOfDate($event, $cutoff, $this->record->id);

                                    if (empty($items) && empty($refundIds)) {
                                        Notification::make()->title('Nimic la data aleasă')->body('Nu există bilete sau rambursări noi până la ' . $cutoff->format('d.m.Y') . ' care să nu fie deja în alt decont.')->warning()->send();
                                        return;
                                    }

                                    $set('payout_tickets', $items);
                                    $set('included_refund_ids', $refundIds);
                                    $discountTotal = \App\Models\MarketplacePayout::sumDiscountFromItems($items);
                                    $set('discount_amount', number_format($discountTotal, 2, '.', ''));
                                    $totalQty = array_sum(array_column($items, 'qty'));
                                    Notification::make()
                                        ->title('Stare la ' . $cutoff->format('d.m.Y'))
                                        ->body(count($items) . ' tipuri · ' . $totalQty . ' bilete · ' . count($refundIds) . ' rambursări · ' . number_format($discountTotal, 2) . ' RON discount. Verifică suma netă și apasă Salvează.')
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpan(1)->extraAttributes(['class' => 'flex items-end pb-6 gap-2']),
                    ]),

                    \Filament\Forms\Components\Repeater::make('payout_tickets')
                        ->label('Bilete incluse')
                        ->helperText('Modifică cantitățile per tip de bilet. Rândurile cu qty=0 se elimină la salvare.')
                        ->extraAttributes(['class' => 'ep-inline-actions'])
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('ticket_type_id'),
                            \Filament\Forms\Components\Hidden::make('unit_price'),
                            \Filament\Forms\Components\Hidden::make('commission_per_ticket'),
                            \Filament\Forms\Components\Hidden::make('commission_mode'),
                            // Per-row promo discount surfaced by the helper; the
                            // submit handler passes it through buildBreakdownFromSelection
                            // so the saved breakdown rows carry it.
                            \Filament\Forms\Components\Hidden::make('discount')->default('0'),
                            // Per-price tier breakdown — {price, qty}[] that the PDF
                            // expands into "50lei*2+40lei*2" entries on row 1b.
                            \Filament\Forms\Components\Hidden::make('tiers')->default([]),
                            \Filament\Forms\Components\Hidden::make('ticket_type_name'),
                            \Filament\Forms\Components\Placeholder::make('row_label')
                                ->hiddenLabel()
                                ->content(fn (\Filament\Schemas\Components\Utilities\Get $get) => new \Illuminate\Support\HtmlString(
                                    '<div class="flex items-center h-full py-2">'
                                    . '<span class="font-medium">' . e($get('ticket_type_name') ?? '') . '</span>'
                                    . '<span class="text-xs text-gray-400 ml-2">' . number_format((float) ($get('unit_price') ?? 0), 2) . ' RON/bilet · comision ' . number_format((float) ($get('commission_per_ticket') ?? 0), 2) . ' RON/bilet</span>'
                                    . '</div>'
                                ))
                                ->columnSpan(3),
                            \Filament\Forms\Components\TextInput::make('qty')
                                ->hiddenLabel()
                                ->numeric()
                                ->minValue(0)
                                ->suffix('bilete')
                                ->live(onBlur: true)
                                ->columnSpan(1),
                        ])
                        ->columns(4)
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(true)
                        ->live()
                        ->columnSpanFull(),

                    // Refund picker — operator chooses which refunds for this
                    // event are accounted for IN this payout. Selected refund
                    // amounts are deducted from the payout's net (visible in
                    // the live preview) and the refunds appear in this payout's
                    // PDF document. Available refunds = event refunds in
                    // refunded/partially_refunded status that aren't linked
                    // to ANOTHER payout (currently linked to this one or to
                    // nothing — operator can re-toggle freely).
                    \Filament\Forms\Components\CheckboxList::make('included_refund_ids')
                        ->label('Rambursări incluse în acest decont')
                        ->helperText('Bifează rambursările care intră în decontul curent — valoarea lor se scade din suma de plată și apar în documentul PDF.')
                        ->options(function () {
                            $event = $this->record->event;
                            if (!$event) return [];

                            return \App\Models\MarketplaceRefundRequest::query()
                                ->whereIn('status', [
                                    \App\Models\MarketplaceRefundRequest::STATUS_REFUNDED,
                                    \App\Models\MarketplaceRefundRequest::STATUS_PARTIALLY_REFUNDED,
                                ])
                                ->where(function ($q) use ($event) {
                                    $q->whereHas('order', function ($q2) use ($event) {
                                        $q2->where('event_id', $event->id)
                                            ->orWhere('marketplace_event_id', $event->id);
                                    });
                                })
                                ->where(function ($q) {
                                    // Show refunds that are unlinked OR already on this payout.
                                    $q->whereNull('marketplace_payout_id')
                                        ->orWhere('marketplace_payout_id', $this->record->id);
                                })
                                ->orderByDesc('completed_at')
                                ->get(['id', 'reference', 'approved_amount', 'completed_at'])
                                ->mapWithKeys(fn ($r) => [
                                    $r->id => sprintf(
                                        '%s · %s RON · %s',
                                        $r->reference,
                                        number_format((float) $r->approved_amount, 2),
                                        MarketplaceTz::fmt($r->completed_at, 'd.m.Y', $this->record->marketplaceClient ?? null, fallback: '—')
                                    ),
                                ])
                                ->all();
                        })
                        ->live()
                        ->columns(1)
                        ->bulkToggleable()
                        ->columnSpanFull(),

                    // Live preview — re-renders whenever payout_tickets state
                    // OR included_refund_ids change. Shows ticket totals,
                    // refund deduction, and the final amount paid to the
                    // organizer (ticket_net − refund_deduction) compared
                    // against the desired net.
                    \Filament\Forms\Components\Placeholder::make('live_preview')
                        ->label('')
                        ->content(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $tickets = $get('payout_tickets') ?? [];
                            $targetNet = (float) ($get('net_amount') ?? 0);
                            // Authoritative commission mode for the preview: try the
                            // event's effective mode first (the single source the
                            // SalesBreakdownService uses), then the payout's stored
                            // mode, then 'included'. Without this chain, a missing
                            // commission_mode on a row → 'included' → brut understated
                            // for added_on_top events (and the diff against Sumă netă
                            // dorită shows a phantom commission-sized shortfall).
                            $payoutMode = $this->record->event?->getEffectiveCommissionMode()
                                ?? $this->record->commission_mode
                                ?? 'included';
                            $totalQty = 0;
                            $gross = 0.0;
                            $commission = 0.0;
                            foreach ($tickets as $t) {
                                $qty = (int) ($t['qty'] ?? 0);
                                if ($qty <= 0) continue;
                                $unit = (float) ($t['unit_price'] ?? 0);
                                $commPer = (float) ($t['commission_per_ticket'] ?? 0);
                                $rowMode = $t['commission_mode'] ?? $payoutMode;
                                $isOnTop = in_array($rowMode, ['added_on_top', 'on_top'], true);
                                $totalQty += $qty;
                                $gross += $qty * $unit + ($isOnTop ? $qty * $commPer : 0);
                                $commission += $qty * $commPer;
                            }
                            $ticketNet = $gross - $commission;

                            // Discount from promo codes — the per-type aggregate
                            // is surfaced to discount_amount by the buttons that
                            // fill the repeater (Adu biletele rămase, Adu la
                            // dată); the operator can also edit it directly via
                            // the hidden field's state.
                            $discountAmount = (float) ($get('discount_amount') ?? 0);

                            $refundIds = $get('included_refund_ids') ?? [];
                            $refundIds = is_array($refundIds) ? array_values(array_filter($refundIds)) : [];
                            $refundCount = count($refundIds);
                            $refundTotal = 0.0;
                            if (!empty($refundIds)) {
                                $refundTotal = (float) \App\Models\MarketplaceRefundItem::query()
                                    ->whereIn('refund_request_id', $refundIds)
                                    ->where('status', 'refunded')
                                    ->sum('face_value');
                            }

                            $finalNet = $ticketNet - $discountAmount - $refundTotal;
                            $diff = $targetNet - $finalNet;
                            $diffSign = $diff > 0.01 ? '+' : ($diff < -0.01 ? '' : '');
                            $diffColor = abs($diff) < 0.5 ? '#059669' : '#d97706';
                            $diffLabel = abs($diff) < 0.5
                                ? '✓ identic cu Sumă netă dorită'
                                : "{$diffSign}" . number_format($diff, 2) . ' RON față de Sumă netă dorită (' . number_format($targetNet, 2) . ' RON)';

                            $extraLine = ($discountAmount > 0.005 || $refundCount > 0)
                                ? '<div style="margin-top:6px;padding-top:6px;border-top:1px dashed #93c5fd;display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">'
                                    . '<div><div style="font-size:10px;color:#666;">Net bilete</div><div style="font-family:monospace;font-weight:600;color:#111827;">' . number_format($ticketNet, 2) . ' RON</div></div>'
                                    . ($discountAmount > 0.005
                                        ? '<div><div style="font-size:10px;color:#666;">Discounturi</div><div style="font-family:monospace;font-weight:600;color:#b91c1c;">-' . number_format($discountAmount, 2) . ' RON</div></div>'
                                        : '<div></div>')
                                    . ($refundCount > 0
                                        ? '<div><div style="font-size:10px;color:#666;">Rambursări (' . $refundCount . ')</div><div style="font-family:monospace;font-weight:600;color:#b91c1c;">-' . number_format($refundTotal, 2) . ' RON</div></div>'
                                        : '<div></div>')
                                    . '<div><div style="font-size:10px;color:#059669;text-transform:uppercase;">Final de plată</div><div style="font-family:monospace;font-weight:700;font-size:16px;color:#059669;">' . number_format($finalNet, 2) . ' RON</div></div>'
                                    . '</div>'
                                : '';

                            return new \Illuminate\Support\HtmlString(
                                '<div style="padding:12px;border:2px solid #3b82f6;border-radius:8px;background:#eff6ff;margin-top:8px;">'
                                . '<div style="font-size:10px;color:#1e40af;text-transform:uppercase;font-weight:600;margin-bottom:6px;">Preview live</div>'
                                . '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">'
                                . '<div><div style="font-size:10px;color:#666;">Total bilete</div><div style="font-family:monospace;font-weight:600;color:#111827;">' . $totalQty . '</div></div>'
                                . '<div><div style="font-size:10px;color:#666;">Brut</div><div style="font-family:monospace;font-weight:600;color:#111827;">' . number_format($gross, 2) . ' RON</div></div>'
                                . '<div><div style="font-size:10px;color:#666;">Comision</div><div style="font-family:monospace;font-weight:600;color:#b91c1c;">-' . number_format($commission, 2) . ' RON</div></div>'
                                . '<div><div style="font-size:10px;color:#666;">Net bilete</div><div style="font-family:monospace;font-weight:700;color:' . (($discountAmount > 0.005 || $refundCount > 0) ? '#111827' : '#059669') . ';">' . number_format($ticketNet, 2) . ' RON</div></div>'
                                . '</div>'
                                . $extraLine
                                . '<div style="margin-top:6px;font-size:11px;color:' . $diffColor . ';font-weight:500;">' . $diffLabel . '</div>'
                                . '</div>'
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $event = $this->record->event;
                    if (!$event) {
                        Notification::make()->title('Eroare')->body('Decontul nu este legat de un eveniment.')->danger()->send();
                        return;
                    }
                    $event->loadMissing('ticketTypes');

                    $payoutTickets = $data['payout_tickets'] ?? [];
                    $enteredNet = (float) ($data['net_amount'] ?? 0);
                    $includedRefundIds = is_array($data['included_refund_ids'] ?? null) ? $data['included_refund_ids'] : [];

                    if (empty($payoutTickets)) {
                        Notification::make()->title('Lista de bilete goală')->body('Adaugă cel puțin un rând cu qty > 0.')->warning()->send();
                        return;
                    }

                    // The interpretation of "Sumă netă dorită" when refunds
                    // are selected: it's the FINAL net (after refund
                    // deduction). So the ticket-only target = entered + refund_total.
                    // Without this adjustment, the proportional scaler would
                    // overshoot — it would drop ticket qtys to hit
                    // enteredNet from ticket_net alone, then subtract refunds
                    // on top, leaving the operator below their target.
                    $refundTotal = !empty($includedRefundIds)
                        ? (float) \App\Models\MarketplaceRefundItem::query()
                            ->whereIn('refund_request_id', $includedRefundIds)
                            ->where('status', 'refunded')
                            ->sum('face_value')
                        : 0.0;

                    $ticketTarget = $enteredNet > 0 ? $enteredNet + $refundTotal : null;

                    $built = \App\Models\MarketplacePayout::buildBreakdownFromSelection(
                        $payoutTickets,
                        $event,
                        $ticketTarget
                    );

                    if (empty($built['rows'])) {
                        Notification::make()->title('Rezultat gol')->body('După scalare, niciun rând nu mai are qty > 0. Anulat.')->warning()->send();
                        return;
                    }

                    $oldAmount = (float) $this->record->amount;
                    // totals['net'] already nets out the per-row promo discount
                    // — buildBreakdownFromSelection now propagates each item's
                    // discount through every pass and into the breakdown rows
                    // (their `discount` and `net` fields). All we still have to
                    // peel off here is the refund total.
                    $ticketNet = $built['totals']['net'];
                    $discountAmount = (float) ($built['totals']['discount'] ?? 0);
                    $newAmount = round($ticketNet - $refundTotal, 2);
                    $delta = round($oldAmount - $newAmount, 2);

                    \Illuminate\Support\Facades\DB::transaction(function () use ($built, $newAmount, $delta, $includedRefundIds, $refundTotal, $discountAmount) {
                        $this->record->update([
                            'ticket_breakdown' => $built['rows'],
                            'commission_mode' => $built['commission_mode'],
                            'amount' => $newAmount,
                            'gross_amount' => $built['totals']['gross'],
                            'commission_amount' => $built['totals']['commission'],
                            'discount_amount' => $discountAmount,
                            'refund_amount' => $refundTotal,
                        ]);

                        // Update the FK on refund_requests so the linkage
                        // table reflects the new selection. Refunds removed
                        // here become unattached (available for another
                        // payout to claim); refunds added are claimed by
                        // this one.
                        $this->record->syncIncludedRefunds($includedRefundIds);

                        // Adjust the organizer's reserved (pending) balance so
                        // available_balance reflects the corrected request.
                        if (abs($delta) > 0.005 && $this->record->organizer) {
                            if ($delta > 0) {
                                $this->record->organizer->returnPendingBalance($delta);
                            } else {
                                $this->record->organizer->reserveBalanceForPayout(abs($delta));
                            }
                        }
                    });

                    Notification::make()
                        ->title('Bilete decontate actualizate')
                        ->body('Net: ' . number_format($newAmount, 2) . ' RON · ' . count($built['rows']) . ' tipuri de bilete · regenerează documentul PDF manual dacă e nevoie.')
                        ->success()
                        ->send();

                    $this->redirect(PayoutResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('recalc_breakdown')
                ->label('Recalculează snapshot bilete')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Se va înlocui snapshot-ul actual cu valorile recalculate din vânzările reale (preț plătit per bilet, comision, discounturi, asigurări) și se vor actualiza sumele payout-ului + balanța organizatorului. Documentele de decont/factură generate trebuie regenerate manual după recalcul.')
                ->visible(fn () => in_array($this->record->status, ['pending', 'approved', 'processing'])
                    && !empty($this->record->event_id))
                ->action(function () {
                    $payout = $this->record;

                    // Single source of truth — same logic the bulk recompute
                    // (tinker/artisan) runs across every payout.
                    $result = $payout->recalcBreakdownSnapshot();

                    if (!($result['ok'] ?? false)) {
                        if (($result['reason'] ?? null) === 'no_event') {
                            Notification::make()->title('Eroare')->body('Decontul nu este legat de un eveniment.')->danger()->send();
                        } else {
                            Notification::make()->title('Nu s-au găsit vânzări')->body('Nu există bilete valide în perioada decontului pentru a recalcula snapshot-ul.')->warning()->send();
                        }
                        return;
                    }

                    $delta = (float) $result['delta'];
                    $deltaLabel = $delta > 0
                        ? '−' . number_format($delta, 2) . ' RON eliberați la disponibil'
                        : ($delta < 0 ? '+' . number_format(abs($delta), 2) . ' RON rezervați suplimentar' : 'fără ajustare de balanță');

                    Notification::make()
                        ->title('Snapshot recalculat')
                        ->body("Sumă netă: " . number_format($result['amount'], 2) . " RON · comision: " . number_format($result['commission'], 2) . " RON · {$deltaLabel}.")
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

            // ========== EXPLICIT NOTIFY ORGANIZER ==========
            // Admin-controlled notification paths. The automatic in-app
            // notification on document creation has been disabled (see
            // OrganizerDocument::$skipNotificationOnCreate) — these two
            // actions let the admin notify the organizer manually when
            // the decont is ready to share.
            Actions\Action::make('send_decont_email')
                ->label('Trimite decont prin email')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('email')
                        ->label('Adresa email destinatar')
                        ->email()
                        ->required()
                        ->default(fn () => $this->record->organizer?->billing_email ?? $this->record->organizer?->email),
                ])
                ->action(function (array $data) {
                    $this->sendDocumentByEmail($this->record->decontDocument, $data['email'], 'Decont');
                })
                ->visible(fn () => $this->record->decontDocument !== null && !in_array($this->record->status, ['rejected', 'cancelled'])),

            Actions\Action::make('notify_organizer_inapp')
                ->label('Notifică organizator (in-app)')
                ->icon('heroicon-o-bell-alert')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Notifică organizator')
                ->modalDescription('Se va crea o notificare în panoul organizatorului ("Document generat: Decont Drepturi"). Nu se trimite email — pentru email folosește acțiunea separată.')
                ->action(function () {
                    try {
                        \App\Services\OrganizerNotificationService::notifyDocumentGenerated($this->record->decontDocument);
                        Notification::make()->title('Notificare in-app trimisă')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Eroare')->body($e->getMessage())->danger()->send();
                    }
                })
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

                    // For general-client invoices the marketplace already
                    // collected the commission from buyers on top of the
                    // ticket price — the invoice is just a paper record.
                    // Stamp it as paid on creation. Organizer-recipient
                    // invoices represent an actual debt and stay outstanding.
                    $isGeneralClient = $recipientType === 'general_client';

                    // Description + line item composition mirrors what the
                    // accountant expects to see on the printed factură. The
                    // sequence label ("1 din 2") only shows when more than
                    // one decont exists for this event+organizer.
                    [$description, $itemDescription] = $this->buildGeneralClientInvoiceTexts($payout, $isGeneralClient);

                    $invoice = Invoice::create([
                        'marketplace_client_id' => $marketplace->id,
                        'marketplace_organizer_id' => $organizer->id,
                        'marketplace_payout_id' => $payout->id,
                        'number' => $invoiceNumber,
                        'type' => 'fiscal',
                        'description' => $description,
                        'issue_date' => now(),
                        'period_start' => $payout->period_start,
                        'period_end' => $payout->period_end,
                        'due_date' => now()->addDays(30),
                        'subtotal' => $commissionSubtotal,
                        'vat_rate' => $vatRate,
                        'vat_amount' => $vatAmount,
                        'amount' => $commissionSubtotal + $vatAmount,
                        'currency' => $payout->currency ?? 'RON',
                        'status' => $isGeneralClient ? 'paid' : 'outstanding',
                        'paid_at' => $isGeneralClient ? now() : null,
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
                                'description' => $itemDescription,
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

            // ========== GENERATE ORGANIZER INVOICE (event-level, billed once) ==========
            // Single invoice per event that bills the organizer for two
            // disjoint commission flows that marketplace didn't recover via
            // the regular Factură:
            //   (1) POS commission — sales the organizer collected via the
            //       mobile POS app (never flowed through marketplace).
            //   (2) Refunded-ticket commission — commission portion that was
            //       returned to the customer on FULL refunds
            //       (commission_refunded=true on the refund item).
            // Visible on any active decont of the event once the event has
            // ended and the invoice hasn't been emitted yet. After emission
            // it disappears everywhere; the other decont gets a "Vezi factura
            // organizator" link below instead.
            Actions\Action::make('generate_invoice_organizer')
                ->label('Generează factură organizator')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(function () {
                    $posComm = $this->record->getPosCommissionTotal();
                    $refundedComm = $this->record->getRefundedCommissionTotalForEvent();
                    $total = $posComm + $refundedComm;
                    $parts = [];
                    if ($posComm > 0) {
                        $parts[] = 'comision POS: ' . number_format($posComm, 2) . ' RON';
                    }
                    if ($refundedComm > 0) {
                        $parts[] = 'comision pe bilete rambursate integral: ' . number_format($refundedComm, 2) . ' RON';
                    }
                    $breakdown = !empty($parts) ? ' (' . implode(' + ', $parts) . ')' : '';
                    return 'Se va genera o singură factură către organizator pentru toate comisioanele datorate pe acest eveniment: '
                        . number_format($total, 2) . ' RON' . $breakdown
                        . '. După emitere, butonul dispare de pe toate deconturile evenimentului.';
                })
                // Allow ONE invoice per PAYOUT (not per event). Previously
                // the check was `getEventPosInvoice() === null`, which
                // hid the button on every decont of the event once any
                // single decont had emitted an invoice. Operators need
                // to be able to emit one invoice per decont when sales /
                // refunds keep arriving across multiple deconts of the
                // same event.
                ->visible(fn () => $this->record->posInvoice === null
                    && $this->record->isEventFinished()
                    && ($this->record->getPosCommissionTotal() > 0
                        || $this->record->getRefundedCommissionTotalForEvent() > 0)
                    && !in_array($this->record->status, ['rejected', 'cancelled']))
                ->action(function () {
                    $this->generatePosInvoice();
                }),

            // When the organizer invoice was emitted on ANOTHER decont of the
            // same event, surface a one-click jump so the operator can see /
            // manage it without hunting through deconts.
            Actions\Action::make('view_event_organizer_invoice_elsewhere')
                ->label(function () {
                    $inv = $this->record->getEventPosInvoice();
                    $payoutId = $inv?->marketplace_payout_id;
                    return $payoutId
                        ? "Vezi factura organizator (Decont #{$payoutId})"
                        : 'Vezi factura organizator';
                })
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(function () {
                    $inv = $this->record->getEventPosInvoice();
                    return $inv !== null
                        && $inv->marketplace_payout_id !== $this->record->id;
                })
                ->url(function () {
                    $inv = $this->record->getEventPosInvoice();
                    if (!$inv) return null;
                    // Link to the decont that owns the organizer invoice — from
                    // there the operator can view/download/send through the
                    // existing organizer-invoice group actions.
                    return PayoutResource::getUrl('view', ['record' => $inv->marketplace_payout_id]);
                }, shouldOpenInNewTab: true),

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
                        return "Factura organizator va fi trimisa ca {$docLabel} in {$providerLabel}.";
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
                ->label('Factură organizator')
                ->icon('heroicon-o-receipt-percent')
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

        if (!$payout->event) {
            Notification::make()->title('Decontul nu este legat de un eveniment')->warning()->send();
            return;
        }

        // Organizer invoice is billed ONCE per event and covers BOTH:
        //   (1) POS commission — every POS sale on the event, event-wide.
        //   (2) Refunded-ticket commission — commission on tickets where
        //       commission_refunded=true (marketplace returned commission to
        //       the customer; bills it back to the organizer).
        // Gated upstream by the button's visible() so we only reach here
        // after the event has finished and no prior invoice exists.
        $posRows = app(\App\Services\Marketplace\SalesBreakdownService::class)
            ->buildPosForPayout($payout->event, null, null);
        $refundedRows = $payout->getRefundedCommissionRowsForEvent();
        if (empty($posRows) && empty($refundedRows)) {
            Notification::make()->title('Nu există comisioane de facturat')->warning()->send();
            return;
        }

        // Organizer contract reference for the POS line description.
        // Contract series/date come from the organizer's financiar tab —
        // any change to that contract is reflected next time a POS
        // invoice is generated.
        $contractNumber = trim((string) ($organizer->contract_number_series ?? ''));
        $contractDate = $organizer->contract_date instanceof \Carbon\Carbon
            ? $organizer->contract_date->format('d.m.Y')
            : (is_string($organizer->contract_date) && $organizer->contract_date !== ''
                ? \Carbon\Carbon::parse($organizer->contract_date)->format('d.m.Y')
                : '');
        $contractFragment = ($contractNumber !== '' || $contractDate !== '')
            ? 'conform contract nr ' . $contractNumber . '/' . $contractDate . ','
            : '';

        // Event context — quoted name plus date / venue / city, matching
        // the accountant-friendly phrasing the user defined.
        $eventCtx = $this->resolveEventContext($payout->event);
        $eventFragment = $eventCtx['name'] !== ''
            ? ' pentru eveniment "' . $eventCtx['name'] . '"'
                . ($eventCtx['date'] !== '' ? ' din ' . $eventCtx['date'] : '')
                . ($eventCtx['venue'] !== '' && $eventCtx['city'] !== ''
                    ? ' la ' . $eventCtx['venue'] . ', ' . $eventCtx['city']
                    : ($eventCtx['venue'] !== '' ? ' la ' . $eventCtx['venue'] : ''))
            : '';

        $items = [];
        $subtotal = 0.0;

        // (1) POS commission lines — one per ticket type sold via POS.
        foreach ($posRows as $item) {
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
            if ($qty <= 0 || $commPerTicket <= 0) {
                continue;
            }

            $lineTotal = round($qty * $commPerTicket, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'description' => trim('Prestari servicii invitatii/bilete online acces POS, taxa ticketing '
                    . $contractFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => $commPerTicket,
                'amount' => $lineTotal,
            ];
        }

        // (2) Refunded-ticket commission lines — commission returned to the
        //     customer on full refunds. Marketplace recovers it from the
        //     organizer here. One line per ticket type that had any
        //     commission_refunded=true items.
        foreach ($refundedRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $commPerTicket = (float) ($row['commission_per_ticket'] ?? 0);
            $lineTotal = round((float) ($row['commission_amount'] ?? ($qty * $commPerTicket)), 2);
            if ($qty <= 0 || $lineTotal <= 0) {
                continue;
            }

            $subtotal += $lineTotal;

            $items[] = [
                'description' => trim('Comision pentru bilet "' . ($row['ticket_type_name'] ?? 'Bilet')
                    . '" rambursat integral (comision returnat clientului), taxa ticketing '
                    . $contractFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => $commPerTicket,
                'amount' => $lineTotal,
            ];
        }

        if ($subtotal <= 0) {
            Notification::make()->title('Nu există comisioane de facturat')->warning()->send();
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
            'description' => 'Factură organizator (comision POS + comision pe rambursări integrale) — decont ' . $payout->reference,
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
     * Resolve the event/venue parts callers need to compose invoice text.
     * Same translatable-fallback rules as buildEventContextSuffix, just
     * exposed as a structured array so each caller can phrase the line
     * the way the accountant expects.
     *
     * @return array{name: string, date: string, venue: string, city: string}
     */
    protected function resolveEventContext(?\App\Models\Event $event): array
    {
        if (!$event) {
            return ['name' => '', 'date' => '', 'venue' => '', 'city' => ''];
        }

        $title = $event->title;
        $name = is_array($title)
            ? ($title['ro'] ?? $title['en'] ?? (reset($title) ?: ''))
            : ($title ?? '');

        $date = '';
        if ($event->event_date) {
            $date = $event->event_date->format('d.m.Y');
        } elseif ($event->range_start_date) {
            $date = $event->range_start_date->format('d.m.Y');
        }

        $venue = '';
        $city = '';
        if ($event->venue) {
            $vName = $event->venue->name;
            $venue = is_array($vName)
                ? ($vName['ro'] ?? $vName['en'] ?? (reset($vName) ?: ''))
                : ($vName ?? '');
            $city = $event->venue->city ?? '';
        }

        return [
            'name' => (string) $name,
            'date' => (string) $date,
            'venue' => (string) $venue,
            'city' => (string) $city,
        ];
    }

    /**
     * Sequence label "X din Y " (trailing space) for invoice descriptions,
     * empty when the event has just one payout overall. Counts pending /
     * approved / processing / completed payouts only — rejected /
     * cancelled never appear on the books.
     */
    protected function buildPayoutSequenceLabel(\App\Models\MarketplacePayout $payout): string
    {
        if (!$payout->event_id || !$payout->marketplace_organizer_id) {
            return '';
        }
        $total = \App\Models\MarketplacePayout::where('event_id', $payout->event_id)
            ->where('marketplace_organizer_id', $payout->marketplace_organizer_id)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->count();
        if ($total <= 1) {
            return '';
        }
        $nr = \App\Models\MarketplacePayout::where('event_id', $payout->event_id)
            ->where('marketplace_organizer_id', $payout->marketplace_organizer_id)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->where('id', '<=', $payout->id)
            ->count();
        return $nr . ' din ' . $total . ' ';
    }

    /**
     * Build (description, item_description) for the general-client-style
     * invoice. Same shape regardless of whether the invoice is actually
     * sent to the general client or to the organizer — we keep the
     * phrasing the user defined for general invoices and let the meta
     * recipient_type drive who it's sent to.
     *
     * @return array{0: string, 1: string}  [header description, line item description]
     */
    protected function buildGeneralClientInvoiceTexts(\App\Models\MarketplacePayout $payout, bool $isGeneralClient): array
    {
        $series = trim((string) ($payout->decont_series ?? '')) !== ''
            ? $payout->decont_series
            : $payout->reference;
        $reference = $payout->reference;
        $sequenceLabel = $this->buildPayoutSequenceLabel($payout);
        $ev = $this->resolveEventContext($payout->event);
        $createdAt = MarketplaceTz::fmt($payout->created_at, 'd.m.Y', $payout->marketplaceClient ?? null, fallback: '');

        $venueFragment = '';
        if ($ev['venue'] !== '' && $ev['city'] !== '') {
            $venueFragment = ' la ' . $ev['venue'] . ', ' . $ev['city'];
        } elseif ($ev['venue'] !== '') {
            $venueFragment = ' la ' . $ev['venue'];
        } elseif ($ev['city'] !== '') {
            $venueFragment = ' la ' . $ev['city'];
        }

        // Header text: "Factura pentru decont [seq] [series] cu referinta
        // [reference] pentru [event] din [date] la [venue], [city]".
        $headerEventFragment = $ev['name'] !== ''
            ? ' pentru ' . $ev['name']
                . ($ev['date'] !== '' ? ' din ' . $ev['date'] : '')
                . $venueFragment
            : '';
        $description = 'Factura pentru decont '
            . $sequenceLabel
            . $series
            . ' cu referinta ' . $reference
            . $headerEventFragment;

        // Line item text: only emitted in the general-client phrasing the
        // user defined. For organizer-recipient invoices we keep the old
        // short "Comision servicii ticketing - REF" line, since the
        // accountant context is different there.
        if ($isGeneralClient) {
            $itemEventFragment = $ev['name'] !== ''
                ? ' pentru evenimentul "' . $ev['name'] . '"'
                    . ($ev['date'] !== '' ? ' din ' . $ev['date'] : '')
                    . ($ev['venue'] !== '' && $ev['city'] !== ''
                        ? ' la ' . $ev['venue'] . ', ' . $ev['city']
                        : ($ev['venue'] !== '' ? ' la ' . $ev['venue'] : ''))
                : '';
            $itemDescription = 'Taxa ticketing bilete vandute online'
                . $itemEventFragment
                . ' // Conform decont nr. ' . $series
                . ($createdAt !== '' ? ' din data ' . $createdAt : '');
        } else {
            $itemDescription = 'Comision servicii ticketing - ' . $reference;
        }

        return [$description, $itemDescription];
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

        // Admin-triggered generation must NOT auto-notify the organizer.
        // The admin decides when the document is final and the organizer
        // should be notified, via the separate "Trimite decont prin email"
        // or "Notifică organizator" actions on this page. Without this
        // flag, every regenerate created an in-app notification spam in
        // the organizer's bell icon.
        \App\Models\OrganizerDocument::$skipNotificationOnCreate = true;

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
        } finally {
            // Always reset so subsequent (organizer-side) document
            // generations notify normally.
            \App\Models\OrganizerDocument::$skipNotificationOnCreate = false;
        }

        // Reload the relationship to see if a decont was actually created.
        $this->record->unsetRelation('decontDocument');
        $decont = $this->record->decontDocument;

        if ($decont) {
            Notification::make()
                ->title($isRegeneration ? 'Decont regenerat' : 'Decont generat')
                ->body('Notificare automată suprimată. Folosește "Trimite decont prin email" sau "Notifică organizator" când vrei să anunți organizatorul.')
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
            // Deconturile sunt tranzacționale → providerul tranzacțional cu
            // fallback runtime la primary dacă SMTP-ul tranzacțional eșuează.
            if (!$marketplace?->hasMailConfigured() && !$marketplace?->hasTransactionalMailConfigured()) {
                Notification::make()->title('Mail-ul nu este configurat')->body('Configurează SMTP/Brevo în Settings > Emails.')->danger()->send();
                return;
            }

            $fromAddress = $marketplace->getTransactionalEmailFromAddress();
            $fromName = $marketplace->getTransactionalEmailFromName();

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
                ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                ->to($email)
                ->subject($subject)
                ->html($bodyHtml)
                ->attachFromPath($filePath, $document->file_name, 'application/pdf');

            $result = $marketplace->sendTransactionalEmail($symfonyEmail);

            if (!$result['success']) {
                Notification::make()->title('Eroare la trimitere')->body($result['error'] ?? 'Trimiterea a eșuat')->danger()->send();
                return;
            }

            // Log to marketplace email logs
            \App\Models\MarketplaceEmailLog::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer?->id,
                'marketplace_event_id' => null,
                'template_slug' => 'decont_send',
                'from_email' => $fromAddress,
                'from_name' => $fromName,
                'to_email' => $email,
                'to_name' => $organizer?->name ?? $email,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $result['message_id'] ?? null,
                'metadata' => ['transport_used' => $result['transport_used']],
            ]);

            $suffix = $result['transport_used'] === 'primary_fallback' ? ' (via Brevo fallback)' : '';
            Notification::make()->title("{$docType} trimis la {$email}{$suffix}")->success()->send();
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
            // Facturile sunt tranzacționale → providerul tranzacțional cu fallback runtime la primary.
            if (!$marketplace?->hasMailConfigured() && !$marketplace?->hasTransactionalMailConfigured()) {
                Notification::make()->title('Mail-ul nu este configurat')->danger()->send();
                return;
            }

            $fromAddress = $marketplace->getTransactionalEmailFromAddress();
            $fromName = $marketplace->getTransactionalEmailFromName();

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
                ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                ->to($email)
                ->subject($subject)
                ->html($bodyHtml);

            $result = $marketplace->sendTransactionalEmail($symfonyEmail);

            if (!$result['success']) {
                Notification::make()->title('Eroare la trimitere')->body($result['error'] ?? 'Trimiterea a eșuat')->danger()->send();
                return;
            }

            // Log to marketplace email logs
            \App\Models\MarketplaceEmailLog::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer?->id,
                'marketplace_event_id' => null,
                'template_slug' => 'invoice_send',
                'from_email' => $fromAddress,
                'from_name' => $fromName,
                'to_email' => $email,
                'to_name' => $organizer?->name ?? $email,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $result['message_id'] ?? null,
                'metadata' => ['transport_used' => $result['transport_used']],
            ]);

            $suffix = $result['transport_used'] === 'primary_fallback' ? ' (via Brevo fallback)' : '';
            Notification::make()->title("Factură trimisă la {$email}{$suffix}")->success()->send();
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
