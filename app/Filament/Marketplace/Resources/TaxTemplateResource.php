<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;
use App\Models\MarketplaceTaxTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TaxTemplateResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceTaxTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Doc Templates';

    protected static ?string $modelLabel = 'Doc Template';

    protected static ?string $pluralModelLabel = 'Doc Templates';

    protected static ?string $slug = 'tax-templates';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')
                    ->icon('heroicon-o-document')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Template Type')
                            ->options(MarketplaceTaxTemplate::TYPES)
                            ->required()
                            ->default('invoice'),

                        Forms\Components\Select::make('trigger')
                            ->label('Build Template Trigger')
                            ->options(MarketplaceTaxTemplate::TRIGGERS)
                            ->placeholder('Select when to generate this template')
                            ->helperText('When should this template be automatically generated?'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('Make this the default template for its type'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Select::make('page_orientation')
                            ->label('Orientare pagină')
                            ->options([
                                'portrait' => 'Portrait (vertical)',
                                'landscape' => 'Landscape (orizontal)',
                            ])
                            ->default('portrait')
                            ->live()
                            ->helperText('Orientarea paginii A4 pentru generarea PDF-ului'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Available Variables')
                    ->icon('heroicon-o-variable')
                    ->description('Click to copy a variable to clipboard. Paste it into the HTML content below.')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_info')
                            ->label('')
                            ->content(function () {
                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

                                foreach (MarketplaceTaxTemplate::TEMPLATE_VARIABLES as $section => $variables) {
                                    $html .= '<div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">';
                                    $html .= '<h4 class="font-bold text-sm mb-2 text-primary-600">' . $section . '</h4>';
                                    $html .= '<div class="space-y-1">';

                                    foreach ($variables as $variable => $label) {
                                        $escapedVar = htmlspecialchars($variable);
                                        $html .= '<div class="flex items-center justify-between text-xs">';
                                        $html .= '<span class="text-gray-600 dark:text-gray-400">' . $label . '</span>';
                                        $html .= '<button type="button" onclick="navigator.clipboard.writeText(\'' . $escapedVar . '\'); this.textContent=\'Copied!\'; setTimeout(() => this.textContent=\'' . $escapedVar . '\', 1000);" class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono hover:bg-primary-100 dark:hover:bg-primary-900 transition cursor-pointer">' . $escapedVar . '</button>';
                                        $html .= '</div>';
                                    }

                                    $html .= '</div></div>';
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Page 1 - HTML Content')
                    ->icon('heroicon-o-code-bracket')
                    ->schema([
                        Forms\Components\Toggle::make('page1_source_mode')
                            ->label('Edit HTML Source Code')
                            ->default(false)
                            ->formatStateUsing(fn ($record) => $record?->html_content && str_contains($record->html_content, 'style="'))
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                if ($state) {
                                    $set('html_content_source', $get('html_content'));
                                }
                            })
                            ->helperText('Auto-enabled when content has inline CSS. Prevents style stripping.'),

                        Forms\Components\RichEditor::make('html_content')
                            ->label('Page 1 HTML Template (WYSIWYG)')
                            ->columnSpanFull()
                            ->dehydrated(true)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('tax-templates')
                            ->fileAttachmentsVisibility('public')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->helperText('Use the variables above in your HTML. You can also upload images using the attach button.')
                            ->visible(fn ($get) => !$get('page1_source_mode')),

                        Forms\Components\Textarea::make('html_content_source')
                            ->label('Page 1 HTML Template (Source Code)')
                            ->rows(25)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('Edit raw HTML code with inline CSS. Styles are preserved in source mode.')
                            ->formatStateUsing(fn ($record) => $record?->html_content)
                            ->visible(fn ($get) => $get('page1_source_mode')),
                    ]),

                Section::make('Page 2 - HTML Content (Optional)')
                    ->icon('heroicon-o-document-duplicate')
                    ->description('Add a second page if your template needs multiple pages (e.g., one portrait, one landscape)')
                    ->schema([
                        Forms\Components\Select::make('page_2_orientation')
                            ->label('Page 2 Orientation')
                            ->options(MarketplaceTaxTemplate::ORIENTATIONS)
                            ->placeholder('Select orientation for page 2'),

                        Forms\Components\Toggle::make('page2_source_mode')
                            ->label('Edit HTML Source Code')
                            ->default(false)
                            ->formatStateUsing(fn ($record) => $record?->html_content_page_2 && str_contains($record->html_content_page_2, 'style="'))
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                if ($state) {
                                    $set('html_content_page_2_source', $get('html_content_page_2'));
                                }
                            })
                            ->helperText('Auto-enabled when content has inline CSS. Prevents style stripping.'),

                        Forms\Components\RichEditor::make('html_content_page_2')
                            ->label('Page 2 HTML Template (WYSIWYG)')
                            ->columnSpanFull()
                            ->dehydrated(true)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('tax-templates')
                            ->fileAttachmentsVisibility('public')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->helperText('Leave empty if you only need one page. You can also upload images using the attach button.')
                            ->visible(fn ($get) => !$get('page2_source_mode')),

                        Forms\Components\Textarea::make('html_content_page_2_source')
                            ->label('Page 2 HTML Template (Source Code)')
                            ->rows(25)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('Edit raw HTML code with inline CSS. Styles are preserved in source mode.')
                            ->formatStateUsing(fn ($record) => $record?->html_content_page_2)
                            ->visible(fn ($get) => $get('page2_source_mode')),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Preview')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('')
                            ->content(function ($get, $record) {
                                // Use source textarea when source mode is ON (preserves inline CSS)
                                $sourceMode = $get('page1_source_mode');
                                $htmlContent = $sourceMode
                                    ? ($get('html_content_source') ?: $record?->html_content)
                                    : $get('html_content');

                                if (!$htmlContent) {
                                    return new HtmlString('<div class="text-gray-500 italic p-4 text-center">Enter HTML content above to see preview</div>');
                                }

                                // Get marketplace context for sample variables
                                $marketplace = static::getMarketplaceClient();

                                // Create sample data for preview
                                $sampleVariables = [
                                    // Tax Registry
                                    'tax_registry_country' => 'Romania',
                                    'tax_registry_county' => 'București',
                                    'tax_registry_city' => 'București',
                                    'tax_registry_name' => 'Sample Company SRL',
                                    'tax_registry_subname' => 'Finance Department',
                                    'tax_registry_address' => 'Str. Exemplu Nr. 123',
                                    'tax_registry_phone' => '+40 21 123 4567',
                                    'tax_registry_email' => 'contact@sample.ro',
                                    'tax_registry_cif' => 'RO12345678',
                                    'tax_registry_iban' => 'RO49AAAA1B31007593840000',

                                    // Marketplace
                                    'marketplace_legal_name' => $marketplace?->legal_name ?? $marketplace?->company_name ?? 'Marketplace SRL',
                                    'marketplace_vat' => $marketplace?->cui ?? $marketplace?->vat_number ?? 'RO98765432',
                                    'marketplace_trade_register' => $marketplace?->reg_com ?? $marketplace?->trade_register ?? 'J40/1234/2024',
                                    'marketplace_address' => $marketplace?->address ?? 'Bulevardul Central Nr. 1',
                                    'marketplace_city' => $marketplace?->city ?? 'București',
                                    'marketplace_state' => $marketplace?->state ?? 'București',
                                    'marketplace_email' => $marketplace?->contact_email ?? $marketplace?->email ?? 'office@marketplace.ro',
                                    'marketplace_phone' => $marketplace?->contact_phone ?? $marketplace?->phone ?? '+40 21 987 6543',
                                    'marketplace_website' => $marketplace?->domain ?? 'www.marketplace.ro',
                                    'marketplace_bank_name' => $marketplace?->bank_name ?? 'Banca Transilvania',
                                    'marketplace_contract_number' => $marketplace?->getCurrentContractNumber() ?? '1',
                                    'marketplace_signature_image' => $marketplace?->signature_image
                                        ? '<img src="' . \Illuminate\Support\Facades\Storage::disk('public')->url($marketplace->signature_image) . '" alt="Semnătura" style="max-height:80px;max-width:200px;" />'
                                        : '<span style="color:#999;font-style:italic;">[Signature Image]</span>',

                                    // Organizer
                                    'organizer_name' => 'Sample Organizer',
                                    'organizer_email' => 'organizer@sample.ro',
                                    'organizer_company_name' => 'Event Organizer SRL',
                                    'organizer_tax_id' => 'RO11223344',
                                    'organizer_registration_number' => 'J40/5678/2024',
                                    'organizer_address' => 'Str. Organizator Nr. 50',
                                    'organizer_city' => 'București',
                                    'organizer_county' => 'București',
                                    'organizer_vat_status' => 'plătitor TVA bilete (cota 19%)',
                                    'organizer_work_mode' => 'Exclusiv',
                                    'organizer_bank_name' => 'Banca Transilvania',
                                    'organizer_iban' => 'RO49BTRL1234567890123456',

                                    // Guarantor
                                    'guarantor_first_name' => 'Ion',
                                    'guarantor_last_name' => 'Popescu',
                                    'guarantor_cnp' => '1850101123456',
                                    'guarantor_id_type' => 'CI',
                                    'guarantor_id_series' => 'XY',
                                    'guarantor_id_number' => '123456',
                                    'guarantor_id_issued_by' => 'SPCLEP București',
                                    'guarantor_id_issued_date' => '15.03.2020',
                                    'guarantor_address' => 'Str. Exemplu Nr. 10, Ap. 5',
                                    'guarantor_city' => 'București',

                                    // Event
                                    'event_name' => 'Sample Concert 2024',
                                    'event_date' => date('d.m.Y H:i', strtotime('+30 days')),
                                    'event_city' => 'București',
                                    'venue_name' => 'Arena Exemplu',
                                    'venue_address' => 'Str. Arenei Nr. 1, București',

                                    // Tickets
                                    'ticket_types_table' => '<table style="width:100%; border-collapse: collapse;"><thead><tr><th style="border:1px solid #ddd; padding:8px; text-align:left;">Ticket Type</th><th style="border:1px solid #ddd; padding:8px; text-align:right;">Price</th><th style="border:1px solid #ddd; padding:8px; text-align:right;">Available</th><th style="border:1px solid #ddd; padding:8px; text-align:right;">Sold</th></tr></thead><tbody><tr><td style="border:1px solid #ddd; padding:8px;">General Admission</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">150.00 RON</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">500</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">245</td></tr><tr><td style="border:1px solid #ddd; padding:8px;">VIP</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">350.00 RON</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">100</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">52</td></tr></tbody></table>',
                                    'ticket_types_series' => "General Admission: GA001 - GA500\nVIP: VIP001 - VIP100",
                                    'ticket_types_rows' => '<tr><td class="left-align">General Admission</td><td>500</td><td>150.00</td><td>75,000.00</td><td><span class="underline-blue">GA001 - GA500</span></td></tr><tr><td class="left-align">VIP</td><td>100</td><td>350.00</td><td>35,000.00</td><td><span class="underline-blue">VIP001 - VIP100</span></td></tr>',
                                    'ticket_types_total_row' => '<tr class="total-row"><td><span class="bold">TOTAL</span></td><td>600</td><td>X</td><td>110,000.00</td><td>X</td></tr>',
                                    'total_tickets_for_sale' => '600',
                                    'total_value_for_sale' => '110,000.00',
                                    'total_tickets_available' => '600',
                                    'total_tickets_sold' => '297',
                                    'total_sales_value' => '54,950.00',
                                    'total_sales_currency' => 'RON',

                                    // Orders
                                    'order_number' => 'ORD-2024-00123',
                                    'order_date' => date('d.m.Y H:i'),
                                    'order_total' => '150.00',
                                    'order_currency' => 'RON',
                                    'customer_name' => 'Ion Popescu',
                                    'customer_email' => 'ion.popescu@example.com',

                                    // Contract
                                    'contract_number_series' => 'AMB001',
                                    'contract_date' => '15.01.2024',

                                    // Payout
                                    'payout_number' => 'DEC00123',
                                    'payout_date' => date('d.m.Y'),
                                    'payout_amount' => '6,947.00',
                                    'payout_currency' => 'RON',
                                    'payout_gross_amount' => '7,065.00',
                                    'payout_commission_amount' => '0.00',
                                    'payout_commission_percent' => '6%',
                                    'payout_fees_amount' => '0.00',
                                    'payout_adjustments_amount' => '118.00',
                                    'payout_payment_reference' => 'VB-2024-00123',

                                    // Invoice
                                    'invoice_number' => 'FACT-00456',
                                    'invoice_date' => date('d.m.Y'),
                                    'invoice_due_date' => date('d.m.Y', strtotime('+30 days')),
                                    'invoice_amount' => '1,500.00',
                                    'invoice_currency' => 'RON',
                                    'invoice_vat_amount' => '285.00',
                                    'invoice_subtotal' => '1,215.00',

                                    // Date/Time variables
                                    'current_day' => date('d'),
                                    'current_month' => date('m'),
                                    'current_month_name' => date('F'),
                                    'current_year' => date('Y'),
                                    'current_date' => date('d.m.Y'),
                                    'current_datetime' => date('d.m.Y H:i'),
                                ];

                                // Process template
                                $processed = $htmlContent;
                                foreach ($sampleVariables as $key => $value) {
                                    $processed = preg_replace(
                                        '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                                        $value,
                                        $processed
                                    );
                                }

                                // Replace any remaining unreplaced variables with highlighted placeholders
                                $processed = preg_replace(
                                    '/\{\{\s*(\w+)\s*\}\}/',
                                    '<span style="background:#fef3c7;color:#92400e;padding:1px 3px;border-radius:2px;font-size:8px;">$1</span>',
                                    $processed
                                );

                                // Determine page orientation
                                $orientation = $get('page_orientation') ?? 'portrait';
                                $isLandscape = $orientation === 'landscape';
                                $pageWidth = $isLandscape ? '297mm' : '210mm';
                                $pageHeight = $isLandscape ? '210mm' : '297mm';
                                $iframeHeight = $isLandscape ? '600px' : '900px';

                                // Wrap in A4-like page structure (same as DomPDF rendering)
                                $pageSize = $isLandscape ? 'A4 landscape' : 'A4';
                                $wrappedHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style>
                                    @page { size: ' . $pageSize . '; margin: 10mm 12mm; }
                                    body { font-family: DejaVu Sans, Arial, sans-serif; margin: 0; padding: 30px 35px; background: #fff; color: #000; }
                                </style></head><body>' . $processed . '</body></html>';

                                // Escape the processed HTML for use in srcdoc attribute
                                $escapedHtml = htmlspecialchars($wrappedHtml, ENT_QUOTES, 'UTF-8');

                                // Use iframe with srcdoc to completely isolate CSS styles — A4 page simulation
                                return new HtmlString(
                                    '<div style="background:#e5e7eb; padding:20px; border-radius:8px; overflow-x:auto;">' .
                                    '<div style="width:' . $pageWidth . '; margin:0 auto; box-shadow:0 2px 8px rgba(0,0,0,0.15);">' .
                                    '<iframe srcdoc="' . $escapedHtml . '" ' .
                                    'style="width:' . $pageWidth . '; min-height:' . $pageHeight . '; height:' . $iframeHeight . '; border:none; background:white; display:block;" ' .
                                    'sandbox="allow-same-origin allow-scripts" ' .
                                    'title="Template Preview">' .
                                    '</iframe>' .
                                    '</div>' .
                                    '</div>'
                                );
                            }),
                    ])
                    ->collapsible(),
            ]) ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MarketplaceTaxTemplate::TYPES[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'invoice' => 'success',
                        'receipt' => 'info',
                        'fiscal_receipt' => 'warning',
                        'proforma' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('trigger')
                    ->label('Trigger')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (MarketplaceTaxTemplate::TRIGGERS[$state] ?? ucfirst($state)) : '-')
                    ->color(fn (?string $state): string => match ($state) {
                        'after_event_published' => 'info',
                        'after_event_finished' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('page_orientation')
                    ->label('Orientation')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'landscape' ? 'Landscape' : 'Portrait')
                    ->color(fn (?string $state): string => $state === 'landscape' ? 'warning' : 'info')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(MarketplaceTaxTemplate::TYPES),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxTemplates::route('/'),
            'create' => Pages\CreateTaxTemplate::route('/create'),
            'edit' => Pages\EditTaxTemplate::route('/{record}/edit'),
        ];
    }
}
