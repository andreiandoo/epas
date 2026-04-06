<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\PayoutResource\Pages;
use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceAdmin;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Support\Enums\IconPosition;

class PayoutResource extends Resource
{
    protected static ?string $model = MarketplacePayout::class;
    protected static ?string $navigationLabel = 'Deconturi';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Payout Request')
                    ->schema([
                        Forms\Components\Placeholder::make('reference_display')
                            ->label('Reference')
                            ->content(fn (?MarketplacePayout $record): string => $record?->reference ?? '-'),

                        Forms\Components\Placeholder::make('organizer_display')
                            ->label('Organizer')
                            ->content(fn (?MarketplacePayout $record): string => $record?->organizer?->name ?? '-'),

                        Forms\Components\Placeholder::make('event_display')
                            ->label('Event')
                            ->content(function (?MarketplacePayout $record): string {
                                if (!$record?->event) return 'General payout (no specific event)';
                                $title = $record->event->title;
                                return is_array($title)
                                    ? ($title['ro'] ?? $title['en'] ?? reset($title) ?? 'Untitled')
                                    : ($title ?? 'Untitled');
                            }),

                        Forms\Components\Placeholder::make('amount_display')
                            ->label('Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?MarketplacePayout $record): string => $record?->status_label ?? '-'),

                        Forms\Components\Placeholder::make('created_at_display')
                            ->label('Requested At')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->created_at?->format('d.m.Y H:i') ?? '-'),
                    ])
                    ->columns(3),

                Section::make('Amount Breakdown')
                    ->schema([
                        Forms\Components\Placeholder::make('gross_amount_display')
                            ->label('Gross Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->gross_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('commission_amount_display')
                            ->label('Commission')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->commission_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('fees_amount_display')
                            ->label('Fees')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->fees_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('net_amount_display')
                            ->label('Net Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),
                    ])
                    ->columns(4),

                Section::make('Payout Method')
                    ->schema([
                        Forms\Components\Placeholder::make('bank_name_display')
                            ->label('Bank')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['bank_name'] ?? '-'),

                        Forms\Components\Placeholder::make('iban_display')
                            ->label('IBAN')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['iban'] ?? '-'),

                        Forms\Components\Placeholder::make('account_holder_display')
                            ->label('Account Holder')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['account_holder'] ?? '-'),
                    ])
                    ->columns(3),

                Section::make('Organizer Notes')
                    ->schema([
                        Forms\Components\Placeholder::make('organizer_notes_display')
                            ->label('')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->organizer_notes ?? 'No notes from organizer')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn (?MarketplacePayout $record): bool => empty($record?->organizer_notes)),

                Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ])->columns(1);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->components([
                // ========== MAIN INFO ==========
                \Filament\Schemas\Components\Grid::make(3)->schema([
                    // LEFT: Payout details (2/3)
                    \Filament\Schemas\Components\Group::make()->columnSpan(2)->schema([
                        Section::make('Decont')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Infolists\Components\TextEntry::make('reference')
                                    ->label('Referință')
                                    ->copyable()
                                    ->icon('heroicon-o-clipboard-document')
                                    ->iconPosition(IconPosition::After),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'info',
                                        'processing' => 'primary',
                                        'completed' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'gray',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('organizer.name')
                                    ->label('Organizator')
                                    ->url(fn ($record) => $record->marketplace_organizer_id
                                        ? OrganizerResource::getUrl('edit', ['record' => $record->marketplace_organizer_id])
                                        : null)
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('event.title')
                                    ->label('Eveniment')
                                    ->placeholder('Decont general')
                                    ->formatStateUsing(fn ($state) => is_array($state)
                                        ? ($state['ro'] ?? $state['en'] ?? reset($state) ?? 'Untitled')
                                        : $state)
                                    ->url(fn ($record) => $record->event_id
                                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                                        : null)
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('source')
                                    ->label('Sursă')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'organizer' => 'primary', 'manual' => 'warning', default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Creat la')
                                    ->dateTime('d.m.Y H:i'),
                            ])
                            ->columns(3),

                        // Ticket breakdown table
                        Section::make('Detalii bilete')
                            ->icon('heroicon-o-ticket')
                            ->schema([
                                Infolists\Components\ViewEntry::make('ticket_breakdown_view')
                                    ->label('')
                                    ->view('filament.infolists.payout-ticket-breakdown')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn ($record) => !empty($record->ticket_breakdown)),

                        // Financial summary with full event context
                        Section::make('Rezumat financiar')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                // 1. Situația vânzărilor la momentul decontului
                                Infolists\Components\TextEntry::make('event_sales_status')
                                    ->label('')
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(function ($state, $record) {
                                        if (!$record->event_id) return '';
                                        $event = \App\Models\Event::with('ticketTypes')->find($record->event_id);
                                        if (!$event) return '';

                                        $financials = \App\Filament\Marketplace\Resources\PayoutResource\Pages\ListPayouts::calculateEventFinancials($event);
                                        $gross = (float) ($financials['gross'] ?? 0);
                                        $commission = (float) ($financials['commission'] ?? 0);
                                        $net = (float) ($financials['net'] ?? 0);
                                        $refunds = (float) ($financials['refunds'] ?? 0);
                                        $paid = (float) ($financials['paid'] ?? 0);
                                        $pending = (float) ($financials['pending'] ?? 0);
                                        $balance = (float) ($financials['balance'] ?? 0);

                                        $fmt = fn ($v) => number_format($v, 2, ',', '.') . ' RON';

                                        // Calculate commission already deducted in payouts
                                        $paidCommission = (float) MarketplacePayout::where('event_id', $record->event_id)
                                            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
                                            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
                                            ->sum('commission_amount');
                                        $remainingCommission = max(0, $commission - $paidCommission);

                                        return new \Illuminate\Support\HtmlString("
                                        <div style='display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:16px;'>
                                            <div style='padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;'>
                                                <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;'>Vânzări totale eveniment</div>
                                                <div style='font-size:16px;font-weight:700;color:#1a1a2e;'>{$fmt($gross)}</div>
                                                <div style='font-size:11px;color:#666;margin-top:2px;'>Comision total: {$fmt($commission)}</div>
                                                <div style='font-size:11px;color:#666;'>Net total: {$fmt($net)}</div>
                                                " . ($refunds > 0 ? "<div style='font-size:11px;color:#dc2626;'>Returnări: -{$fmt($refunds)}</div>" : "") . "
                                            </div>
                                            <div style='padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fef3c7;'>
                                                <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;'>Decontat</div>
                                                <div style='font-size:16px;font-weight:700;color:#92400e;'>{$fmt($paid + $pending)}</div>
                                                <div style='font-size:11px;color:#666;margin-top:2px;'>Plătit: {$fmt($paid)}</div>
                                                " . ($pending > 0 ? "<div style='font-size:11px;color:#d97706;'>În așteptare: {$fmt($pending)}</div>" : "") . "
                                                <div style='font-size:11px;color:#666;'>Comision încasat: {$fmt($paidCommission)}</div>
                                            </div>
                                            <div style='padding:12px;border:1px solid #059669;border-radius:8px;background:#f0fdf4;'>
                                                <div style='font-size:10px;color:#059669;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;'>Disponibil</div>
                                                <div style='font-size:16px;font-weight:700;color:#059669;'>{$fmt($balance)}</div>
                                                <div style='font-size:11px;color:#666;margin-top:2px;'>Comision rămas: {$fmt($remainingCommission)}</div>
                                                <div style='font-size:11px;color:#666;'>Net rămas: {$fmt($balance)}</div>
                                            </div>
                                        </div>
                                        ");
                                    })
                                    ->html()
                                    ->columnSpanFull(),

                                // 2. Ce s-a cerut în acest decont
                                Infolists\Components\TextEntry::make('gross_amount')
                                    ->label('Suma brută decont')
                                    ->money('RON'),

                                Infolists\Components\TextEntry::make('commission_amount')
                                    ->label('Comision decont')
                                    ->formatStateUsing(fn ($state) => '-' . number_format((float) $state, 2) . ' RON')
                                    ->color('danger'),

                                Infolists\Components\TextEntry::make('fees_amount')
                                    ->label('Taxe')
                                    ->formatStateUsing(fn ($state) => '-' . number_format((float) $state, 2) . ' RON')
                                    ->color('danger')
                                    ->visible(fn ($record) => (float) $record->fees_amount > 0),

                                Infolists\Components\TextEntry::make('adjustments_amount')
                                    ->label('Ajustări')
                                    ->formatStateUsing(fn ($state, $record) => ($state >= 0 ? '+' : '') . number_format((float) $state, 2) . ' RON' . ($record->adjustments_note ? " ({$record->adjustments_note})" : ''))
                                    ->visible(fn ($record) => (float) $record->adjustments_amount != 0),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Sumă netă (de plată)')
                                    ->money('RON')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('success'),
                            ])
                            ->columns(4),

                        // Payment info (for completed)
                        Section::make('Plată')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_reference')
                                    ->label('Referință plată')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Metodă plată'),
                                Infolists\Components\TextEntry::make('completed_at')
                                    ->label('Finalizat la')
                                    ->dateTime('d.m.Y H:i'),
                                Infolists\Components\TextEntry::make('payment_notes')
                                    ->label('Note plată')
                                    ->columnSpanFull()
                                    ->visible(fn ($record) => !empty($record->payment_notes)),
                            ])
                            ->columns(3)
                            ->visible(fn ($record) => $record->payment_reference),

                        // Rejection details
                        Section::make('Respingere')
                            ->icon('heroicon-o-x-circle')
                            ->schema([
                                Infolists\Components\TextEntry::make('rejection_reason')
                                    ->label('Motiv')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('rejectedByUser.name')
                                    ->label('Respins de'),
                                Infolists\Components\TextEntry::make('rejected_at')
                                    ->label('Data')
                                    ->dateTime('d.m.Y H:i'),
                            ])
                            ->columns(2)
                            ->visible(fn ($record) => $record->status === 'rejected'),

                        // Notes
                        Section::make('Note')
                            ->schema([
                                Infolists\Components\TextEntry::make('organizer_notes')
                                    ->label('Note organizator')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('admin_notes')
                                    ->label('Note admin')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(fn ($record) => empty($record->organizer_notes) && empty($record->admin_notes)),
                    ]),

                    // RIGHT: Sidebar (1/3)
                    \Filament\Schemas\Components\Group::make()->columnSpan(1)->schema([
                        // Payout method
                        Section::make('Cont bancar')
                            ->icon('heroicon-o-building-library')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('payout_method.bank_name')
                                    ->label('Bancă'),
                                Infolists\Components\TextEntry::make('payout_method.iban')
                                    ->label('IBAN')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('payout_method.account_holder')
                                    ->label('Titular')
                                    ->copyable(),
                            ]),

                        // Event decont context
                        Section::make('Context eveniment')
                            ->icon('heroicon-o-chart-bar')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('decont_number')
                                    ->label('Nr. decont pe eveniment')
                                    ->getStateUsing(function ($record) {
                                        if (!$record->event_id) return '—';
                                        $total = MarketplacePayout::where('event_id', $record->event_id)
                                            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
                                            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
                                            ->count();
                                        $nr = MarketplacePayout::where('event_id', $record->event_id)
                                            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
                                            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
                                            ->where('id', '<=', $record->id)
                                            ->count();
                                        return "{$nr} din {$total}";
                                    }),
                                Infolists\Components\TextEntry::make('event_payouts_summary')
                                    ->label('Deconturi pe eveniment')
                                    ->getStateUsing(function ($record) {
                                        if (!$record->event_id) return '—';
                                        $payouts = MarketplacePayout::where('event_id', $record->event_id)
                                            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
                                            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
                                            ->orderBy('created_at')
                                            ->get(['id', 'reference', 'amount', 'status']);
                                        return $payouts->map(fn ($p) => $p->reference . ': ' . number_format((float) $p->amount, 2) . ' RON (' . $p->status . ')' . ($p->id === $record->id ? ' ←' : ''))->implode("\n");
                                    })
                                    ->markdown(),
                                Infolists\Components\TextEntry::make('event_remaining_balance')
                                    ->label('Sold disponibil eveniment')
                                    ->getStateUsing(function ($record) {
                                        if (!$record->event_id || !$record->organizer) return '—';
                                        $event = \App\Models\Event::find($record->event_id);
                                        if (!$event) return '—';
                                        $balance = \App\Filament\Marketplace\Resources\PayoutResource\Pages\ListPayouts::calculateEventBalance($event);
                                        return number_format($balance, 2) . ' RON';
                                    })
                                    ->color('success')
                                    ->weight('bold'),
                            ])
                            ->visible(fn ($record) => $record->event_id),

                        // Timeline
                        Section::make('Cronologie')
                            ->icon('heroicon-o-clock')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label(fn ($record) => $record->source === 'manual' ? 'Creat manual' : ($record->source === 'organizer' ? 'Solicitat de organizator' : 'Solicitat'))
                                    ->dateTime('d.m.Y H:i'),
                                Infolists\Components\TextEntry::make('source')
                                    ->label('Tip')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'manual' => 'Manual (admin)',
                                        'organizer' => 'Solicitat de organizator',
                                        'automated' => 'Automat',
                                        default => ucfirst($state),
                                    })
                                    ->color(fn ($state) => match ($state) {
                                        'manual' => 'warning',
                                        'organizer' => 'primary',
                                        'automated' => 'info',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('Aprobat')
                                    ->dateTime('d.m.Y H:i')
                                    ->visible(fn ($record) => $record->approved_at),
                                Infolists\Components\TextEntry::make('processed_at')
                                    ->label('Procesat')
                                    ->dateTime('d.m.Y H:i')
                                    ->visible(fn ($record) => $record->processed_at),
                                Infolists\Components\TextEntry::make('completed_at')
                                    ->label('Finalizat')
                                    ->dateTime('d.m.Y H:i')
                                    ->visible(fn ($record) => $record->completed_at),
                                Infolists\Components\TextEntry::make('period_start')
                                    ->label('Perioadă')
                                    ->formatStateUsing(fn ($state, $record) => ($record->period_start?->format('d.m.Y') ?? '—') . ' → ' . ($record->period_end?->format('d.m.Y') ?? '—'))
                                    ->visible(fn ($record) => $record->period_start),
                            ]),

                        // Decont document
                        Section::make('Document decont')
                            ->icon('heroicon-o-document-text')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('decontDocument.title')
                                    ->label('Titlu'),
                                Infolists\Components\TextEntry::make('decontDocument.issued_at')
                                    ->label('Generat la')
                                    ->dateTime('d.m.Y H:i'),
                                Infolists\Components\TextEntry::make('decontDocument.formatted_file_size')
                                    ->label('Mărime'),
                            ])
                            ->visible(fn ($record) => $record->decontDocument !== null),

                        // Invoice
                        Section::make('Factură')
                            ->icon('heroicon-o-document-currency-dollar')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('invoice.number')
                                    ->label('Număr'),
                                Infolists\Components\TextEntry::make('invoice.amount')
                                    ->label('Suma')
                                    ->money('RON'),
                                Infolists\Components\TextEntry::make('invoice.status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'warning'),
                            ])
                            ->visible(fn ($record) => $record->invoice !== null),
                    ]),
                ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('organizer', function ($q) use ($term) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                              ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$term]);
                        });
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->placeholder('General')
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state['ro'] ?? $state['en'] ?? reset($state) ?? 'Untitled')
                        : $state)
                    ->limit(25)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('event', function ($q) use ($term) {
                            $isPgsql = \DB::getDriverName() === 'pgsql';
                            $q->whereRaw(
                                $isPgsql
                                    ? "LOWER(title::jsonb->>'ro') LIKE ? OR LOWER(title::jsonb->>'en') LIKE ?"
                                    : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?",
                                [$term, $term]
                            );
                        });
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('RON')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processing' => 'primary',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'organizer' => 'primary',
                        'manual' => 'warning',
                        'automated' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'organizer' => 'Organizer',
                        'manual' => 'Manual',
                        'automated' => 'Automat',
                        default => ucfirst($state),
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeApproved())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->approve($admin->id);
                    }),

                Action::make('process')
                    ->label('Mark Processing')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeProcessed())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->markAsProcessing($admin->id);
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeCompleted())
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Payment Notes')
                            ->rows(2),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->reject($admin->id, $data['rejection_reason']);
                    }),

                ViewAction::make(),

                Action::make('delete')
                    ->label('Șterge')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Decontul va fi șters și valorile (sold, bilete decontate) vor fi returnate organizatorului.')
                    ->action(function (MarketplacePayout $record): void {
                        static::reversePayout($record);
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_delete')
                        ->label('Șterge selectate')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Deconturile selectate vor fi șterse și valorile returnate organizatorilor.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            foreach ($records as $record) {
                                static::reversePayout($record);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }

    /**
     * Reverse a payout: return balance, delete related documents, invoices, transactions.
     */
    public static function reversePayout(MarketplacePayout $payout): void
    {
        \DB::beginTransaction();
        try {
            $organizer = $payout->organizer;

            // 1. Return balance to organizer if payout was approved/completed
            if (in_array($payout->status, ['approved', 'processing', 'completed']) && $organizer) {
                $organizer->returnPendingBalance($payout->amount);
            }

            // 2. Reverse ticket_breakdown: decrement quota_sold on ticket types
            $ticketBreakdown = $payout->ticket_breakdown ?? [];
            foreach ($ticketBreakdown as $item) {
                $ticketTypeId = $item['ticket_type_id'] ?? null;
                $qty = $item['quantity'] ?? $item['tickets'] ?? 0;
                if ($ticketTypeId && $qty > 0) {
                    \DB::table('ticket_types')
                        ->where('id', $ticketTypeId)
                        ->where('quota_sold', '>=', $qty)
                        ->decrement('quota_sold', $qty);
                }
            }

            // 3. Delete decont document + PDF file
            $decont = $payout->decontDocument;
            if ($decont) {
                if ($decont->file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($decont->file_path);
                }
                $decont->delete();
            }

            // 4. Delete associated invoice
            $invoice = $payout->invoice;
            if ($invoice) {
                $invoice->delete();
            }

            // 5. Delete transactions
            $payout->transactions()->delete();

            // 6. Delete the payout itself
            $payout->delete();

            \DB::commit();

            \Filament\Notifications\Notification::make()
                ->title('Decont șters')
                ->body('Valorile au fost returnate organizatorului.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to reverse payout', ['payout_id' => $payout->id, 'error' => $e->getMessage()]);

            \Filament\Notifications\Notification::make()
                ->title('Eroare la ștergere')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
