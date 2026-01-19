<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceTaxTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\HtmlString;
use BackedEnum;
use UnitEnum;

class MarketplaceTaxTemplateResource extends Resource
{
    protected static ?string $model = MarketplaceTaxTemplate::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tax Templates';
    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 51;
    protected static ?string $modelLabel = 'Tax Template';
    protected static ?string $pluralModelLabel = 'Tax Templates';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Marketplace')
                    ->schema([
                        Forms\Components\Select::make('marketplace_client_id')
                            ->label('Marketplace')
                            ->relationship('marketplaceClient', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Template Details')
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

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default for this type')
                            ->helperText('Only one template can be default per type'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Available Variables')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_info')
                            ->label('')
                            ->content(function () {
                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                                foreach (MarketplaceTaxTemplate::TEMPLATE_VARIABLES as $section => $variables) {
                                    $html .= '<div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">';
                                    $html .= '<h4 class="font-semibold text-sm mb-2 text-primary-600 dark:text-primary-400">' . $section . '</h4>';
                                    $html .= '<div class="space-y-1">';
                                    foreach ($variables as $var => $label) {
                                        $html .= '<div class="flex items-center justify-between text-xs">';
                                        $html .= '<code class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded cursor-pointer hover:bg-primary-100 dark:hover:bg-primary-900" onclick="navigator.clipboard.writeText(\'' . $var . '\'); this.classList.add(\'ring-2\', \'ring-primary-500\'); setTimeout(() => this.classList.remove(\'ring-2\', \'ring-primary-500\'), 500);">' . $var . '</code>';
                                        $html .= '<span class="text-gray-500 dark:text-gray-400 ml-2">' . $label . '</span>';
                                        $html .= '</div>';
                                    }
                                    $html .= '</div></div>';
                                }
                                $html .= '</div>';
                                $html .= '<p class="text-xs text-gray-500 mt-3">Click on a variable to copy it to clipboard</p>';
                                return new HtmlString($html);
                            }),
                    ])
                    ->collapsed()
                    ->collapsible(),

                Section::make('Template Content')
                    ->schema([
                        Forms\Components\Textarea::make('html_content')
                            ->label('HTML Content')
                            ->rows(20)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Use the variables above in your template. They will be replaced with actual values when generating documents.'),
                    ]),

                Section::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('')
                            ->content(function ($get) {
                                $htmlContent = $get('html_content');
                                if (empty($htmlContent)) {
                                    return new HtmlString('<p class="text-gray-500">Enter HTML content above to see the preview.</p>');
                                }

                                // Replace variables with sample data for preview
                                $sampleData = [
                                    '{{tax_registry_country}}' => 'Romania',
                                    '{{tax_registry_county}}' => 'Bucuresti',
                                    '{{tax_registry_city}}' => 'Bucuresti',
                                    '{{tax_registry_name}}' => 'Tax Office Sector 1',
                                    '{{tax_registry_subname}}' => 'Department A',
                                    '{{tax_registry_address}}' => 'Str. Example Nr. 123',
                                    '{{tax_registry_phone}}' => '+40 21 123 4567',
                                    '{{tax_registry_email}}' => 'office@tax.gov.ro',
                                    '{{tax_registry_cif}}' => 'RO12345678',
                                    '{{tax_registry_iban}}' => 'RO49AAAA1B31007593840000',
                                    '{{marketplace_legal_name}}' => 'Events Platform SRL',
                                    '{{marketplace_vat}}' => 'RO87654321',
                                    '{{marketplace_trade_register}}' => 'J40/1234/2024',
                                    '{{marketplace_address}}' => 'Bd. Unirii Nr. 1',
                                    '{{marketplace_city}}' => 'Bucuresti',
                                    '{{marketplace_state}}' => 'Bucuresti',
                                    '{{marketplace_email}}' => 'contact@platform.ro',
                                    '{{marketplace_phone}}' => '+40 21 987 6543',
                                    '{{marketplace_website}}' => 'www.platform.ro',
                                    '{{organizer_name}}' => 'Concert Organizer SRL',
                                    '{{organizer_email}}' => 'events@organizer.ro',
                                    '{{organizer_company_name}}' => 'Concert Events SRL',
                                    '{{organizer_tax_id}}' => 'RO11111111',
                                    '{{organizer_registration_number}}' => 'J40/5678/2024',
                                    '{{organizer_address}}' => 'Str. Muzicii Nr. 10',
                                    '{{event_name}}' => 'Summer Festival 2024',
                                    '{{event_date}}' => '15.08.2024 19:00',
                                    '{{venue_name}}' => 'Arena Nationala',
                                    '{{venue_address}}' => 'Bd. Basarabia Nr. 37-39',
                                    '{{ticket_types_table}}' => '<table style="width:100%;border-collapse:collapse;"><thead><tr><th style="border:1px solid #ddd;padding:8px;">Type</th><th style="border:1px solid #ddd;padding:8px;">Price</th><th style="border:1px solid #ddd;padding:8px;">Qty</th></tr></thead><tbody><tr><td style="border:1px solid #ddd;padding:8px;">General</td><td style="border:1px solid #ddd;padding:8px;">150.00 RON</td><td style="border:1px solid #ddd;padding:8px;">500</td></tr><tr><td style="border:1px solid #ddd;padding:8px;">VIP</td><td style="border:1px solid #ddd;padding:8px;">350.00 RON</td><td style="border:1px solid #ddd;padding:8px;">100</td></tr></tbody></table>',
                                    '{{order_number}}' => 'ORD-2024-00001',
                                    '{{order_date}}' => '10.01.2024 14:30',
                                    '{{order_total}}' => '300.00',
                                    '{{order_currency}}' => 'RON',
                                    '{{customer_name}}' => 'Ion Popescu',
                                    '{{customer_email}}' => 'ion.popescu@email.ro',
                                ];

                                $preview = $htmlContent;
                                foreach ($sampleData as $var => $value) {
                                    $preview = str_replace($var, $value, $preview);
                                }

                                return new HtmlString(
                                    '<div class="border rounded-lg p-4 bg-white dark:bg-gray-900 overflow-auto max-h-[600px]">' .
                                    '<div class="prose dark:prose-invert max-w-none">' . $preview . '</div>' .
                                    '</div>'
                                );
                            }),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MarketplaceTaxTemplate::TYPES[$state] ?? $state),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),

                Tables\Filters\SelectFilter::make('type')
                    ->options(MarketplaceTaxTemplate::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceTaxTemplateResource\Pages\ListMarketplaceTaxTemplates::route('/'),
            'create' => MarketplaceTaxTemplateResource\Pages\CreateMarketplaceTaxTemplate::route('/create'),
            'view' => MarketplaceTaxTemplateResource\Pages\ViewMarketplaceTaxTemplate::route('/{record}'),
            'edit' => MarketplaceTaxTemplateResource\Pages\EditMarketplaceTaxTemplate::route('/{record}/edit'),
        ];
    }
}
