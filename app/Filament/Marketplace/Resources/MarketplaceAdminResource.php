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
    protected static ?string $navigationLabel = 'Platform Users';
    protected static ?string $modelLabel = 'Platform User';
    protected static ?string $pluralModelLabel = 'Platform Users';

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

                Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255)
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Leave blank to keep current password' : ''),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                    ])->columns(2),

                Section::make('Role & Permissions')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->options(function () use ($currentAdmin) {
                                $roles = MarketplaceAdmin::roles();
                                // Only super admins can create other super admins
                                if (!$currentAdmin?->isSuperAdmin()) {
                                    unset($roles['super_admin']);
                                }
                                return $roles;
                            })
                            ->required()
                            ->default('admin')
                            ->live(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('active'),

                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permissions')
                            ->options(MarketplaceAdmin::availablePermissions())
                            ->columns(2)
                            ->visible(fn ($get) => $get('role') !== 'super_admin')
                            ->helperText('Super Admins have all permissions automatically'),
                    ])->columns(1),

                Section::make('Preferences')
                    ->schema([
                        Forms\Components\Select::make('locale')
                            ->options([
                                'en' => 'English',
                                'ro' => 'Romanian',
                            ])
                            ->default('en'),

                        Forms\Components\Select::make('timezone')
                            ->options([
                                'Europe/Bucharest' => 'Europe/Bucharest',
                                'Europe/London' => 'Europe/London',
                                'UTC' => 'UTC',
                            ])
                            ->default('Europe/Bucharest'),
                    ])->columns(2)
                    ->collapsed(),
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
