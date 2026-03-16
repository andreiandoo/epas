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
use App\Filament\Resources\MarketplaceOrganizerResource\RelationManagers\BankAccountsRelationManager;
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

                Section::make('Organizer Type')
                    ->description('Classification and work mode settings')
                    ->schema([
                        Forms\Components\Select::make('person_type')
                            ->label('Person Type')
                            ->options([
                                'pj' => 'Persoana Juridica (Legal Entity)',
                                'pf' => 'Persoana Fizica (Individual)',
                            ])
                            ->native(false),

                        Forms\Components\Select::make('work_mode')
                            ->label('Work Mode')
                            ->options([
                                'exclusive' => 'Exclusive (sells only through this platform)',
                                'non_exclusive' => 'Non-Exclusive (sells through multiple channels)',
                            ])
                            ->native(false),

                        Forms\Components\Select::make('organizer_type')
                            ->label('Organizer Type')
                            ->options([
                                'agency' => 'Event Agency',
                                'promoter' => 'Independent Promoter',
                                'venue' => 'Venue / Hall',
                                'artist' => 'Artist / Manager',
                                'ngo' => 'NGO / Foundation',
                                'other' => 'Other',
                            ])
                            ->native(false),
                    ])
                    ->columns(3),

                Section::make('Company Information')
                    ->description('Legal entity details (for Persoana Juridica)')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('company_tax_id')
                            ->label('CUI / Tax ID')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('company_registration')
                            ->label('Reg. Com. Number')
                            ->maxLength(100),

                        Forms\Components\Toggle::make('vat_payer')
                            ->label('VAT Payer')
                            ->helperText('Is the company a VAT payer?'),

                        Forms\Components\Textarea::make('company_address')
                            ->label('Company Address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('company_city')
                            ->label('City')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('company_county')
                            ->label('County')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('company_zip')
                            ->label('Postal Code')
                            ->maxLength(20),

                        Forms\Components\Textarea::make('past_contract')
                            ->label('Past Contract')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Notes or reference to past contract details'),

                        Forms\Components\TextInput::make('representative_first_name')
                            ->label('Representative First Name')
                            ->maxLength(100)
                            ->helperText('Legal representative'),

                        Forms\Components\TextInput::make('representative_last_name')
                            ->label('Representative Last Name')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Section::make('Guarantor / Personal Details')
                    ->description('Personal identification for contract purposes')
                    ->schema([
                        Forms\Components\TextInput::make('guarantor_first_name')
                            ->label('First Name')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('guarantor_last_name')
                            ->label('Last Name')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('guarantor_cnp')
                            ->label('CNP (Personal ID Number)')
                            ->maxLength(13)
                            ->helperText('13 digit Romanian CNP'),

                        Forms\Components\TextInput::make('guarantor_address')
                            ->label('Home Address')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('guarantor_city')
                            ->label('City')
                            ->maxLength(100),

                        Forms\Components\Select::make('guarantor_id_type')
                            ->label('ID Document Type')
                            ->options([
                                'ci' => 'Carte de Identitate (CI)',
                                'bi' => 'Buletin de Identitate (BI)',
                            ])
                            ->native(false),

                        Forms\Components\TextInput::make('guarantor_id_series')
                            ->label('ID Series')
                            ->maxLength(2)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        Forms\Components\TextInput::make('guarantor_id_number')
                            ->label('ID Number')
                            ->maxLength(6),

                        Forms\Components\TextInput::make('guarantor_id_issued_by')
                            ->label('Issued By')
                            ->maxLength(100)
                            ->helperText('e.g., SPCLEP Sector 1'),

                        Forms\Components\DatePicker::make('guarantor_id_issued_date')
                            ->label('Issue Date')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Uploaded Documents')
                    ->description('Identity and company documents for verification')
                    ->schema([
                        Forms\Components\FileUpload::make('id_card_document')
                            ->label('CI / ID Card Copy')
                            ->disk('public')
                            ->directory('organizer-documents')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->helperText('Personal ID card for the guarantor/representative')
                            ->downloadable()
                            ->openable(),

                        Forms\Components\FileUpload::make('cui_document')
                            ->label('CUI / Company Registration Copy')
                            ->disk('public')
                            ->directory('organizer-documents')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->helperText('Company registration certificate (CUI)')
                            ->downloadable()
                            ->openable(),
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

                        Forms\Components\TextInput::make('fixed_commission_default')
                            ->label('Fixed Commission Default')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('RON')
                            ->helperText('Fixed commission amount (absolute value, not %). Leave empty if not applicable.'),
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

                Tables\Columns\TextColumn::make('person_type')
                    ->label('Person')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pj' => 'PJ',
                        'pf' => 'PF',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'pj' => 'info',
                        'pf' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('work_mode')
                    ->label('Work Mode')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'exclusive' => 'Exclusive',
                        'non_exclusive' => 'Non-Excl.',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'exclusive' => 'success',
                        'non_exclusive' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

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

                Tables\Filters\SelectFilter::make('person_type')
                    ->label('Person Type')
                    ->options([
                        'pj' => 'Persoana Juridica (PJ)',
                        'pf' => 'Persoana Fizica (PF)',
                    ]),

                Tables\Filters\SelectFilter::make('work_mode')
                    ->label('Work Mode')
                    ->options([
                        'exclusive' => 'Exclusive',
                        'non_exclusive' => 'Non-Exclusive',
                    ]),

                Tables\Filters\SelectFilter::make('organizer_type')
                    ->label('Organizer Type')
                    ->options([
                        'agency' => 'Event Agency',
                        'promoter' => 'Independent Promoter',
                        'venue' => 'Venue / Hall',
                        'artist' => 'Artist / Manager',
                        'ngo' => 'NGO / Foundation',
                        'other' => 'Other',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            BankAccountsRelationManager::class,
        ];
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
