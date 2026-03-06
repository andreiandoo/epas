<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EmailTemplateResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceEmailTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\HtmlString;

class EmailTemplateResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceEmailTemplate::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Email Templates';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Grid::make(3)
                    ->schema([
                        // LEFT COLUMN — Email Content (2/3 width)
                        SC\Group::make([
                            SC\Section::make('Email Content')
                                ->schema([
                                    Forms\Components\TextInput::make('subject')
                                        ->label('Subject')
                                        ->required()
                                        ->maxLength(255)
                                        ->helperText('Suportă variabile: {{customer_name}}, {{order_number}}, etc.'),
                                    Forms\Components\Textarea::make('body_html')
                                        ->label('HTML Body')
                                        ->required()
                                        ->rows(25)
                                        ->columnSpanFull()
                                        ->extraAttributes(['id' => 'body-html-editor'])
                                        ->helperText('Cod HTML complet al emailului. Folosește {{variable}} pentru conținut dinamic.'),
                                    SC\View::make('filament.marketplace.components.html-live-preview')
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('body_text')
                                        ->label('Plain Text Body')
                                        ->rows(5)
                                        ->columnSpanFull()
                                        ->helperText('Versiune text simplu (opțional). Se generează automat din HTML dacă lipsește.'),
                                ]),
                        ])->columnSpan(2),

                        // RIGHT COLUMN — Template Details + Variables (1/3 width)
                        SC\Group::make([
                            SC\Section::make('Template Details')
                                ->schema([
                                    Forms\Components\Select::make('slug')
                                        ->label('Template Type')
                                        ->options(MarketplaceEmailTemplate::TEMPLATE_SLUGS)
                                        ->required()
                                        ->live()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                            $marketplace = static::getMarketplaceClient();
                                            return $rule->where('marketplace_client_id', $marketplace?->id);
                                        })
                                        ->helperText('Fiecare tip poate exista o singură dată'),
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->helperText('Nume intern'),
                                    Forms\Components\Select::make('category')
                                        ->options([
                                            'transactional' => 'Tranzacțional',
                                            'notification' => 'Notificare',
                                            'marketing' => 'Marketing',
                                        ])
                                        ->default('transactional'),
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true),
                                ]),

                            SC\Section::make('Variabile disponibile')
                                ->schema([
                                    Forms\Components\Placeholder::make('variables_help')
                                        ->content(function ($get) {
                                            $slug = $get('slug');
                                            if (!$slug) {
                                                return new HtmlString('<span class="text-sm text-gray-500">Selectează un tip de template pentru a vedea variabilele disponibile.</span>');
                                            }
                                            $template = new MarketplaceEmailTemplate(['slug' => $slug]);
                                            $vars = $template->getAvailableVariables();
                                            if (empty($vars)) {
                                                return new HtmlString('<span class="text-sm text-gray-500">Variabilele comune (customer_name, customer_email, marketplace_name) sunt disponibile.</span>');
                                            }
                                            $html = '<div class="text-sm space-y-1">';
                                            foreach ($vars as $key => $desc) {
                                                $html .= '<div><code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs font-mono">{{' . e($key) . '}}</code> <span class="text-gray-500">— ' . e($desc) . '</span></div>';
                                            }
                                            $html .= '</div>';
                                            return new HtmlString($html);
                                        })
                                        ->label(''),
                                ]),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MarketplaceEmailTemplate::TEMPLATE_SLUGS[$state] ?? $state),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'transactional' => 'success',
                        'notification' => 'info',
                        'marketing' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slug')
                    ->label('Type')
                    ->options(MarketplaceEmailTemplate::TEMPLATE_SLUGS),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'transactional' => 'Tranzacțional',
                        'notification' => 'Notificare',
                        'marketing' => 'Marketing',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn ($record) => view('filament.marketplace.email-preview', ['template' => $record]))
                    ->modalHeading('Email Preview')
                    ->modalSubmitAction(false),
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
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
