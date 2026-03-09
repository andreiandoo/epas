<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\MarketplaceOrganizer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class OrganizerInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationLabel = 'Facturi Organizatori';
    protected static ?string $modelLabel = 'Factură';
    protected static ?string $pluralModelLabel = 'Facturi Organizatori';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id)
            ->whereNotNull('marketplace_organizer_id');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Detalii factură')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Număr factură')
                            ->disabled(),

                        Forms\Components\Select::make('marketplace_organizer_id')
                            ->label('Organizator')
                            ->relationship('organizer', 'name')
                            ->disabled()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->label('Tip')
                            ->options([
                                'proforma' => 'Proforma',
                                'fiscal' => 'Fiscală',
                            ])
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'outstanding' => 'Neachitată',
                                'paid' => 'Achitată',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descriere')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Date')
                    ->schema([
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Data emiterii')
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Data scadentă')
                            ->required(),

                        Forms\Components\DatePicker::make('period_start')
                            ->label('Perioadă de la'),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Perioadă până la'),
                    ])->columns(4),

                Section::make('Valori')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                $vatRate = (float) ($get('vat_rate') ?? 0);
                                $subtotal = (float) ($state ?? 0);
                                $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
                                $set('vat_amount', $vatAmount);
                                $set('amount', $subtotal + $vatAmount);
                            }),

                        Forms\Components\TextInput::make('vat_rate')
                            ->label('TVA (%)')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                $subtotal = (float) ($get('subtotal') ?? 0);
                                $vatRate = (float) ($state ?? 0);
                                $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
                                $set('vat_amount', $vatAmount);
                                $set('amount', $subtotal + $vatAmount);
                            }),

                        Forms\Components\TextInput::make('vat_amount')
                            ->label('Valoare TVA')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Total')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('currency')
                            ->label('Monedă')
                            ->default('RON')
                            ->maxLength(3),
                    ])->columns(5),

                // Organizer data section
                Section::make('Date organizator (client facturat)')
                    ->schema([
                        Forms\Components\Placeholder::make('org_info')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return '-';
                                $org = $record->organizer;
                                if (!$org) return 'Organizator negăsit.';

                                $meta = $record->meta ?? [];
                                $client = $meta['client'] ?? [];
                                $issuer = $meta['issuer'] ?? [];

                                $warn = fn ($val, $label) => empty($val)
                                    ? "<span style='color:#dc2626;font-weight:600;'>⚠ {$label} LIPSEȘTE</span>"
                                    : e($val);

                                $html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;font-size:14px;">';

                                // Issuer (emitent) - from invoice meta
                                $html .= '<div style="background:#f0fdf4;padding:16px;border-radius:8px;border:1px solid #bbf7d0;">';
                                $html .= '<h4 style="font-weight:700;color:#166534;margin:0 0 12px;font-size:13px;text-transform:uppercase;">Emitent (din factură)</h4>';
                                $html .= '<p><strong>Nume:</strong> ' . $warn($issuer['name'] ?? '', 'Nume') . '</p>';
                                $html .= '<p><strong>CUI:</strong> ' . $warn($issuer['cui'] ?? '', 'CUI') . '</p>';
                                $html .= '<p><strong>Reg. Com.:</strong> ' . e($issuer['reg_com'] ?? '-') . '</p>';
                                $html .= '<p><strong>Adresă:</strong> ' . e($issuer['address'] ?? '-') . '</p>';
                                $html .= '<p><strong>Bancă:</strong> ' . e($issuer['bank_name'] ?? '-') . '</p>';
                                $html .= '<p><strong>IBAN:</strong> ' . e($issuer['iban'] ?? '-') . '</p>';
                                $html .= '<p><strong>Plătitor TVA:</strong> ' . (($issuer['vat_payer'] ?? false) ? 'Da' : 'Nu') . '</p>';
                                $html .= '</div>';

                                // Client (organizer) - from invoice meta + live organizer data
                                $html .= '<div style="background:#eff6ff;padding:16px;border-radius:8px;border:1px solid #bfdbfe;">';
                                $html .= '<h4 style="font-weight:700;color:#1e40af;margin:0 0 12px;font-size:13px;text-transform:uppercase;">Client (organizator)</h4>';
                                $html .= '<p><strong>Nume:</strong> ' . $warn($client['name'] ?? '', 'Nume') . '</p>';
                                $html .= '<p><strong>CUI (din factură):</strong> ' . $warn($client['cui'] ?? '', 'CUI') . '</p>';
                                $html .= '<p><strong>CUI (din profil):</strong> ' . $warn($org->company_tax_id ?? '', 'CUI profil') . '</p>';
                                $html .= '<p><strong>Reg. Com. (din factură):</strong> ' . e($client['reg_com'] ?? '-') . '</p>';
                                $html .= '<p><strong>Reg. Com. (din profil):</strong> ' . e($org->company_registration ?? '-') . '</p>';
                                $html .= '<p><strong>Adresă (din factură):</strong> ' . e($client['address'] ?? '-') . '</p>';
                                $html .= '<p><strong>Adresă (din profil):</strong> ' . e(implode(', ', array_filter([
                                    $org->company_address,
                                    $org->company_city,
                                    $org->company_county,
                                ])) ?: '-') . '</p>';
                                $html .= '<p><strong>Email:</strong> ' . e($org->billing_email ?? $org->email ?? '-') . '</p>';
                                $html .= '</div>';

                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // Invoice items section
                Section::make('Articole factură')
                    ->schema([
                        Forms\Components\Placeholder::make('items_table')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return '-';
                                $meta = $record->meta ?? [];
                                $items = $meta['items'] ?? [];
                                $currency = $record->currency ?? 'RON';
                                $vatRate = (float) ($record->vat_rate ?? 19);

                                if (empty($items)) {
                                    return new HtmlString('<p style="color:#6b7280;">Nu sunt articole în această factură.</p>');
                                }

                                $html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                                $html .= '<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">';
                                $html .= '<th style="padding:10px 8px;text-align:left;font-size:12px;text-transform:uppercase;color:#6b7280;">Articol</th>';
                                $html .= '<th style="padding:10px 8px;text-align:center;font-size:12px;text-transform:uppercase;color:#6b7280;">Cantitate</th>';
                                $html .= '<th style="padding:10px 8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Preț unitar</th>';
                                $html .= '<th style="padding:10px 8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Valoare fără TVA</th>';
                                $html .= '<th style="padding:10px 8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">TVA</th>';
                                $html .= '<th style="padding:10px 8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Total</th>';
                                $html .= '</tr></thead><tbody>';

                                $totalNet = 0;
                                $totalVat = 0;
                                $totalGross = 0;

                                foreach ($items as $item) {
                                    $qty = (float) ($item['quantity'] ?? 1);
                                    $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                                    $lineTotal = (float) ($item['total'] ?? ($qty * $price));
                                    $lineVat = round($lineTotal * $vatRate / 100, 2);
                                    $lineGross = $lineTotal + $lineVat;

                                    $totalNet += $lineTotal;
                                    $totalVat += $lineVat;
                                    $totalGross += $lineGross;

                                    $html .= '<tr style="border-bottom:1px solid #e5e7eb;">';
                                    $html .= '<td style="padding:10px 8px;">' . e($item['description'] ?? '-') . '</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:center;">' . $qty . '</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($price, 2) . ' ' . $currency . '</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($lineTotal, 2) . ' ' . $currency . '</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($lineVat, 2) . ' ' . $currency . '</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:right;font-weight:600;">' . number_format($lineGross, 2) . ' ' . $currency . '</td>';
                                    $html .= '</tr>';
                                }

                                // Totals row
                                $html .= '<tr style="background:#f9fafb;font-weight:700;border-top:2px solid #d1d5db;">';
                                $html .= '<td style="padding:10px 8px;" colspan="3">TOTAL</td>';
                                $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($totalNet, 2) . ' ' . $currency . '</td>';
                                $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($totalVat, 2) . ' ' . $currency . '</td>';
                                $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($totalGross, 2) . ' ' . $currency . '</td>';
                                $html .= '</tr>';

                                $html .= '</tbody></table>';

                                // Compare with invoice totals
                                $invoiceSubtotal = (float) $record->subtotal;
                                $invoiceVat = (float) $record->vat_amount;
                                $invoiceTotal = (float) $record->amount;

                                if (abs($totalNet - $invoiceSubtotal) > 0.01 || abs($totalGross - $invoiceTotal) > 0.01) {
                                    $html .= '<p style="color:#dc2626;font-size:13px;margin-top:8px;">⚠ Totalurile articolelor nu corespund cu totalurile facturii (subtotal factură: ' . number_format($invoiceSubtotal, 2) . ', total factură: ' . number_format($invoiceTotal, 2) . ')</p>';
                                }

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Număr')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizator')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fiscal' => 'info',
                        'proforma' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fiscal' => 'Fiscală',
                        'proforma' => 'Proforma',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Data emiterii')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Perioadă')
                    ->formatStateUsing(function ($state, Invoice $record) {
                        if (!$record->period_start || !$record->period_end) return '-';
                        return $record->period_start->format('d.m.Y') . ' - ' . $record->period_end->format('d.m.Y');
                    }),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money(fn (Invoice $record) => $record->currency ?? 'RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vat_amount')
                    ->label('TVA')
                    ->money(fn (Invoice $record) => $record->currency ?? 'RON'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Total')
                    ->money(fn (Invoice $record) => $record->currency ?? 'RON')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'outstanding' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Achitată',
                        'outstanding' => 'Neachitată',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('anafQueue.status')
                    ->label('eFactura')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted' => 'success',
                        'submitted' => 'warning',
                        'queued' => 'info',
                        'rejected', 'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'queued' => 'În coadă',
                        'submitted' => 'Trimisă',
                        'accepted' => 'Acceptată',
                        'rejected' => 'Respinsă',
                        'error' => 'Eroare',
                        default => '-',
                    })
                    ->placeholder('-'),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizator')
                    ->relationship('organizer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'outstanding' => 'Neachitată',
                        'paid' => 'Achitată',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tip')
                    ->options([
                        'fiscal' => 'Fiscală',
                        'proforma' => 'Proforma',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Invoice $record) => "Factură #{$record->number}")
                    ->modalContent(function (Invoice $record) {
                        return new HtmlString(static::renderInvoiceHtml($record));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide'),
            ])
            ->toolbarActions([]);
    }

    /**
     * Render invoice as HTML for preview modal.
     * Priority: 1) Provider PDF (if use_provider_template enabled), 2) Tax template, 3) Built-in fallback.
     */
    public static function renderInvoiceHtml(Invoice $record): string
    {
        // Check if accounting connector has use_provider_template enabled
        $meta = $record->meta ?? [];
        $pdfUrl = $meta['accounting']['pdf_url'] ?? null;

        if ($pdfUrl && static::isProviderTemplateEnabled($record->marketplace_client_id)) {
            $provider = ucfirst($meta['accounting']['provider'] ?? 'contabilitate');
            $accNumber = e($meta['accounting']['invoice_number'] ?? $record->number);

            return <<<HTML
            <div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;text-align:center;padding:40px 20px;">
                <div style="margin-bottom:24px;">
                    <svg style="width:64px;height:64px;margin:0 auto;color:#2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <h3 style="font-size:18px;font-weight:600;color:#1f2937;margin-bottom:8px;">Factură #{$accNumber}</h3>
                <p style="color:#6b7280;margin-bottom:24px;">Factura a fost generată de {$provider}.</p>
                <a href="{$pdfUrl}" target="_blank" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">
                    Vizualizează / Descarcă PDF
                </a>
                <p style="color:#9ca3af;font-size:12px;margin-top:16px;">PDF generat de {$provider}</p>
            </div>
            HTML;
        }

        // Try to use a tax template
        $template = \App\Models\MarketplaceTaxTemplate::where('marketplace_client_id', $record->marketplace_client_id)
            ->where('type', 'invoice')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if ($template) {
            $variables = \App\Models\MarketplaceTaxTemplate::getInvoiceVariables($record);

            // Add date/time variables
            $now = now();
            $variables['current_day'] = $now->format('d');
            $variables['current_month'] = $now->format('m');
            $variables['current_month_name'] = $now->translatedFormat('F');
            $variables['current_year'] = $now->format('Y');
            $variables['current_date'] = $now->format('d.m.Y');
            $variables['current_datetime'] = $now->format('d.m.Y H:i');

            return $template->processTemplate($variables);
        }

        // Fallback: built-in simple layout
        return static::renderInvoiceHtmlFallback($record);
    }

    /**
     * Check if the marketplace's accounting connector has use_provider_template enabled.
     */
    protected static function isProviderTemplateEnabled(?int $marketplaceClientId): bool
    {
        if (!$marketplaceClientId) return false;

        $connector = \Illuminate\Support\Facades\DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) return false;

        $settings = json_decode($connector->settings ?? '{}', true) ?: [];
        return (bool) ($settings['use_provider_template'] ?? false);
    }

    /**
     * Fallback invoice HTML when no tax template exists.
     */
    protected static function renderInvoiceHtmlFallback(Invoice $record): string
    {
        $meta = $record->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];
        $currency = $record->currency ?? 'RON';

        $issuerHtml = '<div>';
        $issuerHtml .= '<h4 style="font-weight:600;text-transform:uppercase;font-size:12px;color:#6b7280;margin-bottom:8px;">Emitent</h4>';
        $issuerHtml .= '<p style="font-weight:600;">' . e($issuer['name'] ?? '-') . '</p>';
        if (!empty($issuer['cui'])) $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">CUI: ' . e($issuer['cui']) . '</p>';
        if (!empty($issuer['reg_com'])) $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">Reg. Com.: ' . e($issuer['reg_com']) . '</p>';
        if (!empty($issuer['address'])) $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">' . e($issuer['address']) . '</p>';
        if (!empty($issuer['bank_name']) && !empty($issuer['iban'])) {
            $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">' . e($issuer['bank_name']) . ': ' . e($issuer['iban']) . '</p>';
        }
        $issuerHtml .= '</div>';

        $clientHtml = '<div>';
        $clientHtml .= '<h4 style="font-weight:600;text-transform:uppercase;font-size:12px;color:#6b7280;margin-bottom:8px;">Client</h4>';
        $clientHtml .= '<p style="font-weight:600;">' . e($client['name'] ?? '-') . '</p>';
        if (!empty($client['cui'])) $clientHtml .= '<p style="color:#6b7280;font-size:14px;">CUI: ' . e($client['cui']) . '</p>';
        if (!empty($client['address'])) $clientHtml .= '<p style="color:#6b7280;font-size:14px;">' . e($client['address']) . '</p>';
        $clientHtml .= '</div>';

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<tr>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;">' . e($item['description'] ?? '') . '</td>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">' . ($item['quantity'] ?? 0) . '</td>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">' . number_format($item['price'] ?? 0, 2) . ' ' . $currency . '</td>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600;">' . number_format($item['total'] ?? 0, 2) . ' ' . $currency . '</td>';
            $itemsHtml .= '</tr>';
        }

        $statusLabel = $record->status === 'paid' ? 'Achitată' : 'Neachitată';
        $statusColor = $record->status === 'paid' ? '#059669' : '#d97706';

        return <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;">
            <div style="display:flex;gap:24px;margin-bottom:24px;">
                {$issuerHtml}
                {$clientHtml}
            </div>
            <table style="width:100%;border-collapse:collapse;margin-top:16px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:8px;text-align:left;font-size:12px;text-transform:uppercase;color:#6b7280;">Descriere</th>
                        <th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Cantitate</th>
                        <th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Preț</th>
                        <th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Total</th>
                    </tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
            </table>
            <div style="margin-top:16px;text-align:right;">
                <p>Subtotal: <strong>{$record->subtotal} {$currency}</strong></p>
                <p>TVA ({$record->vat_rate}%): <strong>{$record->vat_amount} {$currency}</strong></p>
                <p style="font-size:18px;margin-top:8px;">Total: <strong>{$record->amount} {$currency}</strong></p>
            </div>
            <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;padding-top:16px;">
                <span style="background:{$statusColor};color:#fff;padding:4px 12px;border-radius:12px;font-size:13px;">{$statusLabel}</span>
                <span style="color:#6b7280;font-size:14px;">Emisă: {$record->issue_date?->format('d.m.Y')} | Scadentă: {$record->due_date?->format('d.m.Y')}</span>
            </div>
        </div>
        HTML;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizerInvoices::route('/'),
            'edit' => Pages\EditOrganizerInvoice::route('/{record}/edit'),
        ];
    }
}
