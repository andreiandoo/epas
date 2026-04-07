<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\MarketplaceAdminResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceAdmin;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class MarketplaceAdminResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceAdmin::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationLabel = 'Utilizatori';
    protected static ?string $modelLabel = 'Platform User';
    protected static ?string $pluralModelLabel = 'Utilizatori';

    public static function shouldRegisterNavigation(): bool
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return $admin?->isSuperAdmin() || $admin?->hasPermission('admins.view');
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        $currentAdmin = Auth::guard('marketplace_admin')->user();

        $query = parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);

        // Non-super admins can only see non-super admins
        if (!$currentAdmin?->isSuperAdmin()) {
            $query->where('role', '!=', 'super_admin');
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $currentAdmin = Auth::guard('marketplace_admin')->user();

        return $schema
            ->components([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                Section::make('Informații utilizator')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nume complet')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->label('Parolă')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255)
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Lasă gol pentru a păstra parola curentă' : ''),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options(function () use ($currentAdmin) {
                                $roles = MarketplaceAdmin::roles();
                                if (!$currentAdmin?->isSuperAdmin()) {
                                    unset($roles['super_admin']);
                                }
                                return $roles;
                            })
                            ->required()
                            ->default('admin')
                            ->live(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Activ',
                                'inactive' => 'Inactiv',
                                'suspended' => 'Suspendat',
                            ])
                            ->required()
                            ->default('active'),

                        Forms\Components\Select::make('locale')
                            ->label('Limbă')
                            ->options([
                                'en' => 'English',
                                'ro' => 'Română',
                            ])
                            ->default('ro'),

                        Forms\Components\Select::make('timezone')
                            ->label('Fus orar')
                            ->options([
                                'Europe/Bucharest' => 'Europe/Bucharest',
                                'Europe/London' => 'Europe/London',
                                'UTC' => 'UTC',
                            ])
                            ->default('Europe/Bucharest'),

                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permisiuni')
                            ->options(MarketplaceAdmin::availablePermissions())
                            ->columns(2)
                            ->visible(fn ($get) => $get('role') !== 'super_admin')
                            ->helperText('Super Admins au toate permisiunile automat.'),
                    ])->columns(1),

                Section::make('Date Împuternicit')
                    ->description('Datele persoanei împuternicite pentru operațiuni fiscale și documente.')
                    ->schema([
                        Forms\Components\TextInput::make('proxy_full_name')
                            ->label('Nume și prenume')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('proxy_role')
                            ->label('Calitate')
                            ->placeholder('ex: Administrator, Director, Reprezentant legal')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('proxy_phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('proxy_address')
                            ->label('Adresa')
                            ->maxLength(500),

                        Forms\Components\Select::make('proxy_country')
                            ->label('Țara')
                            ->options([
                                'Romania' => 'România',
                                'Germany' => 'Germania',
                                'France' => 'Franța',
                                'Italy' => 'Italia',
                                'Spain' => 'Spania',
                                'Austria' => 'Austria',
                                'Hungary' => 'Ungaria',
                                'Bulgaria' => 'Bulgaria',
                                'Moldova' => 'Moldova',
                            ])
                            ->default('Romania')
                            ->searchable(),

                        Forms\Components\TextInput::make('proxy_county')
                            ->label('Județ')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('proxy_city')
                            ->label('Oraș')
                            ->maxLength(100)
                            ->live(onBlur: true),

                        Forms\Components\Select::make('proxy_sector')
                            ->label('Sector')
                            ->options([
                                '1' => 'Sector 1',
                                '2' => 'Sector 2',
                                '3' => 'Sector 3',
                                '4' => 'Sector 4',
                                '5' => 'Sector 5',
                                '6' => 'Sector 6',
                            ])
                            ->visible(fn ($get) => in_array(
                                mb_strtolower(trim($get('proxy_city') ?? '')),
                                ['bucuresti', 'bucurești', 'bucharest']
                            )),

                        Forms\Components\TextInput::make('proxy_id_series')
                            ->label('Serie CI')
                            ->placeholder('ex: XY')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('proxy_id_number')
                            ->label('Număr CI')
                            ->placeholder('ex: 123456')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('proxy_cnp')
                            ->label('CNP')
                            ->maxLength(13),

                        Forms\Components\FileUpload::make('proxy_id_card_file')
                            ->label('Buletin / Carte de identitate')
                            ->disk('public')
                            ->directory('proxy-documents')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable(),

                        Forms\Components\FileUpload::make('proxy_authorization_file')
                            ->label('Împuternicire')
                            ->disk('public')
                            ->directory('proxy-documents')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable(),

                        Forms\Components\FileUpload::make('proxy_signature_image')
                            ->label('Semnătură împuternicit')
                            ->helperText('Imagine PNG/JPG cu semnătura. Va fi folosită automat în documentele generate prin împuternicit.')
                            ->disk('public')
                            ->directory('proxy-signatures')
                            ->image()
                            ->imageEditor()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(2048)
                            ->downloadable()
                            ->openable(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currentAdmin = Auth::guard('marketplace_admin')->user();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'primary',
                        'moderator' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => MarketplaceAdmin::roles()[$state] ?? $state),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(function () use ($currentAdmin) {
                        $roles = MarketplaceAdmin::roles();
                        if (!$currentAdmin?->isSuperAdmin()) {
                            unset($roles['super_admin']);
                        }
                        return $roles;
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->id !== $currentAdmin?->id),
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
            'index' => Pages\ListMarketplaceAdmins::route('/'),
            'create' => Pages\CreateMarketplaceAdmin::route('/create'),
            'edit' => Pages\EditMarketplaceAdmin::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return $admin?->isSuperAdmin() || $admin?->hasPermission('admins.manage');
    }

    public static function canEdit($record): bool
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return $admin?->isSuperAdmin() || $admin?->hasPermission('admins.manage');
    }

    public static function canDelete($record): bool
    {
        $admin = Auth::guard('marketplace_admin')->user();
        // Can't delete yourself
        if ($record->id === $admin?->id) {
            return false;
        }
        return $admin?->isSuperAdmin() || $admin?->hasPermission('admins.manage');
    }
}
