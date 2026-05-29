<?php

namespace App\Filament\Marketplace\Resources\PendingPaymentResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\PendingPaymentResource;
use App\Models\Invoice;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewPendingPayment extends ViewRecord
{
    protected static string $resource = PendingPaymentResource::class;

    public function getTitle(): string
    {
        return 'Plată decont ' . ($this->record->reference ?? '#' . $this->record->id);
    }

    public function getHeading(): string
    {
        return 'Plată decont ' . ($this->record->reference ?? '#' . $this->record->id);
    }

    protected function getHeaderActions(): array
    {
        // Acțiunile Achitat / Respins trăiesc în infolist, jos. Header lăsat
        // doar cu link înapoi spre decontul standard (pentru cazul în care
        // operatorul vrea fluxul complet — recalc snapshot, edit bilete etc).
        return [
            Actions\Action::make('view_full_decont')
                ->label('Vezi decontul complet')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => PayoutResource::getUrl('view', ['record' => $this->record->id]))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->components([
                // 1) ORGANIZATOR
                Section::make('Organizator')
                    ->icon('heroicon-o-building-office')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('organizer.company_name')
                            ->label('Companie')
                            ->state(fn ($record) => $record->organizer?->company_name
                                ?? $record->organizer?->name
                                ?? '—'),
                        Infolists\Components\TextEntry::make('organizer.cui')
                            ->label('CUI')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('organizer.company_address')
                            ->label('Adresă')
                            ->placeholder('—')
                            ->columnSpan(2),
                        Infolists\Components\TextEntry::make('representative')
                            ->label('Reprezentant')
                            ->state(function ($record) {
                                $o = $record->organizer;
                                if (!$o) return '—';
                                $name = trim(($o->representative_first_name ?? '') . ' ' . ($o->representative_last_name ?? ''));
                                return $name !== '' ? $name : '—';
                            }),
                        Infolists\Components\TextEntry::make('organizer.email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('—'),
                    ]),

                // 2) CONT BANCAR (primar)
                Section::make('Cont bancar')
                    ->icon('heroicon-o-building-library')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('bank_name')
                            ->label('Bancă')
                            ->state(fn ($record) => $this->getPrimaryBank($record)?->bank_name ?? '—'),
                        Infolists\Components\TextEntry::make('bank_iban')
                            ->label('IBAN')
                            ->state(fn ($record) => $this->getPrimaryBank($record)?->iban ?? '—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('bank_holder')
                            ->label('Titular')
                            ->state(fn ($record) => $this->getPrimaryBank($record)?->account_holder ?? '—'),
                        Infolists\Components\TextEntry::make('bank_warning')
                            ->hiddenLabel()
                            ->visible(fn ($record) => $this->getPrimaryBank($record) === null)
                            ->state(fn () => new HtmlString(
                                '<div style="padding:8px 12px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;color:#92400e;font-size:13px;">'
                                . '⚠ Organizatorul nu are setat un cont bancar primar — cere-i să-l configureze înainte de transfer.'
                                . '</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // 3) SOLD DE PLATĂ
                Section::make('Sold de plată')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Infolists\Components\TextEntry::make('balance_breakdown')
                            ->hiddenLabel()
                            ->state(fn ($record) => new HtmlString($this->renderBalanceBreakdown($record)))
                            ->columnSpanFull(),
                    ]),

                // 4) ACȚIUNI (doar 2: Achitat + Respins)
                Section::make('Acțiuni')
                    ->icon('heroicon-o-bolt')
                    ->columns(2)
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'approved', 'processing'], true))
                    ->schema([
                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('mark_paid')
                                ->label('Achitat')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalHeading('Marchează plata ca achitată')
                                ->modalDescription(function ($record) {
                                    $balance = PendingPaymentResource::computeBalance($record);
                                    return 'Transferul de ' . number_format($balance, 2) . ' RON a fost efectuat către organizator. Decontul se va marca completed, iar facturile organizator linkate (POS / refunds) vor fi marcate paid în același transfer.';
                                })
                                ->form([
                                    Forms\Components\TextInput::make('payment_reference')
                                        ->label('Referință transfer')
                                        ->required()
                                        ->helperText('Numărul transferului bancar sau ID-ul tranzacției.'),
                                    Forms\Components\Textarea::make('payment_notes')
                                        ->label('Note')
                                        ->rows(2),
                                ])
                                ->action(function (array $data, $record, $livewire) {
                                    // 1. Decont → completed
                                    $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);

                                    // 2. Facturile organizator linkate → paid
                                    Invoice::query()
                                        ->where('marketplace_payout_id', $record->id)
                                        ->where('meta->is_pos_commission', true)
                                        ->whereIn('status', ['outstanding', 'new', 'overdue'])
                                        ->get()
                                        ->each(fn ($inv) => $inv->markAsPaid('manual'));

                                    Notification::make()
                                        ->title('Plată înregistrată')
                                        ->body('Decontul + facturile organizator au fost marcate ca achitate.')
                                        ->success()
                                        ->send();

                                    $livewire->redirect(PendingPaymentResource::getUrl('index'));
                                }),
                        ])->fullWidth(),

                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('mark_rejected')
                                ->label('Respinge plata')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Respinge decontul')
                                ->modalDescription('Decontul va fi marcat rejected, PDF-ul decont va fi șters, iar facturile organizator linkate vor fi marcate cancelled.')
                                ->form([
                                    Forms\Components\Textarea::make('reason')
                                        ->label('Motiv respingere')
                                        ->required()
                                        ->rows(3),
                                ])
                                ->action(function (array $data, $record, $livewire) {
                                    $admin = Auth::guard('marketplace_admin')->user();

                                    // 1. Decont → rejected (releases reserved balance)
                                    $record->reject($admin->id, $data['reason']);

                                    // 2. Șterge PDF + record decont (același flow ca
                                    //    reject_payout din PayoutResource).
                                    $decont = $record->decontDocument;
                                    if ($decont) {
                                        if ($decont->file_path) {
                                            Storage::disk('public')->delete($decont->file_path);
                                        }
                                        $decont->delete();
                                    }

                                    // 3. Facturile organizator linkate → cancelled
                                    Invoice::query()
                                        ->where('marketplace_payout_id', $record->id)
                                        ->where('meta->is_pos_commission', true)
                                        ->whereIn('status', ['outstanding', 'new', 'overdue', 'paid'])
                                        ->update(['status' => 'cancelled']);

                                    Notification::make()
                                        ->title('Plată respinsă')
                                        ->body('Decontul a fost marcat rejected, documentele asociate au fost curățate.')
                                        ->success()
                                        ->send();

                                    $livewire->redirect(PendingPaymentResource::getUrl('index'));
                                }),
                        ])->fullWidth(),
                    ]),

                // 4b) Status final pentru deconturile deja procesate (read-only info)
                Section::make('Plată înregistrată')
                    ->icon('heroicon-o-check-badge')
                    ->columns(2)
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_reference')
                            ->label('Referință transfer')
                            ->placeholder('—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Data plății')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment_notes')
                            ->label('Note')
                            ->placeholder('—')
                            ->columnSpan(2),
                    ]),

                Section::make('Plată respinsă')
                    ->icon('heroicon-o-x-circle')
                    ->columns(1)
                    ->visible(fn ($record) => $record->status === 'rejected')
                    ->schema([
                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Motiv respingere')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('rejected_at')
                            ->label('Data respingerii')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),

                // 5) ALTE DECONTURI PE ACELAȘI EVENIMENT (context)
                Section::make('Alte deconturi pe acest eveniment')
                    ->icon('heroicon-o-rectangle-stack')
                    ->compact()
                    ->visible(fn ($record) => $this->otherEventPayouts($record)->isNotEmpty())
                    ->schema([
                        Infolists\Components\TextEntry::make('other_payouts_list')
                            ->hiddenLabel()
                            ->state(fn ($record) => new HtmlString($this->renderOtherPayouts($record)))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    // ───────────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────────

    protected function getPrimaryBank(MarketplacePayout $record): ?MarketplaceOrganizerBankAccount
    {
        if (!$record->marketplace_organizer_id) {
            return null;
        }
        return MarketplaceOrganizerBankAccount::query()
            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    /**
     * Render the explicit "decont − facturi = de plată" formula with line
     * items, bullet content for each invoice (from invoice meta).
     */
    protected function renderBalanceBreakdown(MarketplacePayout $record): string
    {
        $decontAmount = (float) $record->amount;
        $invoices = Invoice::query()
            ->where('marketplace_payout_id', $record->id)
            ->where('meta->is_pos_commission', true)
            ->orderBy('id')
            ->get();

        $invoiceOutstandingTotal = $invoices
            ->whereIn('status', ['outstanding', 'new', 'overdue'])
            ->sum(fn ($i) => (float) $i->amount);
        $balance = $decontAmount - (float) $invoiceOutstandingTotal;

        $html = '<div style="font-family:system-ui;color:#111827;font-size:14px;">';

        // Decont line
        $payoutUrl = PayoutResource::getUrl('view', ['record' => $record->id]);
        $payoutDate = $record->created_at?->format('d.m.Y');
        $html .= '<div style="display:flex;justify-content:space-between;padding:10px 12px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;margin-bottom:12px;">'
            . '<div>'
            . '<div style="font-size:11px;color:#15803d;text-transform:uppercase;font-weight:600;">Decont net</div>'
            . '<div style="font-weight:600;color:#111827;">'
            . '<a href="' . e($payoutUrl) . '" target="_blank" style="color:#0f766e;text-decoration:underline;">' . e($record->reference) . '</a>'
            . ($record->decont_series ? ' <span style="color:#64748b;">· ' . e($record->decont_series) . '</span>' : '')
            . ($payoutDate ? ' <span style="color:#64748b;font-weight:400;">· ' . e($payoutDate) . '</span>' : '')
            . '</div>'
            . '</div>'
            . '<div style="font-family:monospace;font-weight:700;font-size:16px;color:#15803d;">+ ' . number_format($decontAmount, 2) . ' RON</div>'
            . '</div>';

        // Facturi organizator (outstanding)
        if ($invoices->isNotEmpty()) {
            $html .= '<div style="font-size:11px;color:#92400e;text-transform:uppercase;font-weight:600;margin-bottom:6px;">Facturi organizator emise (de scăzut din decont)</div>';
            foreach ($invoices as $inv) {
                $isOutstanding = in_array($inv->status, ['outstanding', 'new', 'overdue'], true);
                $statusLabel = match ($inv->status) {
                    'outstanding', 'new', 'overdue' => 'de încasat',
                    'paid' => 'încasată',
                    'cancelled' => 'anulată',
                    default => $inv->status,
                };
                $statusColor = $isOutstanding ? '#92400e' : ($inv->status === 'paid' ? '#15803d' : '#64748b');

                $invUrl = OrganizerInvoiceResource::getUrl('edit', ['record' => $inv->id]);
                $items = is_array($inv->meta) ? ($inv->meta['items'] ?? []) : [];

                $html .= '<div style="padding:10px 12px;background:' . ($isOutstanding ? '#fffbeb' : '#f9fafb') . ';border:1px solid ' . ($isOutstanding ? '#fcd34d' : '#e5e7eb') . ';border-radius:8px;margin-bottom:8px;">';
                $html .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">';
                $html .= '<div style="flex:1;">';
                $html .= '<div style="font-weight:600;"><a href="' . e($invUrl) . '" target="_blank" style="color:#0f766e;text-decoration:underline;">' . e($inv->number) . '</a> <span style="font-size:11px;color:' . $statusColor . ';font-weight:500;">· ' . e($statusLabel) . '</span></div>';

                if (!empty($items)) {
                    $html .= '<ul style="margin:6px 0 0 16px;padding:0;font-size:12px;color:#475569;list-style:disc;">';
                    foreach ($items as $it) {
                        $desc = (string) ($it['description'] ?? '');
                        $qty = (int) ($it['quantity'] ?? 0);
                        $amt = (float) ($it['amount'] ?? 0);
                        // Trim the long contract / event fragment for the bullets —
                        // keep just the lead (first ~80 chars) so the table stays
                        // readable.
                        $shortDesc = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 97) . '…' : $desc;
                        $html .= '<li>' . e($shortDesc) . ' — <span style="font-family:monospace;">' . $qty . ' × ' . number_format((float) ($it['unit_price'] ?? 0), 2) . ' = ' . number_format($amt, 2) . ' RON</span></li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</div>';
                $html .= '<div style="font-family:monospace;font-weight:600;color:' . ($isOutstanding ? '#92400e' : '#64748b') . ';white-space:nowrap;">' . ($isOutstanding ? '− ' : '') . number_format((float) $inv->amount, 2) . ' RON</div>';
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '<div style="display:flex;justify-content:space-between;padding:6px 12px;color:#64748b;font-size:12px;border-top:1px dashed #cbd5e1;margin-top:4px;">'
                . '<div>Subtotal facturi de încasat</div>'
                . '<div style="font-family:monospace;">− ' . number_format((float) $invoiceOutstandingTotal, 2) . ' RON</div>'
                . '</div>';
        } else {
            $html .= '<div style="padding:8px 12px;background:#f9fafb;border:1px dashed #cbd5e1;border-radius:8px;font-size:12px;color:#64748b;margin-bottom:12px;">'
                . 'Nu există facturi organizator emise pe acest decont (POS / refund commission).'
                . '</div>';
        }

        // Totalul de plată
        $balanceColor = $balance >= 0 ? '#15803d' : '#dc2626';
        $balancePrefix = $balance >= 0 ? '' : '';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:#ecfdf5;border:2px solid #10b981;border-radius:10px;margin-top:14px;">'
            . '<div style="font-size:13px;color:#064e3b;font-weight:600;text-transform:uppercase;">De plătit (transfer real)</div>'
            . '<div style="font-family:monospace;font-weight:700;font-size:20px;color:' . $balanceColor . ';">' . $balancePrefix . number_format($balance, 2) . ' RON</div>'
            . '</div>';

        if ($balance < 0) {
            $html .= '<div style="margin-top:8px;padding:8px 12px;background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:12px;">'
                . '⚠ Sold negativ — facturile depășesc decontul. Organizatorul datorează marketplace-ului. Verifică situația înainte de a marca achitat.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    protected function otherEventPayouts(MarketplacePayout $record): \Illuminate\Support\Collection
    {
        if (!$record->event_id || !$record->marketplace_organizer_id) {
            return collect();
        }

        return MarketplacePayout::query()
            ->where('event_id', $record->event_id)
            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
            ->where('id', '!=', $record->id)
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'decont_series', 'amount', 'status', 'created_at']);
    }

    protected function renderOtherPayouts(MarketplacePayout $record): string
    {
        $rows = $this->otherEventPayouts($record);
        if ($rows->isEmpty()) {
            return '';
        }

        $html = '<div style="font-family:system-ui;font-size:13px;">';
        foreach ($rows as $p) {
            $url = PendingPaymentResource::getUrl('view', ['record' => $p->id]);
            $statusLabel = match ($p->status) {
                'pending', 'approved', 'processing' => 'în așteptare',
                'completed' => 'achitat',
                'rejected' => 'respins',
                default => $p->status,
            };
            $statusColor = match ($p->status) {
                'pending', 'approved', 'processing' => '#92400e',
                'completed' => '#15803d',
                'rejected' => '#991b1b',
                default => '#64748b',
            };
            $date = $p->created_at?->format('d.m.Y');
            $html .= '<div style="display:flex;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #f1f5f9;">'
                . '<div>'
                . '<a href="' . e($url) . '" style="color:#0f766e;text-decoration:underline;font-weight:500;">' . e($p->reference) . '</a>'
                . ($p->decont_series ? ' <span style="color:#64748b;font-size:11px;">· ' . e($p->decont_series) . '</span>' : '')
                . ($date ? ' <span style="color:#94a3b8;font-size:11px;">· ' . e($date) . '</span>' : '')
                . ' <span style="color:' . $statusColor . ';font-size:11px;font-weight:500;">· ' . e($statusLabel) . '</span>'
                . '</div>'
                . '<div style="font-family:monospace;font-weight:500;">' . number_format((float) $p->amount, 2) . ' RON</div>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
