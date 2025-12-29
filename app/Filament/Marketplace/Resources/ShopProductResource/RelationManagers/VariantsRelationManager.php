<?php

namespace App\Filament\Marketplace\Resources\ShopProductResource\RelationManagers;

use App\Models\Shop\ShopAttributeValue;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(100)
                    ->default(fn () => strtoupper(Str::random(8))),

                Forms\Components\TextInput::make('name')
                    ->label('Variant Name')
                    ->maxLength(200)
                    ->placeholder('Auto-generated from attributes'),

                Forms\Components\TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->prefix('RON')
                    ->placeholder('Use product price'),

                Forms\Components\TextInput::make('sale_price')
                    ->label('Sale Price')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->prefix('RON')
                    ->placeholder('Leave empty if no sale'),

                Forms\Components\TextInput::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('weight_grams')
                    ->label('Weight (g)')
                    ->numeric()
                    ->placeholder('Use product weight'),

                Forms\Components\FileUpload::make('image_url')
                    ->label('Variant Image')
                    ->image()
                    ->imageEditor()
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('1:1')
                    ->imageResizeTargetWidth('400')
                    ->imageResizeTargetHeight('400')
                    ->disk('public')
                    ->directory('shop-variants')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Drag & drop or click to upload. Max 5MB'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),

                Forms\Components\Select::make('attributeValues')
                    ->label('Attribute Values')
                    ->multiple()
                    ->relationship('attributeValues', 'slug')
                    ->options(function () use ($marketplaceLanguage) {
                        $marketplace = static::getMarketplaceClient();
                        return ShopAttributeValue::whereHas('attribute', fn ($q) => $q->where('marketplace_client_id', $marketplace?->id))
                            ->with('attribute')
                            ->get()
                            ->mapWithKeys(function ($value) use ($marketplaceLanguage) {
                                $attrName = $value->attribute->name[$marketplaceLanguage] ?? $value->attribute->slug;
                                $valueName = $value->value[$marketplaceLanguage] ?? $value->slug;
                                return [$value->id => "{$attrName}: {$valueName}"];
                            });
                    })
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://placehold.co/50x50/EEE/31343C?text=-'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('attributeValues.value')
                    ->label('Attributes')
                    ->formatStateUsing(function ($state, $record) use ($marketplaceLanguage) {
                        return $record->attributeValues
                            ->map(fn ($v) => $v->value[$marketplaceLanguage] ?? $v->slug)
                            ->join(', ');
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) return '—';
                        return number_format($state, 2) . ' ' . ($record->product->currency ?? 'RON');
                    }),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
