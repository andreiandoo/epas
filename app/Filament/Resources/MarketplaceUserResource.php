<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceAdmin;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components as SC;
use BackedEnum;
use UnitEnum;

class MarketplaceUserResource extends Resource
{
    protected static ?string $model = MarketplaceAdmin::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Marketplace Users';

    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Marketplace User';

    protected static ?string $pluralModelLabel = 'Marketplace Users';

    protected static ?string $slug = 'marketplace-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('User Information')
                ->schema([
                    Forms\Components\Select::make('marketplace_client_id')
                        ->label('Marketplace')
                        ->relationship('marketplaceClient', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\Select::make('role')
                        ->options(MarketplaceAdmin::roles())
                        ->required()
                        ->default('admin'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'suspended' => 'Suspended',
                        ])
                        ->required()
                        ->default('active'),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => $state ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                        ->maxLength(255),
                ])
                ->columns(2),

            SC\Section::make('Permissions')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->options(MarketplaceAdmin::availablePermissions())
                        ->columns(3)
                        ->helperText('Super Admins have all permissions automatically.'),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'moderator' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('role')
                    ->options(MarketplaceAdmin::roles()),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceUserResource\Pages\ListMarketplaceUsers::route('/'),
            'create' => MarketplaceUserResource\Pages\CreateMarketplaceUser::route('/create'),
            'edit' => MarketplaceUserResource\Pages\EditMarketplaceUser::route('/{record}/edit'),
        ];
    }
}
