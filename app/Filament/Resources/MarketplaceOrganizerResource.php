<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceOrganizer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use BackedEnum;
use UnitEnum;

class MarketplaceOrganizerResource extends Resource
{
    protected static ?string $model = MarketplaceOrganizer::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Organizers';
    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 20;
    protected static ?string $modelLabel = 'Marketplace Organizer';
    protected static ?string $pluralModelLabel = 'Marketplace Organizers';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organizer Information')
                    ->schema([
                        Forms\Components\Select::make('marketplace_client_id')
                            ->label('Marketplace')
                            ->relationship('marketplaceClient', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Company Information')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('company_tax_id')
                            ->label('Tax ID / VAT')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('company_registration')
                            ->label('Registration Number')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('company_address')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Status & Settings')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('pending'),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Commission Override (%)')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Leave empty to use marketplace default'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'rejected' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('events')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceOrganizerResource\Pages\ListMarketplaceOrganizers::route('/'),
            'create' => MarketplaceOrganizerResource\Pages\CreateMarketplaceOrganizer::route('/create'),
            'view' => MarketplaceOrganizerResource\Pages\ViewMarketplaceOrganizer::route('/{record}'),
            'edit' => MarketplaceOrganizerResource\Pages\EditMarketplaceOrganizer::route('/{record}/edit'),
        ];
    }
}
