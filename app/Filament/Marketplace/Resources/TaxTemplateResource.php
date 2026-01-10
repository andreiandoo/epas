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

    protected static ?string $navigationLabel = 'Tax Templates';

    protected static ?string $modelLabel = 'Tax Template';

    protected static ?string $pluralModelLabel = 'Tax Templates';

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

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('Make this the default template for its type'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

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
                        Forms\Components\Select::make('page_orientation')
                            ->label('Page 1 Orientation')
                            ->options(MarketplaceTaxTemplate::ORIENTATIONS)
                            ->default('portrait')
                            ->required(),

                        Forms\Components\Textarea::make('html_content')
                            ->label('Page 1 HTML Template')
                            ->required()
                            ->rows(20)
                            ->columnSpanFull()
                            ->helperText('Use the variables above in your HTML. Example: {{marketplace_legal_name}}')
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                    ]),

                Section::make('Page 2 - HTML Content (Optional)')
                    ->icon('heroicon-o-document-duplicate')
                    ->description('Add a second page if your template needs multiple pages (e.g., one portrait, one landscape)')
                    ->schema([
                        Forms\Components\Select::make('page_2_orientation')
                            ->label('Page 2 Orientation')
                            ->options(MarketplaceTaxTemplate::ORIENTATIONS)
                            ->placeholder('Select orientation for page 2'),

                        Forms\Components\Textarea::make('html_content_page_2')
                            ->label('Page 2 HTML Template')
                            ->rows(20)
                            ->columnSpanFull()
                            ->helperText('Leave empty if you only need one page')
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Preview')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('')
                            ->content(function ($get, $record) {
                                $htmlContent = $get('html_content');

                                if (!$htmlContent) {
                                    return new HtmlString('<div class="text-gray-500 italic p-4 text-center">Enter HTML content above to see preview</div>');
                                }

                                // Get marketplace context for sample variables
                                $marketplace = static::getMarketplaceClient();

                                // Create sample data for preview
                                $sampleVariables = [
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

                                    'marketplace_legal_name' => $marketplace?->legal_name ?? 'Marketplace SRL',
                                    'marketplace_vat' => $marketplace?->vat_number ?? 'RO98765432',
                                    'marketplace_trade_register' => $marketplace?->trade_register ?? 'J40/1234/2024',
                                    'marketplace_address' => $marketplace?->address ?? 'Bulevardul Central Nr. 1',
                                    'marketplace_city' => $marketplace?->city ?? 'București',
                                    'marketplace_state' => $marketplace?->state ?? 'București',
                                    'marketplace_email' => $marketplace?->contact_email ?? 'office@marketplace.ro',
                                    'marketplace_phone' => $marketplace?->contact_phone ?? '+40 21 987 6543',
                                    'marketplace_website' => $marketplace?->domain ?? 'www.marketplace.ro',

                                    'organizer_name' => 'Sample Organizer',
                                    'organizer_email' => 'organizer@sample.ro',
                                    'organizer_company_name' => 'Event Organizer SRL',
                                    'organizer_tax_id' => 'RO11223344',
                                    'organizer_registration_number' => 'J40/5678/2024',
                                    'organizer_address' => 'Str. Organizator Nr. 50',

                                    'event_name' => 'Sample Concert 2024',
                                    'event_date' => date('d.m.Y H:i', strtotime('+30 days')),
                                    'venue_name' => 'Arena Exemplu',
                                    'venue_address' => 'Str. Arenei Nr. 1, București',
                                    'ticket_types_table' => '<table style="width:100%; border-collapse: collapse;"><thead><tr><th style="border:1px solid #ddd; padding:8px; text-align:left;">Ticket Type</th><th style="border:1px solid #ddd; padding:8px; text-align:right;">Price</th><th style="border:1px solid #ddd; padding:8px; text-align:right;">Available</th><th style="border:1px solid #ddd; padding:8px; text-align:right;">Sold</th></tr></thead><tbody><tr><td style="border:1px solid #ddd; padding:8px;">General Admission</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">150.00 RON</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">500</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">245</td></tr><tr><td style="border:1px solid #ddd; padding:8px;">VIP</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">350.00 RON</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">100</td><td style="border:1px solid #ddd; padding:8px; text-align:right;">52</td></tr></tbody></table>',
                                    'total_tickets_available' => '600',
                                    'total_tickets_sold' => '297',
                                    'total_sales_value' => '54,950.00',
                                    'total_sales_currency' => 'RON',

                                    'order_number' => 'ORD-2024-00123',
                                    'order_date' => date('d.m.Y H:i'),
                                    'order_total' => '150.00',
                                    'order_currency' => 'RON',
                                    'customer_name' => 'Ion Popescu',
                                    'customer_email' => 'ion.popescu@example.com',

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

                                return new HtmlString(
                                    '<div class="border rounded-lg p-4 bg-white dark:bg-gray-900 overflow-auto max-h-96">' .
                                    '<div class="mb-2 pb-2 border-b text-xs text-gray-500">Preview with sample data:</div>' .
                                    '<div class="prose dark:prose-invert max-w-none">' . $processed . '</div>' .
                                    '</div>'
                                );
                            }),
                    ])
                    ->collapsible(),
            ]);
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
