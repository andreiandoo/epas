<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\GiftCardDesignResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceGiftCard;
use App\Models\MarketplaceGiftCardDesign;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class GiftCardDesignResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceGiftCardDesign::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 15;
    protected static ?string $navigationLabel = 'Gift Card Designs';
    protected static ?string $modelLabel = 'Gift Card Design';
    protected static ?string $pluralModelLabel = 'Gift Card Designs';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->ordered();
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->components([
                SC\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Hidden::make('marketplace_client_id')
                            ->default($marketplace?->id),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash(),

                        Forms\Components\Textarea::make('description')
                            ->rows(2),

                        Forms\Components\Select::make('occasion')
                            ->options(MarketplaceGiftCard::OCCASIONS),
                    ])->columns(2),

                SC\Section::make('Design Assets')
                    ->schema([
                        Forms\Components\FileUpload::make('preview_image')
                            ->label('Preview Image')
                            ->image()
                            ->directory('gift-card-designs')
                            ->visibility('public'),

                        Forms\Components\TextInput::make('email_template_path')
                            ->label('Email Template Path')
                            ->helperText('Path to custom email blade template'),

                        Forms\Components\TextInput::make('pdf_template_path')
                            ->label('PDF Template Path')
                            ->helperText('Path to custom PDF blade template'),
                    ])->columns(2),

                SC\Section::make('Colors')
                    ->schema([
                        Forms\Components\ColorPicker::make('colors.primary')
                            ->label('Primary Color')
                            ->default('#4f46e5'),

                        Forms\Components\ColorPicker::make('colors.secondary')
                            ->label('Secondary Color')
                            ->default('#818cf8'),

                        Forms\Components\ColorPicker::make('colors.accent')
                            ->label('Accent Color')
                            ->default('#c7d2fe'),

                        Forms\Components\ColorPicker::make('colors.text')
                            ->label('Text Color')
                            ->default('#1f2937'),
                    ])->columns(4),

                SC\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Design'),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_image')
                    ->label('Preview')
                    ->circular(false)
                    ->height(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('occasion')
                    ->formatStateUsing(fn ($state) => MarketplaceGiftCard::OCCASIONS[$state] ?? $state)
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\TextColumn::make('giftCards_count')
                    ->label('Used')
                    ->counts('giftCards')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('occasion')
                    ->options(MarketplaceGiftCard::OCCASIONS),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->is_default)
                    ->action(function ($record) {
                        $record->setAsDefault();

                        Notification::make()
                            ->title('Design set as default')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListGiftCardDesigns::route('/'),
            'create' => Pages\CreateGiftCardDesign::route('/create'),
            'edit' => Pages\EditGiftCardDesign::route('/{record}/edit'),
        ];
    }
}
