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
    use HasMarketplaceContext;

    protected static ?string $model = KbCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'KB Categories';

    protected static \UnitEnum|string|null $navigationGroup = 'Knowledge Base';

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

                SC\Section::make('Category Details')
                    ->schema([
                        Forms\Components\TextInput::make("name.{$marketplaceLanguage}")
                            ->label('Category Name')
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) $set('slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Textarea::make("description.{$marketplaceLanguage}")
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),
                    ])->columns(2),

                SC\Section::make('Appearance')
                    ->schema([
                        Forms\Components\Select::make('icon')
                            ->label('Icon')
                            ->options([
                                'heroicon-o-ticket' => 'Ticket',
                                'heroicon-o-calendar' => 'Calendar',
                                'heroicon-o-user' => 'User',
                                'heroicon-o-cog-6-tooth' => 'Settings',
                                'heroicon-o-credit-card' => 'Credit Card',
                                'heroicon-o-document-text' => 'Document',
                                'heroicon-o-question-mark-circle' => 'Question',
                                'heroicon-o-information-circle' => 'Information',
                                'heroicon-o-bell' => 'Bell',
                                'heroicon-o-envelope' => 'Envelope',
                                'heroicon-o-shield-check' => 'Shield',
                                'heroicon-o-lock-closed' => 'Lock',
                                'heroicon-o-map-pin' => 'Location',
                                'heroicon-o-phone' => 'Phone',
                                'heroicon-o-building-office' => 'Building',
                                'heroicon-o-banknotes' => 'Money',
                                'heroicon-o-gift' => 'Gift',
                                'heroicon-o-star' => 'Star',
                                'heroicon-o-heart' => 'Heart',
                                'heroicon-o-chart-bar' => 'Chart',
                                'heroicon-o-folder' => 'Folder',
                                'heroicon-o-clipboard-document-list' => 'Clipboard',
                            ])
                            ->searchable()
                            ->placeholder('Select an icon'),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),

                        Forms\Components\TextInput::make('image_url')
                            ->label('Image URL')
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
                    ->label('Color'),

                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                Tables\Columns\TextColumn::make('article_count')
                    ->label('Articles')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
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
