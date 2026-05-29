<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Filament\Marketplace\Resources\PayoutResource;
use App\Filament\Marketplace\Resources\PendingPaymentResource\Pages;
use App\Models\Invoice;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * "De plătit" page — operator's focused payment queue.
 *
 * A second Resource on top of the existing MarketplacePayout model (same
 * underlying table). This view filters to active payment items (status in
 * pending/approved/processing/completed/rejected, event-attached only) and
 * exposes a payment-centric workflow:
 *   - Listă: ce trebuie plătit azi (default filter: "în așteptare")
 *   - View: organizator, cont bancar, sold defalcat (decont − factură),
 *     2 acțiuni — Achitat / Respins — care marchează atât decontul cât și
 *     factura organizator linkată dintr-un singur transfer real.
 */
class PendingPaymentResource extends Resource
{
    protected static ?string $model = MarketplacePayout::class;
    protected static ?string $navigationLabel = 'De plătit';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'reference';

    /**
     * Badge: număr de plăți în așteptare pentru marketplace-ul curent.
     */
    public static function getNavigationBadge(): ?string
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) {
            return null;
        }

        $count = MarketplacePayout::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->whereNotNull('event_id')
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Restrict to:
     *   - marketplace_client of the current admin
     *   - event-attached payouts only (general/organizer-level payouts stay
     *     in PayoutResource)
     *   - statuses we want to surface as "to pay": pending → in așteptare,
     *     completed → achitat (history), rejected → respins (history).
     */
    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id)
            ->whereNotNull('event_id')
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed', 'rejected']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_with_meta')
                    ->label('Eveniment')
                    ->state(function ($record) {
                        $event = $record->event;
                        if (!$event) {
                            return '—';
                        }
                        $t = $event->title;
                        return is_array($t)
                            ? ($t['ro'] ?? $t['en'] ?? (reset($t) ?: 'Untitled'))
                            : ($t ?? 'Untitled');
                    })
                    ->description(function ($record) {
                        $event = $record->event;
                        if (!$event) {
                            return null;
                        }
                        $parts = [];
                        $start = $event->start_date?->format('d.m.Y');
                        $end = $event->end_date?->format('d.m.Y');
                        if ($start) {
                            $parts[] = ($end && $end !== $start) ? "{$start} – {$end}" : $start;
                        }
                        if ($event->venue) {
                            $vn = $event->venue->name;
                            $vname = is_array($vn) ? ($vn['ro'] ?? $vn['en'] ?? null) : $vn;
                            if ($vname) {
                                $parts[] = $vname;
                            }
                            if ($event->venue->city) {
                                $parts[] = $event->venue->city;
                            }
                        }
                        return implode(' · ', $parts) ?: null;
                    })
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record->id]))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('event', function ($q) use ($term) {
                            // Title is a translatable JSON column — search via raw cast
                            $q->whereRaw('LOWER(CAST(title AS TEXT)) LIKE ?', [$term]);
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('organizer.company_name')
                    ->label('Organizator')
                    ->state(fn ($record) => $record->organizer?->company_name
                        ?? $record->organizer?->name
                        ?? '—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referință')
                    ->description(fn ($record) => $record->decont_series)
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('sold_de_plata')
                    ->label('Sold de plată')
                    ->state(function ($record) {
                        return self::computeBalance($record);
                    })
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2) . ' RON')
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Sort by amount as proxy — the invoice subtraction is rare
                        // enough that exact ordering by computed balance isn't worth
                        // a join here.
                        return $query->orderBy('amount', $direction);
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn ($record) => match ($record->status) {
                        'pending', 'approved', 'processing' => 'În așteptare',
                        'completed' => 'Achitat',
                        'rejected' => 'Respins',
                        default => $record->status,
                    })
                    ->color(fn ($state) => match ($state) {
                        'În așteptare' => 'warning',
                        'Achitat' => 'success',
                        'Respins' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data decont')
                    ->date('d.m.Y'),
            ])
            ->filters([
                // Status filtering is handled by the toolbar tabs (În așteptare /
                // Achitat / Respins) on the list page — see ListPendingPayments::getTabs().
                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizator')
                    ->relationship('organizer', 'company_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('Vezi'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * View page infolist — static on the Resource so Filament 4 picks it up
     * (a `public function infolist()` defined on the page didn't render the
     * Grid sidebar; matching PayoutResource's pattern fixes it).
     *
     * Layout: Grid(4) with a Group(colSpan=3) for the main content and a
     * Group(colSpan=1) sidebar that carries the Acțiuni section (Achitat /
     * Respins). Closures call back into static helpers below.
     */
    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->components([
                \Filament\Schemas\Components\Grid::make(4)->schema([
                    // MAIN (3/4): Cont bancar → Sold → Organizator → Eveniment → status info → Alte deconturi
                    \Filament\Schemas\Components\Group::make()->columnSpan(3)->schema([

                        Section::make('Cont bancar')
                            ->icon('heroicon-o-building-library')
                            ->columns(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('bank_name')
                                    ->label('Bancă')
                                    ->state(fn ($record) => static::getPrimaryBank($record)?->bank_name ?? '—'),
                                Infolists\Components\TextEntry::make('bank_iban')
                                    ->label('IBAN')
                                    ->state(fn ($record) => static::getPrimaryBank($record)?->iban ?? '—')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('bank_holder')
                                    ->label('Titular')
                                    ->state(fn ($record) => static::getPrimaryBank($record)?->account_holder ?? '—'),
                                Infolists\Components\TextEntry::make('bank_warning')
                                    ->hiddenLabel()
                                    ->visible(fn ($record) => static::getPrimaryBank($record) === null)
                                    ->state(fn () => new HtmlString(
                                        '<div style="padding:8px 12px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;color:#92400e;font-size:13px;">'
                                        . '⚠ Organizatorul nu are setat un cont bancar primar — cere-i să-l configureze înainte de transfer.'
                                        . '</div>'
                                    ))
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Sold de plată')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Infolists\Components\TextEntry::make('balance_breakdown')
                                    ->hiddenLabel()
                                    ->state(fn ($record) => new HtmlString(static::renderBalanceBreakdown($record)))
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Organizator')
                            ->icon('heroicon-o-building-office')
                            ->columns(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('organizer.company_name')
                                    ->label('Companie')
                                    ->state(fn ($record) => $record->organizer?->company_name
                                        ?? $record->organizer?->name
                                        ?? '—')
                                    ->url(fn ($record) => $record->marketplace_organizer_id
                                        ? OrganizerResource::getUrl('edit', ['record' => $record->marketplace_organizer_id])
                                        : null)
                                    ->color('primary')
                                    ->weight('bold'),
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

                        Section::make('Eveniment')
                            ->icon('heroicon-o-calendar-days')
                            ->columns(2)
                            ->visible(fn ($record) => $record->event_id !== null)
                            ->schema([
                                Infolists\Components\TextEntry::make('event.title')
                                    ->label('Titlu')
                                    ->state(function ($record) {
                                        $t = $record->event?->title;
                                        return is_array($t)
                                            ? ($t['ro'] ?? $t['en'] ?? (reset($t) ?: 'Untitled'))
                                            : ($t ?? 'Untitled');
                                    })
                                    ->url(fn ($record) => $record->event_id
                                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                                        : null)
                                    ->color('primary')
                                    ->weight('bold')
                                    ->openUrlInNewTab(),
                                Infolists\Components\TextEntry::make('event_date_label')
                                    ->label('Data eveniment')
                                    ->state(function ($record) {
                                        $event = $record->event;
                                        if (!$event) return '—';
                                        $start = $event->start_date?->format('d.m.Y');
                                        $end = $event->end_date?->format('d.m.Y');
                                        if (!$start) return 'TBD';
                                        return ($end && $end !== $start) ? "{$start} – {$end}" : $start;
                                    }),
                                Infolists\Components\TextEntry::make('event.venue.name')
                                    ->label('Locație')
                                    ->state(function ($record) {
                                        $v = $record->event?->venue?->name;
                                        $vname = is_array($v) ? ($v['ro'] ?? $v['en'] ?? null) : $v;
                                        return $vname ?: '—';
                                    }),
                                Infolists\Components\TextEntry::make('event.venue.city')
                                    ->label('Oraș')
                                    ->state(fn ($record) => $record->event?->venue?->city ?? '—'),
                                Infolists\Components\TextEntry::make('event.venue.address')
                                    ->label('Adresă locație')
                                    ->state(fn ($record) => $record->event?->venue?->address ?? '—')
                                    ->columnSpan(2),
                            ]),

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

                        Section::make('Alte deconturi pe acest eveniment')
                            ->icon('heroicon-o-rectangle-stack')
                            ->compact()
                            ->visible(fn ($record) => static::otherEventPayouts($record)->isNotEmpty())
                            ->schema([
                                Infolists\Components\TextEntry::make('other_payouts_list')
                                    ->hiddenLabel()
                                    ->state(fn ($record) => new HtmlString(static::renderOtherPayouts($record)))
                                    ->columnSpanFull(),
                            ]),
                    ]),

                    // SIDEBAR (1/4): Acțiuni
                    \Filament\Schemas\Components\Group::make()->columnSpan(1)->schema([
                        Section::make('Acțiuni')
                            ->icon('heroicon-o-bolt')
                            ->compact()
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
                                            $balance = static::computeBalance($record);
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
                                            $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);

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

                                            $livewire->redirect(static::getUrl('index'));
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

                                            $record->reject($admin->id, $data['reason']);

                                            $decont = $record->decontDocument;
                                            if ($decont) {
                                                if ($decont->file_path) {
                                                    Storage::disk('public')->delete($decont->file_path);
                                                }
                                                $decont->delete();
                                            }

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

                                            $livewire->redirect(static::getUrl('index'));
                                        }),
                                ])->fullWidth(),
                            ]),
                    ]),
                ]),
            ]);
    }

    // ───────────────────────────────────────────────────────────────────────
    // View helpers (static so the infolist closures can use static::method)
    // ───────────────────────────────────────────────────────────────────────

    public static function getPrimaryBank(MarketplacePayout $record): ?MarketplaceOrganizerBankAccount
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
     * Dark-themed "decont − facturi = de plată" breakdown — numbers are the
     * most important content on the page, so they read off a high-contrast
     * surface (#0f172a) with bright lime / amber / green / red accents.
     */
    public static function renderBalanceBreakdown(MarketplacePayout $record): string
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

        $html = '<div style="font-family:system-ui;background:#0f172a;color:#e2e8f0;font-size:14px;padding:16px;border-radius:10px;border:1px solid #1e293b;">';

        $payoutUrl = PayoutResource::getUrl('view', ['record' => $record->id]);
        $payoutDate = $record->created_at?->format('d.m.Y');
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#172554;border:1px solid #1d4ed8;border-radius:8px;margin-bottom:12px;">'
            . '<div>'
            . '<div style="font-size:11px;color:#93c5fd;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Decont net</div>'
            . '<div style="font-weight:600;color:#f1f5f9;margin-top:2px;">'
            . '<a href="' . e($payoutUrl) . '" target="_blank" style="color:#93c5fd;text-decoration:underline;">' . e($record->reference) . '</a>'
            . ($record->decont_series ? ' <span style="color:#94a3b8;">· ' . e($record->decont_series) . '</span>' : '')
            . ($payoutDate ? ' <span style="color:#94a3b8;font-weight:400;">· ' . e($payoutDate) . '</span>' : '')
            . '</div>'
            . '</div>'
            . '<div style="font-family:monospace;font-weight:700;font-size:18px;color:#86efac;">+ ' . number_format($decontAmount, 2) . ' RON</div>'
            . '</div>';

        if ($invoices->isNotEmpty()) {
            $html .= '<div style="font-size:11px;color:#fcd34d;text-transform:uppercase;font-weight:600;letter-spacing:.04em;margin:8px 0 8px 2px;">Facturi organizator emise (de scăzut din decont)</div>';
            foreach ($invoices as $inv) {
                $isOutstanding = in_array($inv->status, ['outstanding', 'new', 'overdue'], true);
                $statusLabel = match ($inv->status) {
                    'outstanding', 'new', 'overdue' => 'de încasat',
                    'paid' => 'încasată',
                    'cancelled' => 'anulată',
                    default => $inv->status,
                };
                $statusColor = $isOutstanding ? '#fcd34d' : ($inv->status === 'paid' ? '#86efac' : '#94a3b8');

                $invUrl = OrganizerInvoiceResource::getUrl('edit', ['record' => $inv->id]);
                $items = is_array($inv->meta) ? ($inv->meta['items'] ?? []) : [];
                $cardBg = $isOutstanding ? '#422006' : '#1e293b';
                $cardBorder = $isOutstanding ? '#a16207' : '#334155';
                $amountColor = $isOutstanding ? '#fcd34d' : '#94a3b8';

                $html .= '<div style="padding:11px 14px;background:' . $cardBg . ';border:1px solid ' . $cardBorder . ';border-radius:8px;margin-bottom:8px;">';
                $html .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">';
                $html .= '<div style="flex:1;">';
                $html .= '<div style="font-weight:600;"><a href="' . e($invUrl) . '" target="_blank" style="color:#93c5fd;text-decoration:underline;">' . e($inv->number) . '</a> <span style="font-size:11px;color:' . $statusColor . ';font-weight:500;">· ' . e($statusLabel) . '</span></div>';

                if (!empty($items)) {
                    $html .= '<ul style="margin:6px 0 0 18px;padding:0;font-size:12px;color:#cbd5e1;list-style:disc;">';
                    foreach ($items as $it) {
                        $desc = (string) ($it['description'] ?? '');
                        $qty = (int) ($it['quantity'] ?? 0);
                        $amt = (float) ($it['amount'] ?? 0);
                        $shortDesc = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 97) . '…' : $desc;
                        $html .= '<li style="margin-bottom:3px;">' . e($shortDesc) . ' — <span style="font-family:monospace;color:#e2e8f0;">' . $qty . ' × ' . number_format((float) ($it['unit_price'] ?? 0), 2) . ' = ' . number_format($amt, 2) . ' RON</span></li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</div>';
                $html .= '<div style="font-family:monospace;font-weight:700;font-size:16px;color:' . $amountColor . ';white-space:nowrap;">' . ($isOutstanding ? '− ' : '') . number_format((float) $inv->amount, 2) . ' RON</div>';
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '<div style="display:flex;justify-content:space-between;padding:8px 14px;color:#cbd5e1;font-size:12px;border-top:1px dashed #475569;margin-top:6px;">'
                . '<div>Subtotal facturi de încasat</div>'
                . '<div style="font-family:monospace;font-weight:600;color:#fcd34d;">− ' . number_format((float) $invoiceOutstandingTotal, 2) . ' RON</div>'
                . '</div>';
        } else {
            $html .= '<div style="padding:10px 14px;background:#1e293b;border:1px dashed #475569;border-radius:8px;font-size:12px;color:#94a3b8;margin-bottom:12px;">'
                . 'Nu există facturi organizator emise pe acest decont (POS / refund commission).'
                . '</div>';
        }

        $balanceColor = $balance >= 0 ? '#22c55e' : '#ef4444';
        $accentBg = $balance >= 0 ? '#052e16' : '#450a0a';
        $accentBorder = $balance >= 0 ? '#16a34a' : '#dc2626';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:16px 18px;background:' . $accentBg . ';border:2px solid ' . $accentBorder . ';border-radius:10px;margin-top:14px;">'
            . '<div style="font-size:13px;color:#e2e8f0;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">De plătit (transfer real)</div>'
            . '<div style="font-family:monospace;font-weight:800;font-size:22px;color:' . $balanceColor . ';">' . number_format($balance, 2) . ' RON</div>'
            . '</div>';

        if ($balance < 0) {
            $html .= '<div style="margin-top:10px;padding:10px 12px;background:#450a0a;border:1px solid #dc2626;border-radius:6px;color:#fca5a5;font-size:12px;">'
                . '⚠ Sold negativ — facturile depășesc decontul. Organizatorul datorează marketplace-ului. Verifică situația înainte de a marca achitat.'
                . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public static function otherEventPayouts(MarketplacePayout $record): \Illuminate\Support\Collection
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

    public static function renderOtherPayouts(MarketplacePayout $record): string
    {
        $rows = static::otherEventPayouts($record);
        if ($rows->isEmpty()) {
            return '';
        }

        $html = '<div style="font-family:system-ui;font-size:13px;">';
        foreach ($rows as $p) {
            $url = static::getUrl('view', ['record' => $p->id]);
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

    /**
     * Compute the net amount the marketplace actually transfers to the
     * organizer for this payout:
     *   payout.amount − Σ(linked organizer-invoice amounts that are still
     *   outstanding/new/overdue).
     *
     * Paid invoices are excluded — they were already netted in a prior
     * settlement. Cancelled too — they no longer represent a real debt.
     */
    public static function computeBalance(MarketplacePayout $payout): float
    {
        $invoiceTotal = (float) \App\Models\Invoice::query()
            ->where('marketplace_payout_id', $payout->id)
            ->where('meta->is_pos_commission', true)
            ->whereIn('status', ['outstanding', 'new', 'overdue'])
            ->sum('amount');

        return round((float) $payout->amount - $invoiceTotal, 2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendingPayments::route('/'),
            'view' => Pages\ViewPendingPayment::route('/{record}'),
        ];
    }
}
