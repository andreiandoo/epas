<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerDocumentResource\Pages;
use App\Filament\Marketplace\Resources\PayoutResource;
use App\Models\MarketplacePayout;
use App\Models\OrganizerDocument;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrganizerDocumentResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = OrganizerDocument::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Documente';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documente';

    protected static ?string $slug = 'organizer-documents';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->with(['organizer', 'event', 'taxTemplate']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Document Header ──
                Section::make('Informații Document')
                    ->icon('heroicon-o-document')
                    ->schema([
                        Forms\Components\Placeholder::make('doc_header')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return '-';

                                $type = OrganizerDocument::TYPES[$record->document_type] ?? $record->document_type;
                                $typeColor = match ($record->document_type) {
                                    'decont' => '#c0392b',
                                    'cerere_avizare' => '#2563eb',
                                    'declaratie_impozite' => '#059669',
                                    'organizer_contract' => '#7c3aed',
                                    default => '#6b7280',
                                };
                                $issuedAt = $record->issued_at?->format('d.m.Y H:i') ?? '-';
                                $createdAt = $record->created_at?->format('d.m.Y H:i') ?? '-';
                                $fileSize = $record->formatted_file_size ?? '-';
                                $templateName = e($record->taxTemplate?->name ?? '-');
                                $title = e($record->title ?? '-');

                                $html = "
                                <div style='display:grid; grid-template-columns:1fr 1fr; gap:12px;'>
                                    <div>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;'>Titlu</div>
                                        <div style='font-size:14px;font-weight:600;'>{$title}</div>
                                    </div>
                                    <div>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;'>Tip Document</div>
                                        <div><span style='display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;color:#fff;background:{$typeColor};'>{$type}</span></div>
                                    </div>
                                    <div>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;'>Data emitere</div>
                                        <div style='font-size:13px;'>{$issuedAt}</div>
                                    </div>
                                    <div>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;'>Creat la</div>
                                        <div style='font-size:13px;'>{$createdAt}</div>
                                    </div>
                                    <div>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;'>Template folosit</div>
                                        <div style='font-size:13px;'>{$templateName}</div>
                                    </div>
                                    <div>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;'>Dimensiune fișier</div>
                                        <div style='font-size:13px;'>{$fileSize}</div>
                                    </div>
                                </div>";

                                return new HtmlString($html);
                            }),
                    ]),

                // ── Organizer + Event side by side ──
                Section::make('Organizator & Eveniment')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\Placeholder::make('org_event_info')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return '-';

                                // Organizer
                                $org = $record->organizer;
                                $orgHtml = '<div style="color:#888;font-style:italic;">Organizator nesetat</div>';
                                if ($org) {
                                    $orgName = e($org->name ?? '-');
                                    $orgCompany = e($org->company_name ?? '-');
                                    $orgEmail = e($org->email ?? '-');
                                    $orgPhone = e($org->phone ?? '-');
                                    $orgTax = e($org->company_tax_id ?? '-');
                                    $orgReg = e($org->company_registration ?? '-');
                                    $orgAddr = e($org->company_address ?? '-');
                                    $orgCity = e($org->city ?? '-');
                                    $orgIban = e($org->iban ?? '-');
                                    $orgBank = e($org->bank_name ?? '-');

                                    $orgHtml = "
                                    <div style='font-weight:700;font-size:14px;color:#1a4a7a;margin-bottom:6px;'>{$orgCompany}</div>
                                    <div style='font-size:12px;color:#555;line-height:1.8;'>
                                        <div><strong>Contact:</strong> {$orgName}</div>
                                        <div><strong>Email:</strong> {$orgEmail}</div>
                                        <div><strong>Telefon:</strong> {$orgPhone}</div>
                                        <div><strong>CUI/CIF:</strong> {$orgTax}</div>
                                        <div><strong>Nr. înreg.:</strong> {$orgReg}</div>
                                        <div><strong>Adresă:</strong> {$orgAddr}, {$orgCity}</div>
                                        <div><strong>IBAN:</strong> {$orgIban}</div>
                                        <div><strong>Bancă:</strong> {$orgBank}</div>
                                    </div>";
                                }

                                // Event
                                $event = $record->event;
                                $eventHtml = '<div style="color:#888;font-style:italic;">Eveniment nesetat</div>';
                                if ($event) {
                                    $eventName = is_array($event->title)
                                        ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: '-')
                                        : ($event->title ?? '-');
                                    $eventName = e((string) $eventName);
                                    $eventDate = $event->event_date?->format('d.m.Y') ?? '-';
                                    if ($event->start_time) $eventDate .= ' ' . $event->start_time;

                                    $venue = $event->venue;
                                    $venueName = $venue ? e($venue->getTranslation('name', 'ro') ?? '-') : '-';
                                    $venueAddr = e($venue?->address ?? '-');
                                    $venueCity = e($venue?->city ?? '-');

                                    $eventHtml = "
                                    <div style='font-weight:700;font-size:14px;color:#1a4a7a;margin-bottom:6px;'>{$eventName}</div>
                                    <div style='font-size:12px;color:#555;line-height:1.8;'>
                                        <div><strong>Data:</strong> {$eventDate}</div>
                                        <div><strong>Locație:</strong> {$venueName}</div>
                                        <div><strong>Adresă:</strong> {$venueAddr}</div>
                                        <div><strong>Oraș:</strong> {$venueCity}</div>
                                    </div>";
                                }

                                return new HtmlString("
                                <div style='display:grid; grid-template-columns:1fr 1fr; gap:24px;'>
                                    <div style='padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fafbfc;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;border-bottom:2px solid #c0392b;padding-bottom:4px;'>Organizator</div>
                                        {$orgHtml}
                                    </div>
                                    <div style='padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fafbfc;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;border-bottom:2px solid #2563eb;padding-bottom:4px;'>Eveniment</div>
                                        {$eventHtml}
                                    </div>
                                </div>");
                            }),
                    ]),

                // ── Decont Details (only for decont type) ──
                Section::make('Detalii Decont')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn ($record) => $record?->document_type === 'decont')
                    ->schema([
                        Forms\Components\Placeholder::make('decont_details')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || $record->document_type !== 'decont') return '-';

                                $payout = $record->payout;
                                if (!$payout) {
                                    return new HtmlString('<div style="color:#888;font-style:italic;">Niciun decont asociat acestui document.</div>');
                                }

                                $payout->loadMissing(['organizer', 'invoice']);
                                $org = $payout->organizer;
                                $event = $record->event;

                                // Count deconts for this event
                                $decontCount = MarketplacePayout::where('event_id', $payout->event_id)
                                    ->where('marketplace_organizer_id', $payout->marketplace_organizer_id)
                                    ->whereIn('status', ['approved', 'processing', 'completed'])
                                    ->count();
                                $thisDecontNr = MarketplacePayout::where('event_id', $payout->event_id)
                                    ->where('marketplace_organizer_id', $payout->marketplace_organizer_id)
                                    ->whereIn('status', ['approved', 'processing', 'completed'])
                                    ->where('id', '<=', $payout->id)
                                    ->count();

                                // Balance info
                                $availableBalance = number_format((float) ($org?->available_balance ?? 0), 2, '.', ',');
                                $pendingBalance = number_format((float) ($org?->pending_balance ?? 0), 2, '.', ',');

                                // Payout amounts
                                $gross = number_format((float) $payout->gross_amount, 2, '.', ',');
                                $commission = number_format((float) $payout->commission_amount, 2, '.', ',');
                                $fees = number_format((float) $payout->fees_amount, 2, '.', ',');
                                $adjustments = number_format((float) $payout->adjustments_amount, 2, '.', ',');
                                $net = number_format((float) $payout->amount, 2, '.', ',');
                                $currency = e($payout->currency ?? 'RON');
                                $status = e($payout->status ?? '-');
                                $statusColor = match ($payout->status) {
                                    'completed' => '#059669',
                                    'approved' => '#2563eb',
                                    'processing' => '#d97706',
                                    'pending' => '#6b7280',
                                    'rejected' => '#dc2626',
                                    'cancelled' => '#9ca3af',
                                    default => '#6b7280',
                                };
                                $reference = e($payout->reference ?? '-');

                                // Invoice info
                                $invoice = $payout->invoice;
                                $invoiceHtml = '<span style="color:#dc2626;font-weight:600;">❌ Factură negenerată</span>';
                                if ($invoice) {
                                    $invNum = e($invoice->number ?? $invoice->invoice_number ?? '-');
                                    $invDate = $invoice->issued_at?->format('d.m.Y') ?? ($invoice->created_at?->format('d.m.Y') ?? '-');
                                    $invAmount = number_format((float) ($invoice->total ?? $invoice->amount ?? 0), 2, '.', ',');
                                    $invoiceUrl = PayoutResource::getUrl('view', ['record' => $payout]);
                                    $invoiceHtml = "<span style='color:#059669;font-weight:600;'>✅ {$invNum}</span> — {$invDate} — {$invAmount} {$currency}";
                                }

                                // Ticket breakdown
                                $breakdownHtml = '';
                                $breakdown = $payout->ticket_breakdown;
                                if (!empty($breakdown) && is_array($breakdown)) {
                                    $rows = '';
                                    foreach ($breakdown as $item) {
                                        $ttName = e($item['name'] ?? '-');
                                        $ttQty = (int) ($item['qty'] ?? 0);
                                        $ttPrice = number_format((float) ($item['price'] ?? 0), 2, '.', ',');
                                        $ttTotal = number_format((float) ($item['total'] ?? 0), 2, '.', ',');
                                        $ttComm = number_format((float) ($item['commission'] ?? 0), 2, '.', ',');
                                        $rows .= "<tr>
                                            <td style='padding:6px 8px;border-bottom:1px solid #f0f0f0;font-size:12px;'>{$ttName}</td>
                                            <td style='padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:center;font-size:12px;'>{$ttQty}</td>
                                            <td style='padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-size:12px;'>{$ttPrice}</td>
                                            <td style='padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-size:12px;'>{$ttTotal}</td>
                                            <td style='padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-size:12px;'>{$ttComm}</td>
                                        </tr>";
                                    }
                                    $breakdownHtml = "
                                    <div style='margin-top:16px;'>
                                        <div style='font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;'>Detaliu bilete</div>
                                        <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
                                            <thead>
                                                <tr style='background:#f3f4f6;'>
                                                    <th style='padding:6px 8px;text-align:left;font-size:11px;color:#555;font-weight:600;'>Tip bilet</th>
                                                    <th style='padding:6px 8px;text-align:center;font-size:11px;color:#555;font-weight:600;'>Qty</th>
                                                    <th style='padding:6px 8px;text-align:right;font-size:11px;color:#555;font-weight:600;'>Preț</th>
                                                    <th style='padding:6px 8px;text-align:right;font-size:11px;color:#555;font-weight:600;'>Total</th>
                                                    <th style='padding:6px 8px;text-align:right;font-size:11px;color:#555;font-weight:600;'>Comision</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                        </table>
                                    </div>";
                                }

                                return new HtmlString("
                                <div style='display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;'>
                                    <div style='padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#f0fdf4;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;'>Referință decont</div>
                                        <div style='font-size:16px;font-weight:700;color:#1a1a1a;'>{$reference}</div>
                                        <div style='margin-top:4px;'><span style='display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;color:#fff;background:{$statusColor};'>{$status}</span></div>
                                        <div style='margin-top:8px;font-size:12px;color:#555;'>Decont <strong>{$thisDecontNr}</strong> din <strong>{$decontCount}</strong> pentru acest eveniment</div>
                                    </div>
                                    <div style='padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fefce8;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;'>Balanță organizator</div>
                                        <div style='font-size:12px;color:#555;line-height:1.8;'>
                                            <div><strong>Disponibil:</strong> {$availableBalance} {$currency}</div>
                                            <div><strong>În așteptare:</strong> {$pendingBalance} {$currency}</div>
                                        </div>
                                    </div>
                                </div>

                                <div style='display:grid; grid-template-columns:repeat(5, 1fr); gap:10px; margin-bottom:16px;'>
                                    <div style='padding:10px;border:1px solid #e5e7eb;border-radius:8px;text-align:center;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;'>Brut</div>
                                        <div style='font-size:15px;font-weight:700;'>{$gross}</div>
                                        <div style='font-size:10px;color:#888;'>{$currency}</div>
                                    </div>
                                    <div style='padding:10px;border:1px solid #e5e7eb;border-radius:8px;text-align:center;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;'>Comision</div>
                                        <div style='font-size:15px;font-weight:700;color:#dc2626;'>-{$commission}</div>
                                        <div style='font-size:10px;color:#888;'>{$currency}</div>
                                    </div>
                                    <div style='padding:10px;border:1px solid #e5e7eb;border-radius:8px;text-align:center;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;'>Taxe</div>
                                        <div style='font-size:15px;font-weight:700;color:#dc2626;'>-{$fees}</div>
                                        <div style='font-size:10px;color:#888;'>{$currency}</div>
                                    </div>
                                    <div style='padding:10px;border:1px solid #e5e7eb;border-radius:8px;text-align:center;'>
                                        <div style='font-size:10px;color:#888;text-transform:uppercase;'>Ajustări</div>
                                        <div style='font-size:15px;font-weight:700;color:#d97706;'>-{$adjustments}</div>
                                        <div style='font-size:10px;color:#888;'>{$currency}</div>
                                    </div>
                                    <div style='padding:10px;border:1px solid #059669;border-radius:8px;text-align:center;background:#f0fdf4;'>
                                        <div style='font-size:10px;color:#059669;text-transform:uppercase;font-weight:600;'>Net de plată</div>
                                        <div style='font-size:17px;font-weight:800;color:#059669;'>{$net}</div>
                                        <div style='font-size:10px;color:#059669;'>{$currency}</div>
                                    </div>
                                </div>

                                <div style='padding:10px;border:1px solid #e5e7eb;border-radius:8px;background:#fafbfc;'>
                                    <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;'>Factură asociată</div>
                                    <div style='font-size:13px;'>{$invoiceHtml}</div>
                                </div>

                                {$breakdownHtml}
                                ");
                            }),
                    ]),

                // ── Document Preview ──
                Section::make('Previzualizare Document')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Forms\Components\Placeholder::make('document_preview')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->html_content) {
                                    return 'Conținutul documentului nu este disponibil.';
                                }

                                $htmlContent = htmlspecialchars($record->html_content, ENT_QUOTES, 'UTF-8');

                                return new HtmlString("
                                    <div class='border border-gray-200 rounded-lg overflow-hidden bg-white'>
                                        <iframe
                                            id='document-preview-iframe'
                                            class='w-full'
                                            style='height: 800px; border: none;'
                                            srcdoc='{$htmlContent}'
                                        ></iframe>
                                    </div>
                                ");
                            }),
                    ])
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Document')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => OrganizerDocument::TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'cerere_avizare' => 'info',
                        'declaratie_impozite' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('organizer.company_name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state['ro'] ?? $state['en'] ?? reset($state) ?: '-';
                        }
                        return $state ?? '-';
                    })
                    ->limit(40)
                    ->tooltip(function ($record) {
                        $title = $record->event?->title;
                        if (is_array($title)) {
                            return $title['ro'] ?? $title['en'] ?? reset($title) ?: null;
                        }
                        return $title;
                    }),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($record) => $record->formatted_file_size),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Type')
                    ->options(OrganizerDocument::TYPES),

                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizer')
                    ->relationship('organizer', 'company_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->company_name ?? $record->name ?? 'Unknown')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record])),
                Action::make('download')
                    ->label('')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record) => $record->download_url)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->file_path),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('delete_selected')
                    ->label('Șterge selectate')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Șterge documentele selectate')
                    ->modalDescription('Ești sigur? Fișierele PDF vor fi șterse permanent.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        foreach ($records as $doc) {
                            if ($doc->file_path) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->file_path);
                            }
                            $doc->delete();
                        }
                        \Filament\Notifications\Notification::make()
                            ->title($records->count() . ' documente șterse')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizerDocuments::route('/'),
            'view' => Pages\ViewOrganizerDocument::route('/{record}'),
        ];
    }
}
