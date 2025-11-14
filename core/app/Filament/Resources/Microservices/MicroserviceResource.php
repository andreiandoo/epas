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

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->columnSpanFull()
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
                        ->helperText('Small icon for UI cards (recommended: 64x64px or 128x128px)'),

                    Forms\Components\FileUpload::make('public_image')
                        ->label('Public Page Image')
                        ->image()
                        ->disk('public')
                        ->directory('microservices/public')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->helperText('Larger image for public pages (recommended: 800x600px or larger)'),
                ])->columns(2),

            SC\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Price')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(0.01)
                        ->prefix('RON')
                        ->required()
                        ->helperText('Base price for this microservice'),

                    Forms\Components\Select::make('pricing_model')
                        ->label('Pricing Model')
                        ->options([
                            'monthly' => 'Monthly Subscription',
                            'yearly' => 'Yearly Subscription',
                            'one-time' => 'One-time Payment',
                            'per-use' => 'Pay Per Use',
                        ])
                        ->default('monthly')
                        ->required()
                        ->helperText('How this microservice is billed'),
                ])->columns(2),

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

                    Forms\Components\KeyValue::make('features')
                        ->label('Features List')
                        ->columnSpanFull()
                        ->helperText('Key features of this microservice (key: feature description)'),
                ])->columns(2),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Microservice $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('short_description')
                    ->label('Short Description')
                    ->limit(50)
                    ->toggleable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('pricing_model')
                    ->label('Pricing')
                    ->colors([
                        'primary' => 'monthly',
                        'success' => 'yearly',
                        'warning' => 'one-time',
                        'info' => 'per-use',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Active Tenants')
                    ->counts('tenants')
                    ->sortable()
                    ->toggleable(),
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
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                        'one-time' => 'One-time',
                        'per-use' => 'Per Use',
                    ]),
            ])
            ->actions([])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMicroservices::route('/'),
            'create' => Pages\CreateMicroservice::route('/create'),
            'edit' => Pages\EditMicroservice::route('/{record}/edit'),
        ];
    }
}
