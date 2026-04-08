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

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

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

                        Forms\Components\Toggle::make('by_proxy')
                            ->label('Prin împuternicit')
                            ->default(false),

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

                Section::make('Taxe generale aplicabile')
                    ->description('Selectează care taxe generale (timbru, etc.) se aplică automat în acest template. Doar taxele care au "Event Type" potrivit cu tipul evenimentului vor fi calculate efectiv.')
                    ->icon('heroicon-o-receipt-percent')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('general_tax_ids')
                            ->label('Taxe aplicabile')
                            ->options(function () {
                                return \App\Models\Tax\GeneralTax::where('is_active', true)
                                    ->orderBy('priority')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($tax) => [
                                        $tax->id => $tax->name . ' (' . rtrim(rtrim(number_format((float) $tax->value, 2, '.', ''), '0'), '.') . ($tax->value_type === 'percentage' ? '%' : ' ' . ($tax->currency ?? 'RON')) . ')',
                                    ]);
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Variabile generate: {{music_stamp_value}} (suma totală taxe), {{taxable_income}}, {{tax_due}}.'),
                    ]),

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
                            ->helperText('Edit raw HTML code with inline CSS. Click outside textarea to refresh preview.')
                            ->formatStateUsing(fn ($record) => $record?->html_content)
                            ->live(onBlur: true)
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
                            ->helperText('Edit raw HTML code with inline CSS. Click outside textarea to refresh preview.')
                            ->formatStateUsing(fn ($record) => $record?->html_content_page_2)
                            ->live(onBlur: true)
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
                                $sourceMode1 = $get('page1_source_mode');
                                $htmlContent1 = $sourceMode1
                                    ? ($get('html_content_source') ?: $record?->html_content)
                                    : $get('html_content');

                                $sourceMode2 = $get('page2_source_mode');
                                $htmlContent2 = $sourceMode2
                                    ? ($get('html_content_page_2_source') ?: $record?->html_content_page_2)
                                    : $get('html_content_page_2');

                                // Treat empty HTML (whitespace, empty tags) as no content
                                $hasContent1 = $htmlContent1 && trim(strip_tags($htmlContent1)) !== '';
                                $hasContent2 = $htmlContent2 && trim(strip_tags($htmlContent2)) !== '';

                                if (!$hasContent1 && !$hasContent2) {
                                    return new HtmlString('<div class="text-gray-500 italic p-4 text-center">Enter HTML content above to see preview</div>');
                                }

                                $sampleVariables = static::getSampleVariables();

                                $output = '<div style="background:#e5e7eb; padding:20px; border-radius:8px; overflow-x:auto;">';

                                // Page 1 preview
                                if ($hasContent1) {
                                    $processed1 = static::processPreviewHtml($htmlContent1, $sampleVariables);
                                    $orientation1 = $get('page_orientation') ?? 'portrait';
                                    $output .= static::renderPagePreview($processed1, $orientation1, 'Page 1');
                                }

                                // Page 2 preview
                                if ($hasContent2) {
                                    $processed2 = static::processPreviewHtml($htmlContent2, $sampleVariables);
                                    $orientation2 = $get('page_2_orientation') ?? $get('page_orientation') ?? 'portrait';
                                    $output .= static::renderPagePreview($processed2, $orientation2, 'Page 2');
                                }

                                $output .= '</div>';
                                return new HtmlString($output);
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

                Tables\Columns\IconColumn::make('by_proxy')
                    ->label('Împuternicit')
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

    /**
     * Process HTML content with sample variables for preview
     */
    protected static function processPreviewHtml(string $html, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $html = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                $value,
                $html
            );
        }

        // Highlight unreplaced variables
        return preg_replace(
            '/\{\{\s*(\w+)\s*\}\}/',
            '<span style="background:#fef3c7;color:#92400e;padding:1px 3px;border-radius:2px;font-size:8px;">$1</span>',
            $html
        );
    }

    /**
     * Render an A4 page preview iframe
     */
    protected static function renderPagePreview(string $processedHtml, string $orientation, string $label): string
    {
        $isLandscape = $orientation === 'landscape';
        $pageWidth = $isLandscape ? '297mm' : '210mm';
        $pageHeight = $isLandscape ? '210mm' : '297mm';
        $iframeHeight = $isLandscape ? '600px' : '900px';
        $pageSize = $isLandscape ? 'A4 landscape' : 'A4';

        $wrappedHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style>
            @page { size: ' . $pageSize . '; margin: 10mm 12mm; }
            body { font-family: DejaVu Sans, Arial, sans-serif; margin: 0; padding: 30px 35px; background: #fff; color: #000; }
        </style></head><body>' . $processedHtml . '</body></html>';

        $escapedHtml = htmlspecialchars($wrappedHtml, ENT_QUOTES, 'UTF-8');

        return '<div style="margin-bottom:16px;">' .
            '<div style="text-align:center; margin-bottom:8px; font-weight:600; color:#374151; font-size:14px;">' . $label . ' (' . ($isLandscape ? 'Landscape' : 'Portrait') . ')</div>' .
            '<div style="width:' . $pageWidth . '; margin:0 auto; box-shadow:0 2px 8px rgba(0,0,0,0.15);">' .
            '<iframe srcdoc="' . $escapedHtml . '" ' .
            'style="width:' . $pageWidth . '; min-height:' . $pageHeight . '; height:' . $iframeHeight . '; border:none; background:white; display:block;" ' .
            'sandbox="allow-same-origin allow-scripts" ' .
            'title="' . $label . ' Preview">' .
            '</iframe>' .
            '</div>' .
            '</div>';
    }

    /**
     * Get sample variables for preview
     */
    protected static function getSampleVariables(): array
    {
        $marketplace = static::getMarketplaceClient();

        return [
            // Tax Registry
            'tax_registry_country' => 'Romania',
            'tax_registry_county' => 'București',
            'tax_registry_city' => 'București',
            'tax_registry_commune' => 'Sector 1',
            'tax_registry_name' => 'Direcția Venituri Buget Local',
            'tax_registry_subname' => 'Serviciul Impozite și Taxe',
            'tax_registry_address' => 'Str. Exemplu Nr. 123',
            'tax_registry_directions' => 'Etaj 2, Camera 15',
            'tax_registry_phone' => '+40 21 123 4567',
            'tax_registry_email' => 'contact@sample.ro',
            'tax_registry_email2' => 'taxe@sample.ro',
            'tax_registry_website_url' => 'www.taxe-locale.ro',
            'tax_registry_cif' => 'RO12345678',
            'tax_registry_iban' => 'RO49AAAA1B31007593840000',
            'tax_registry_siruta_code' => '179141',

            // Marketplace
            'marketplace_legal_name' => $marketplace?->company_name ?? $marketplace?->name ?? 'Marketplace SRL',
            'marketplace_vat' => $marketplace?->cui ?? 'RO98765432',
            'marketplace_trade_register' => $marketplace?->reg_com ?? 'J40/1234/2024',
            'marketplace_address' => $marketplace?->address ?? 'Bulevardul Central Nr. 1',
            'marketplace_city' => $marketplace?->city ?? 'București',
            'marketplace_state' => $marketplace?->state ?? 'București',
            'marketplace_email' => $marketplace?->contact_email ?? $marketplace?->email ?? 'office@marketplace.ro',
            'marketplace_phone' => $marketplace?->contact_phone ?? $marketplace?->phone ?? '+40 21 987 6543',
            'marketplace_website' => $marketplace?->domain ?? 'www.marketplace.ro',
            'marketplace_bank_name' => $marketplace?->bank_name ?? 'Banca Transilvania',
            'marketplace_iban' => $marketplace?->bank_account ?? 'RO49BTRL1234567890123456',
            'marketplace_contract_number' => $marketplace?->getCurrentContractNumber() ?? '1',
            'marketplace_signature_image' => $marketplace?->signature_image
                ? '<img src="' . \Illuminate\Support\Facades\Storage::disk('public')->url($marketplace->signature_image) . '" alt="Semnătura" style="max-height:80px;max-width:200px;" />'
                : '<span style="color:#999;font-style:italic;">[Semnătura]</span>',
            'marketplace_logo_url' => '<img src="' . ($marketplace?->settings['logo_url'] ?? '') . '" alt="Logo" style="max-height:44px;max-width:130px;display:block;" />',
            'marketplace_invoice_preparer' => $marketplace?->settings['invoice_preparer'] ?? 'Nume Prenume',

            // Organizer
            'organizer_name' => 'Sample Organizer',
            'organizer_email' => 'organizer@sample.ro',
            'organizer_phone' => '+40 721 123 456',
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

            // Proxy (împuternicit)
            'proxy_full_name' => 'Maria Ionescu',
            'proxy_role' => 'Administrator',
            'proxy_address' => 'Str. Libertății Nr. 25, Bl. A3, Sc. 2, Ap. 10',
            'proxy_country' => 'Romania',
            'proxy_county' => 'București',
            'proxy_city' => 'București',
            'proxy_sector' => '3',
            'proxy_id_series' => 'RD',
            'proxy_id_number' => '654321',
            'proxy_cnp' => '2850315400123',
            'proxy_phone' => '+40 721 234 567',

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

            // Decont impozit pe spectacole
            'music_stamp_value' => '1,099.00',
            'taxable_income' => '53,851.00',
            'tax_due' => '1,615.53',
            'document_number' => '00123',
            'tax_registry_tax_rate' => '3',
            'tax_registry_coat_of_arms' => '<div style="width:48px;height:48px;border:1px dashed #999;display:inline-block;line-height:48px;color:#999;font-size:9px;">stemă</div>',
            'proxy_signature_image' => '<span style="color:#999;font-style:italic;">[Semnătura electronică]</span>',

            // PV Distrugere - unsold tickets
            'unsold_tickets_rows' => '<tr><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">AMB-100-GA-00298 — AMB-100-GA-00500</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">203</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">150.00</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">30,450.00</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">General Admission</td></tr><tr><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">AMB-100-VIP-00068 — AMB-100-VIP-00100</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">33</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">350.00</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">11,550.00</td><td style="border:1.5px solid #111; padding:3px 5px; font-size:9px; text-align:center; vertical-align:middle;">VIP</td></tr>',
            'total_unsold_tickets' => '236',
            'total_unsold_value' => '42,000.00',

            'ticket_series_from' => 'GA001',
            'ticket_series_to' => 'GA500',
            'ticket_unit_price' => '150.00',

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
            'payout_vat_rate' => '0%',
            'payout_vat_amount' => '0.00',
            'payout_total_with_vat' => '0.00',
            'payout_payment_method' => 'virament bancar',
            'payout_bank_name' => 'Banca Transilvania',
            'payout_iban' => 'RO49BTRL1234567890123456',
            'payout_account_holder' => 'Event Organizer SRL',
            'payout_period_start' => date('01.m.Y'),
            'payout_period_end' => date('d.m.Y'),
            'payout_adjustments_note' => 'Bilete returnate',
            'payout_sequence_number' => '1',
            'payout_advance_amount' => '0.00',
            'payout_net_amount' => '6,500.00',
            'payout_commission_mode' => 'included',

            // Payout - Bilete
            'tickets_breakdown_label' => ' (150lei*2+350lei*1)',
            'total_tickets_refunded' => '0',

            // Payout - Bilete Pretipărite
            'payout_preprinted_ticket_fee' => '2.00',
            'total_preprinted_tickets' => '0',
            'payout_preprinted_amount' => '0.00',
            'payout_preprinted_shipping_date' => '',
            'payout_shipping_amount' => '0.00',

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
            'current_month_name' => self::romanianMonthName((int) date('m')),
            'current_year' => date('Y'),
            'current_date' => date('d.m.Y'),
            'current_datetime' => date('d.m.Y H:i'),
        ];
    }

    protected static function romanianMonthName(int $month): string
    {
        return match ($month) {
            1 => 'Ianuarie',
            2 => 'Februarie',
            3 => 'Martie',
            4 => 'Aprilie',
            5 => 'Mai',
            6 => 'Iunie',
            7 => 'Iulie',
            8 => 'August',
            9 => 'Septembrie',
            10 => 'Octombrie',
            11 => 'Noiembrie',
            12 => 'Decembrie',
            default => '',
        };
    }
}
