<?php

namespace App\Filament\Marketplace\Resources;

use App\Enums\TenantType;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Marketplace super-admin-only page for creating venue-owner tenant
 * accounts (e.g. Ambilet AmBilet cash-app scan operators). Distinct
 * from the core Tixello /admin/tenants resource in three ways:
 *
 *   1. tenant_type is FIXED to 'venue' — the marketplace flow never
 *      creates artist / theater / festival tenants.
 *   2. Venue picker is FILTERED to venues.marketplace_client_id =
 *      current, so a marketplace can only link its own venues.
 *   3. Rows are SCOPED to created_by_marketplace_client_id = current,
 *      so a marketplace never sees or edits tenants it didn't create
 *      (including all tenants created via /admin/tenants — those stay
 *      invisible here).
 *
 * The core Tixello admin resource remains authoritative for
 * subscription / billing / plan / contract fields. Anything created
 * here starts with sensible defaults and can be extended by a core
 * super-admin later.
 */
class TenantResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Tenant::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static \UnitEnum|string|null $navigationGroup = 'Locații';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Proprietari locații';

    protected static ?string $modelLabel = 'Proprietar locație';

    protected static ?string $pluralModelLabel = 'Proprietari locații';

    /**
     * Marketplace super-admins only. Regular marketplace admins never
     * see the navigation entry.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::currentAdminIsSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::currentAdminIsSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::currentAdminIsSuperAdmin();
    }

    public static function canEdit($record): bool
    {
        return static::currentAdminIsSuperAdmin() && static::ownsRecord($record);
    }

    public static function canDelete($record): bool
    {
        return static::currentAdminIsSuperAdmin() && static::ownsRecord($record);
    }

    public static function canView($record): bool
    {
        return static::currentAdminIsSuperAdmin() && static::ownsRecord($record);
    }

    protected static function currentAdminIsSuperAdmin(): bool
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return $admin && method_exists($admin, 'isSuperAdmin') && $admin->isSuperAdmin();
    }

    protected static function ownsRecord($record): bool
    {
        $mcId = static::getMarketplaceClientId();
        return $record instanceof Tenant
            && $mcId
            && (int) $record->created_by_marketplace_client_id === (int) $mcId;
    }

    /**
     * Scope list/edit lookups to tenants this marketplace created —
     * defense in depth on top of the canView/canEdit guards.
     */
    public static function getEloquentQuery(): Builder
    {
        $mcId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->when($mcId, fn ($q) => $q->where('created_by_marketplace_client_id', $mcId))
            ->when(!$mcId, fn ($q) => $q->whereRaw('1=0'));
    }

    public static function form(Schema $schema): Schema
    {
        $mcId = static::getMarketplaceClientId();
        $isEdit = fn (string $operation): bool => $operation === 'edit';

        return $schema->schema([
            SC\Section::make('Detalii venue owner')
                ->description('Un tenant de tip venue capabil să se autentifice în aplicația AmBilet Android și să scaneze / vândă bilete pentru locațiile linked.')
                ->schema([
                    Forms\Components\TextInput::make('public_name')
                        ->label('Nume public')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Cum apare venue owner-ul în interfețe.'),

                    Forms\Components\Select::make('locale')
                        ->label('Limbă / Locale')
                        ->options([
                            'ro' => 'Română',
                            'en' => 'English',
                            'hu' => 'Magyar',
                            'de' => 'Deutsch',
                            'fr' => 'Français',
                        ])
                        ->default('ro')
                        ->required(),
                ])->columns(2),

            SC\Section::make('Locații linked')
                ->description('Multi-select — locațiile la care venue owner-ul va putea scana / vinde bilete în aplicație. Se afișează toate locațiile din DB (indiferent de marketplace).')
                ->schema([
                    Forms\Components\Select::make('linked_venue_ids')
                        ->label('Venues')
                        ->multiple()
                        ->options(function () {
                            // Whole venue catalog — per operator request
                            // (2026-08-22): a marketplace super-admin can
                            // link any venue in the DB, not just the ones
                            // scoped to their marketplace_client_id.
                            return Venue::query()
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => ($v->getTranslation('name', 'ro')
                                        ?: $v->getTranslation('name', 'en')
                                        ?: $v->name)
                                        . ($v->city ? ' (' . $v->city . ')' : ''),
                                ])
                                ->sort()
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->afterStateHydrated(function ($component, $record) {
                            if ($record) {
                                $ids = Venue::where('tenant_id', $record->id)
                                    ->pluck('id')
                                    ->all();
                                $component->state($ids);
                            }
                        })
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            SC\Section::make('Cont autentificare owner')
                ->description('User-ul pe care venue owner-ul îl folosește la login. Se creează la salvare cu rolul potrivit pentru aplicația AmBilet.')
                ->schema([
                    Forms\Components\TextInput::make('owner_first_name')
                        ->label('Prenume')
                        ->required($isEdit ? false : true)
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->afterStateHydrated(function ($component, $record) {
                            // users table on prod only has `name`, not
                            // first_name / last_name — split on the first
                            // space so the two-field UI still renders
                            // sensibly for existing rows.
                            $name = (string) ($record?->owner?->name ?? '');
                            [$first] = array_pad(explode(' ', $name, 2), 2, '');
                            $component->state($first);
                        }),

                    Forms\Components\TextInput::make('owner_last_name')
                        ->label('Nume')
                        ->required($isEdit ? false : true)
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->afterStateHydrated(function ($component, $record) {
                            $name = (string) ($record?->owner?->name ?? '');
                            [, $last] = array_pad(explode(' ', $name, 2), 2, '');
                            $component->state($last);
                        }),

                    Forms\Components\TextInput::make('owner_email')
                        ->label('Email login')
                        ->email()
                        ->required($isEdit ? false : true)
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->helperText('Adresa cu care se autentifică în aplicația AmBilet. Trebuie să fie o adresă nefolosită de niciun alt cont — verificarea se face la salvare.')
                        ->afterStateHydrated(function ($component, $record) {
                            $component->state($record?->owner?->email);
                        })
                        ->disabled(fn (string $operation) => $operation === 'edit'),

                    Forms\Components\TextInput::make('owner_password')
                        ->label('Parolă')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->minLength(8)
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Completează doar dacă vrei să resetezi parola. Altfel lasă gol.'
                            : 'Minim 8 caractere.'),
                ])->columns(2),

            // Hidden bookkeeping — tenant_type + origin marker + status.
            Forms\Components\Hidden::make('tenant_type')
                ->default(TenantType::Venue->value),
            Forms\Components\Hidden::make('created_by_marketplace_client_id')
                ->default($mcId),
            Forms\Components\Hidden::make('status')
                ->default('active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_name')
                    ->label('Nume public')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner.email')
                    ->label('Email owner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('venues_count')
                    ->label('Locații')
                    ->counts('venues')
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Locale')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
