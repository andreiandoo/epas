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
            ->columns(1)
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

                // Organizer & items data section (single column)
                Section::make('Date emitent / client / articole')
                    ->schema([
                        Forms\Components\Placeholder::make('invoice_details_view')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return '-';
                                $org = $record->organizer;
                                $meta = $record->meta ?? [];
                                $issuer = $meta['issuer'] ?? [];
                                $client = $meta['client'] ?? [];
                                $items = $meta['items'] ?? [];
                                $currency = $record->currency ?? 'RON';
                                $vatRate = (float) ($record->vat_rate ?? 19);

                                // Also load current marketplace data for comparison
                                $marketplace = \App\Models\MarketplaceClient::find($record->marketplace_client_id);

                                $warn = fn ($val, $label) => empty($val)
                                    ? "<span style='color:#ef4444;font-weight:600;'>⚠ {$label} LIPSEȘTE</span>"
                                    : e($val);

                                $row = fn ($label, $value) => "<tr><td style='padding:6px 12px 6px 0;opacity:0.7;white-space:nowrap;'>{$label}</td><td style='padding:6px 0;'>{$value}</td></tr>";

                                $html = '<div style="font-size:14px;line-height:1.6;">';

                                // ── EMITENT ──
                                $html .= '<h4 style="font-weight:700;font-size:13px;text-transform:uppercase;opacity:0.5;margin:0 0 8px;letter-spacing:0.05em;">Emitent (din factură)</h4>';
                                $html .= '<table style="width:100%;">';
                                $html .= $row('Nume', $warn($issuer['name'] ?? '', 'Nume'));
                                $html .= $row('CUI', $warn($issuer['cui'] ?? '', 'CUI'));
                                if ($marketplace && !empty($marketplace->cui) && empty($issuer['cui'])) {
                                    $html .= $row('CUI (din setări)', '<span style="color:#22c55e;">' . e($marketplace->cui) . ' — va fi completat automat la trimitere</span>');
                                }
                                $html .= $row('Reg. Com.', e($issuer['reg_com'] ?? '-'));
                                $html .= $row('Adresă', e($issuer['address'] ?? '-'));
                                $html .= $row('Bancă', e($issuer['bank_name'] ?? '-'));
                                $html .= $row('IBAN', e($issuer['iban'] ?? '-'));
                                $html .= $row('Plătitor TVA', ($issuer['vat_payer'] ?? false) ? 'Da' : 'Nu');
                                $html .= '</table>';

                                // ── SEPARATOR ──
                                $html .= '<hr style="border:none;border-top:1px solid currentColor;opacity:0.15;margin:20px 0;">';

                                // ── CLIENT ──
                                $isGeneralClient = ($record->meta['recipient_type'] ?? null) === 'general_client';
                                $clientHeading = $isGeneralClient ? 'Client (general)' : 'Client (organizator)';
                                $html .= '<h4 style="font-weight:700;font-size:13px;text-transform:uppercase;opacity:0.5;margin:0 0 8px;letter-spacing:0.05em;">' . $clientHeading . '</h4>';
                                $html .= '<table style="width:100%;">';
                                $html .= $row('Nume', $warn($client['name'] ?? '', 'Nume'));

                                if ($isGeneralClient) {
                                    // For general client, show only the static data — no organizer profile fallback
                                    $html .= $row('CUI', e($client['cui'] ?? '') ?: '<span style="opacity:0.5;">—</span>');
                                    $html .= $row('Adresă', e($client['address'] ?? '') ?: '<span style="opacity:0.5;">—</span>');
                                } else {
                                    $html .= $row('CUI (factură)', $warn($client['cui'] ?? '', 'CUI'));
                                    if ($org) {
                                        $orgCui = $org->company_tax_id ?? '';
                                        if ($orgCui && empty($client['cui'])) {
                                            $html .= $row('CUI (profil)', '<span style="color:#22c55e;">' . e($orgCui) . ' — va fi completat automat la trimitere</span>');
                                        } elseif ($orgCui) {
                                            $html .= $row('CUI (profil)', e($orgCui));
                                        } else {
                                            $html .= $row('CUI (profil)', $warn('', 'CUI profil'));
                                        }
                                        $html .= $row('Reg. Com. (factură)', e($client['reg_com'] ?? '-'));
                                        $html .= $row('Reg. Com. (profil)', e($org->company_registration ?? '-'));
                                        $html .= $row('Adresă (factură)', e($client['address'] ?? '-'));
                                        $orgAddr = implode(', ', array_filter([$org->company_address, $org->company_city, $org->company_county]));
                                        $html .= $row('Adresă (profil)', e($orgAddr ?: '-'));
                                        $html .= $row('Email', e($org->billing_email ?? $org->email ?? '-'));
                                    } else {
                                        $html .= $row('Organizator', '<span style="color:#ef4444;">Organizator negăsit</span>');
                                    }
                                }
                                $html .= '</table>';

                                // ── SEPARATOR ──
                                $html .= '<hr style="border:none;border-top:1px solid currentColor;opacity:0.15;margin:20px 0;">';

                                // ── ARTICOLE ──
                                $html .= '<h4 style="font-weight:700;font-size:13px;text-transform:uppercase;opacity:0.5;margin:0 0 8px;letter-spacing:0.05em;">Articole factură</h4>';

                                if (empty($items)) {
                                    $html .= '<p style="opacity:0.5;">Nu sunt articole în această factură.</p>';
                                } else {
                                    $html .= '<table style="width:100%;border-collapse:collapse;">';
                                    $html .= '<thead><tr style="border-bottom:2px solid currentColor;opacity:0.9;">';
                                    $html .= '<th style="padding:8px 8px 8px 0;text-align:left;font-size:12px;text-transform:uppercase;opacity:0.6;">Articol</th>';
                                    $html .= '<th style="padding:8px;text-align:center;font-size:12px;text-transform:uppercase;opacity:0.6;">Cant.</th>';
                                    $html .= '<th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;opacity:0.6;">Preț unitar</th>';
                                    $html .= '<th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;opacity:0.6;">Fără TVA</th>';
                                    $html .= '<th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;opacity:0.6;">TVA</th>';
                                    $html .= '<th style="padding:8px 0 8px 8px;text-align:right;font-size:12px;text-transform:uppercase;opacity:0.6;">Total</th>';
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

                                        $html .= '<tr style="border-bottom:1px solid currentColor;border-bottom-color:inherit;opacity:0.9;">';
                                        $html .= '<td style="padding:8px 8px 8px 0;border-bottom:1px solid rgba(128,128,128,0.2);">' . e($item['description'] ?? '-') . '</td>';
                                        $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid rgba(128,128,128,0.2);">' . $qty . '</td>';
                                        $html .= '<td style="padding:8px;text-align:right;border-bottom:1px solid rgba(128,128,128,0.2);">' . number_format($price, 2) . ' ' . $currency . '</td>';
                                        $html .= '<td style="padding:8px;text-align:right;border-bottom:1px solid rgba(128,128,128,0.2);">' . number_format($lineTotal, 2) . ' ' . $currency . '</td>';
                                        $html .= '<td style="padding:8px;text-align:right;border-bottom:1px solid rgba(128,128,128,0.2);">' . number_format($lineVat, 2) . ' ' . $currency . '</td>';
                                        $html .= '<td style="padding:8px 0 8px 8px;text-align:right;font-weight:600;border-bottom:1px solid rgba(128,128,128,0.2);">' . number_format($lineGross, 2) . ' ' . $currency . '</td>';
                                        $html .= '</tr>';
                                    }

                                    // Totals
                                    $html .= '<tr style="font-weight:700;border-top:2px solid rgba(128,128,128,0.3);">';
                                    $html .= '<td style="padding:10px 8px 10px 0;" colspan="3">TOTAL</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($totalNet, 2) . ' ' . $currency . '</td>';
                                    $html .= '<td style="padding:10px 8px;text-align:right;">' . number_format($totalVat, 2) . ' ' . $currency . '</td>';
                                    $html .= '<td style="padding:10px 0 10px 8px;text-align:right;">' . number_format($totalGross, 2) . ' ' . $currency . '</td>';
                                    $html .= '</tr>';
                                    $html .= '</tbody></table>';

                                    // Check totals match
                                    $invoiceSubtotal = (float) $record->subtotal;
                                    $invoiceTotal = (float) $record->amount;
                                    if (abs($totalNet - $invoiceSubtotal) > 0.01 || abs($totalGross - $invoiceTotal) > 0.01) {
                                        $html .= '<p style="color:#ef4444;font-size:13px;margin-top:8px;">⚠ Totalurile articolelor nu corespund cu totalurile facturii (subtotal: ' . number_format($invoiceSubtotal, 2) . ', total: ' . number_format($invoiceTotal, 2) . ')</p>';
                                    }
                                }

                                $html .= '</div>';
                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
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
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $meta = $record->meta ?? [];
                        if (($meta['recipient_type'] ?? null) === 'general_client') {
                            return $meta['client']['name'] ?? 'Client general';
                        }
                        return $state;
                    }),

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
