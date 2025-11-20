<?php

namespace App\Filament\Resources\Microservices;

use App\Models\Microservice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use BackedEnum;

class MicroserviceResource extends Resource
{
    protected static ?string $model = Microservice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static \UnitEnum|string|null $navigationGroup = 'Core';
    protected static ?int $navigationSort = 15;
    protected static ?string $modelLabel = 'Microservice';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Grid::make(3)->schema([
                // Left column - Details + Pricing + Images
                SC\Grid::make(1)->schema([
                    SC\Section::make('Microservice Details')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                    if (!$state) return;
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }),

                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),

                            Forms\Components\RichEditor::make('description')
                                ->label('Description')
                                ->columnSpanFull()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList',
                                    'h2', 'h3',
                                    'link', 'blockquote',
                                    'redo', 'undo',
                                ])
                                ->helperText('Full description of this microservice'),

                            Forms\Components\Textarea::make('short_description')
                                ->label('Short Description')
                                ->rows(2)
                                ->columnSpanFull()
                                ->helperText('Brief description for cards (1-2 sentences)'),

                            Forms\Components\TextInput::make('icon')
                                ->label('Icon')
                                ->helperText('Heroicon name (e.g., heroicon-o-shield-check)')
                                ->maxLength(255),
                        ])->columns(2),

                    SC\Section::make('Pricing')
                        ->schema([
                            Forms\Components\TextInput::make('price')
                                ->label('Price')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->step(0.01)
                                ->prefix('EUR')
                                ->required()
                                ->helperText('Base price for this microservice'),

                            Forms\Components\Select::make('pricing_model')
                                ->label('Pricing Model')
                                ->options([
                                    'recurring' => 'Recurring Subscription',
                                    'one_time' => 'One-time Payment',
                                    'usage' => 'Pay Per Use',
                                ])
                                ->default('recurring')
                                ->required()
                                ->helperText('How this microservice is billed'),

                            Forms\Components\Select::make('billing_cycle')
                                ->label('Billing Cycle')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'yearly' => 'Yearly',
                                    'one_time' => 'One-time',
                                ])
                                ->default('monthly')
                                ->helperText('Payment frequency'),

                            Forms\Components\TextInput::make('category')
                                ->label('Category')
                                ->helperText('Category for grouping (e.g., compliance, communication)'),
                        ])->columns(2),

                    SC\Section::make('Images')
                        ->description('Upload images for different display contexts')
                        ->schema([
                            Forms\Components\FileUpload::make('icon_image')
                                ->label('Icon Image')
                                ->image()
                                ->disk('public')
                                ->directory('microservices/icons')
                                ->visibility('public')
                                ->maxSize(2048)
                                ->helperText('Small icon for UI cards (64x64px or 128x128px)'),

                            Forms\Components\FileUpload::make('public_image')
                                ->label('Public Page Image')
                                ->image()
                                ->disk('public')
                                ->directory('microservices/public')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->helperText('Larger image for public pages (800x600px or larger)'),
                        ])->columns(2),
                ])->columnSpan(2),

                // Right column - Configuration
                SC\Grid::make(1)->schema([
                    SC\Section::make('Configuration')
                        ->schema([
                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->helperText('Whether this microservice is available for activation'),

                            Forms\Components\TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Display order (lower numbers first)'),

                            Forms\Components\TagsInput::make('features')
                                ->label('Features List')
                                ->helperText('Key features of this microservice')
                                ->placeholder('Add a feature'),

                            Forms\Components\TextInput::make('documentation_url')
                                ->label('Documentation URL')
                                ->url()
                                ->helperText('Link to documentation'),
                        ])->columns(1),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\ImageColumn::make('icon_image')
                            ->disk('public')
                            ->circular()
                            ->size(48)
                            ->defaultImageUrl(fn () => 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.401.604-.401.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 00.659-.663 47.703 47.703 0 00-.31-4.82 48.847 48.847 0 01-6.067.21v0a.64.64 0 01-.657-.643v0z" /></svg>')
                            ->grow(false),
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('name')
                                ->weight('bold')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('short_description')
                                ->size('sm')
                                ->color('gray')
                                ->limit(80)
                                ->default('No description'),
                        ])->space(1),
                    ]),
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('price')
                            ->money('EUR')
                            ->size('sm')
                            ->color('success'),
                        Tables\Columns\BadgeColumn::make('pricing_model')
                            ->colors([
                                'primary' => 'recurring',
                                'success' => 'one_time',
                                'info' => 'usage',
                            ]),
                        Tables\Columns\IconColumn::make('is_active')
                            ->boolean()
                            ->size('sm'),
                    ])->from('md'),
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('category')
                            ->badge()
                            ->color('gray')
                            ->size('sm'),
                        Tables\Columns\TextColumn::make('tenants_count')
                            ->counts('tenants')
                            ->label('Tenants')
                            ->suffix(' tenants')
                            ->size('sm')
                            ->color('gray'),
                    ])->from('md'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                Tables\Filters\SelectFilter::make('pricing_model')
                    ->label('Pricing Model')
                    ->options([
                        'recurring' => 'Recurring',
                        'one_time' => 'One-time',
                        'usage' => 'Per Use',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn () => Microservice::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),
                Tables\Actions\Action::make('tenants')
                    ->icon('heroicon-o-building-office-2')
                    ->iconButton()
                    ->url(fn (Microservice $record) => static::getUrl('tenants', ['record' => $record])),
            ])
            ->recordUrl(fn (Microservice $record) => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMicroservices::route('/'),
            'create' => Pages\CreateMicroservice::route('/create'),
            'edit' => Pages\EditMicroservice::route('/{record}/edit'),
            'tenants' => Pages\ViewMicroserviceTenants::route('/{record}/tenants'),
        ];
    }
}
