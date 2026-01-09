<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;
use App\Models\MarketplaceCustomer;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class MarketplaceCustomerResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCustomer::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Customers';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Registered Users';
    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Registered Users';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Account Information')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('active'),

                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Email Verified')
                            ->formatStateUsing(fn ($state) => $state !== null)
                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                            ->helperText('Mark if email is manually verified'),

                        Forms\Components\Toggle::make('accepts_marketing')
                            ->label('Accepts Marketing')
                            ->helperText('Customer has consented to marketing communications'),
                    ])->columns(2),

                SC\Section::make('Personal Details')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Birth Date'),

                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ]),

                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'ro' => 'Romanian',
                                'de' => 'German',
                                'fr' => 'French',
                                'es' => 'Spanish',
                            ]),
                    ])->columns(2),

                SC\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Street Address')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('State/Province')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('country')
                            ->label('Country Code')
                            ->maxLength(2)
                            ->helperText('ISO 2-letter code (e.g., RO, US, DE)'),
                    ])->columns(2)
                    ->collapsed(),

                SC\Section::make('Statistics')
                    ->schema([
                        Forms\Components\Placeholder::make('total_orders_display')
                            ->label('Total Orders')
                            ->content(fn ($record) => $record?->total_orders ?? 0),

                        Forms\Components\Placeholder::make('total_spent_display')
                            ->label('Total Spent')
                            ->content(fn ($record) => number_format($record?->total_spent ?? 0, 2) . ' RON'),

                        Forms\Components\Placeholder::make('last_login_display')
                            ->label('Last Login')
                            ->content(fn ($record) => $record?->last_login_at?->diffForHumans() ?? 'Never'),

                        Forms\Components\Placeholder::make('created_at_display')
                            ->label('Registered')
                            ->content(fn ($record) => $record?->created_at?->format('d M Y H:i') ?? '-'),
                    ])->columns(4)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null),

                Tables\Columns\IconColumn::make('is_guest')
                    ->label('Type')
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-user-circle')
                    ->trueColor('gray')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => $record->isGuest())
                    ->tooltip(fn ($record) => $record->isGuest() ? 'Guest (no password)' : 'Registered'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Spent')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label('Email Verified')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    ),

                Tables\Filters\TernaryFilter::make('is_guest')
                    ->label('Account Type')
                    ->trueLabel('Guest Only')
                    ->falseLabel('Registered Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('password'),
                        false: fn (Builder $query) => $query->whereNotNull('password'),
                    ),

                Tables\Filters\TernaryFilter::make('has_orders')
                    ->label('Has Orders')
                    ->queries(
                        true: fn (Builder $query) => $query->where('total_orders', '>', 0),
                        false: fn (Builder $query) => $query->where('total_orders', 0),
                    ),

                Tables\Filters\TernaryFilter::make('accepts_marketing')
                    ->label('Marketing Consent'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        return static::getModel()::where('marketplace_client_id', $marketplace->id)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplaceCustomers::route('/'),
            'create' => Pages\CreateMarketplaceCustomer::route('/create'),
            'view' => Pages\ViewMarketplaceCustomer::route('/{record}'),
            'edit' => Pages\EditMarketplaceCustomer::route('/{record}/edit'),
        ];
    }
}
