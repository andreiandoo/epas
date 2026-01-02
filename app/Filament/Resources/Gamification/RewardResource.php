<?php

namespace App\Filament\Resources\Gamification;

use App\Filament\Resources\Gamification\RewardResource\Pages;
use App\Models\Gamification\Reward;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class RewardResource extends Resource
{
    protected static ?string $model = Reward::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Rewards';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'gamification/rewards';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Ownership')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('marketplace_client_id')
                            ->relationship('marketplaceClient', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])->columns(2),

                SC\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label('Name (English)')
                            ->required(),

                        Forms\Components\TextInput::make('name.ro')
                            ->label('Name (Romanian)')
                            ->required(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('type')
                            ->options([
                                'fixed_discount' => 'Fixed Discount',
                                'percentage_discount' => 'Percentage Discount',
                                'free_item' => 'Free Item',
                                'voucher_code' => 'Voucher Code',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('points_cost')
                            ->label('Points Cost')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('value')
                            ->label('Value')
                            ->numeric()
                            ->required(),
                    ])->columns(2),

                SC\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('(Marketplace)')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->placeholder('(Tenant)')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('points_cost')
                    ->label('Points Cost')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_value')
                    ->label('Value'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('redemptions_count')
                    ->label('Redemptions')
                    ->counts('redemptions'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewards::route('/'),
            'create' => Pages\CreateReward::route('/create'),
            'edit' => Pages\EditReward::route('/{record}/edit'),
        ];
    }
}
