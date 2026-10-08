<?php

namespace App\Filament\Tenant\Resources\ShopProductResource\RelationManagers;

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
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(100)
                    ->default(fn () => strtoupper(Str::random(8))),

                Forms\Components\TextInput::make('name')
                    ->label(__('Variant Name'))
                    ->maxLength(200)
                    ->placeholder(__('Auto-generated from attributes')),

                Forms\Components\TextInput::make('price')
                    ->label(__('Price'))
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->prefix('RON')
                    ->placeholder(__('Use product price')),

                Forms\Components\TextInput::make('sale_price')
                    ->label(__('Sale Price'))
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->prefix('RON')
                    ->placeholder(__('Leave empty if no sale')),

                Forms\Components\TextInput::make('stock_quantity')
                    ->label(__('Stock'))
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('weight_grams')
                    ->label(__('Weight (g)'))
                    ->numeric()
                    ->placeholder(__('Use product weight')),

                Forms\Components\FileUpload::make('image_url')
                    ->label(__('Variant Image'))
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
                    ->helperText(__('Drag & drop or click to upload. Max 5MB')),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),

                Forms\Components\TextInput::make('sort_order')
                    ->label(__('Sort Order'))
                    ->numeric()
                    ->default(0),

                Forms\Components\Select::make('attributeValues')
                    ->label(__('Attribute Values'))
                    ->multiple()
                    ->relationship('attributeValues', 'slug')
                    ->options(function () use ($tenantLanguage) {
                        $tenant = auth()->user()->tenant;
                        return ShopAttributeValue::whereHas('attribute', fn ($q) => $q->where('tenant_id', $tenant?->id))
                            ->with('attribute')
                            ->get()
                            ->mapWithKeys(function ($value) use ($tenantLanguage) {
                                $attrName = $value->attribute->name[$tenantLanguage] ?? $value->attribute->slug;
                                $valueName = $value->value[$tenantLanguage] ?? $value->slug;
                                return [$value->id => "{$attrName}: {$valueName}"];
                            });
                    })
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(__('Image'))
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://placehold.co/50x50/EEE/31343C?text=-'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('attributeValues.value')
                    ->label(__('Attributes'))
                    ->formatStateUsing(function ($state, $record) use ($tenantLanguage) {
                        return $record->attributeValues
                            ->map(fn ($v) => $v->value[$tenantLanguage] ?? $v->slug)
                            ->join(', ');
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) return '—';
                        return number_format($state, 2) . ' ' . ($record->product->currency ?? 'RON');
                    }),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label(__('Stock'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),
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
