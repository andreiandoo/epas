<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\KbCategoryResource\Pages;
use App\Models\KnowledgeBase\KbCategory;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;

class KbCategoryResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = KbCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'KB Categories';

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'KB Categories';

    protected static ?string $slug = 'kb-categories';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('knowledge-base');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make(__('Category Details'))
                    ->schema([
                        Forms\Components\TextInput::make("name.{$marketplaceLanguage}")
                            ->label(__('Category Name'))
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) $set('slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Textarea::make("description.{$marketplaceLanguage}")
                            ->label(__('Description'))
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_visible')
                            ->label(__('Visible'))
                            ->default(true),
                    ])->columns(2),

                SC\Section::make(__('Appearance'))
                    ->schema([
                        Forms\Components\Select::make('icon')
                            ->label(__('Icon'))
                            ->options([
                                'heroicon-o-ticket' => __('Ticket'),
                                'heroicon-o-calendar' => __('Calendar'),
                                'heroicon-o-user' => __('User'),
                                'heroicon-o-cog-6-tooth' => __('Settings'),
                                'heroicon-o-credit-card' => __('Credit Card'),
                                'heroicon-o-document-text' => __('Document'),
                                'heroicon-o-question-mark-circle' => __('Question'),
                                'heroicon-o-information-circle' => __('Information'),
                                'heroicon-o-bell' => __('Bell'),
                                'heroicon-o-envelope' => __('Envelope'),
                                'heroicon-o-shield-check' => __('Shield'),
                                'heroicon-o-lock-closed' => __('Lock'),
                                'heroicon-o-map-pin' => __('Location'),
                                'heroicon-o-phone' => __('Phone'),
                                'heroicon-o-building-office' => __('Building'),
                                'heroicon-o-banknotes' => __('Money'),
                                'heroicon-o-gift' => __('Gift'),
                                'heroicon-o-star' => __('Star'),
                                'heroicon-o-heart' => __('Heart'),
                                'heroicon-o-chart-bar' => __('Chart'),
                                'heroicon-o-folder' => __('Folder'),
                                'heroicon-o-clipboard-document-list' => __('Clipboard'),
                            ])
                            ->searchable()
                            ->placeholder(__('Select an icon')),

                        Forms\Components\ColorPicker::make('color')
                            ->label(__('Color')),

                        Forms\Components\TextInput::make('image_url')
                            ->label(__('Image URL'))
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $iconName = str_replace(['heroicon-o-', 'heroicon-s-'], '', $state);
                        return $iconName;
                    })
                    ->icon(fn ($state) => $state),

                Tables\Columns\ColorColumn::make('color')
                    ->label(__('Color')),

                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('article_count')
                    ->label(__('Articles'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label(__('Visible'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label(__('Visible')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKbCategories::route('/'),
            'create' => Pages\CreateKbCategory::route('/create'),
            'edit' => Pages\EditKbCategory::route('/{record}/edit'),
        ];
    }
}
