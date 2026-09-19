<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\VaultEntryResource\Pages;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceVaultEntry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Settings → Seif: credentials visible only to the super admins selected on
 * each entry. A super admin who is not on an entry's access list does not see
 * it at all (not in the list, and its URLs return 404).
 */
class VaultEntryResource extends Resource
{
    protected static ?string $model = MarketplaceVaultEntry::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Seif';

    protected static ?string $modelLabel = 'intrare în seif';

    protected static ?string $pluralModelLabel = 'Seif';

    protected static ?string $slug = 'seif';

    protected static ?string $recordTitleAttribute = 'name';

    public const PASSWORD_MASK = '••••••••••';

    /** Form fields stored on the entry itself (access is saved separately). */
    public const ENTRY_FIELDS = ['name', 'url', 'username', 'password', 'requires_2fa', 'phone', 'email'];

    /**
     * The admin whose vault is shown, or null when the vault is closed.
     *
     * Only active super admins get one. A core super admin who switched into
     * the marketplace is logged in as one of its admins (often simply the
     * first super admin), so the vault stays closed unless that account has
     * the core user's own email.
     */
    public static function vaultAdmin(): ?MarketplaceAdmin
    {
        $admin = Auth::guard('marketplace_admin')->user();

        if (! $admin instanceof MarketplaceAdmin || ! $admin->isSuperAdmin() || ! $admin->isActive()) {
            return null;
        }

        if (session('marketplace_is_super_admin')) {
            $coreUser = Auth::guard('web')->user();

            if (! $coreUser || strcasecmp(trim((string) $coreUser->email), trim((string) $admin->email)) !== 0) {
                return null;
            }
        }

        return $admin;
    }

    /**
     * Super admins of the current marketplace, for the "Acces" selector.
     *
     * @return array<string, string>
     */
    public static function superAdminOptions(): array
    {
        $admin = static::vaultAdmin();

        if (! $admin) {
            return [];
        }

        return MarketplaceAdmin::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('role', 'super_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status'])
            ->mapWithKeys(fn (MarketplaceAdmin $a) => [
                (string) $a->id => $a->name . ' (' . $a->email . ')' . ($a->isActive() ? '' : ' — inactiv'),
            ])
            ->all();
    }

    /**
     * Keeps only ids of super admins from the current marketplace, whatever
     * the browser sent.
     *
     * @return array<int, int>
     */
    public static function sanitizeAccessIds(array $ids): array
    {
        $admin = static::vaultAdmin();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (! $admin || $ids === []) {
            return [];
        }

        return MarketplaceAdmin::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('role', 'super_admin')
            ->whereKey($ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('accessAdmins');
        $admin = static::vaultAdmin();

        return $admin
            ? $query->accessibleBy($admin)
            : $query->whereKey([]);
    }

    /**
     * Every page, action and can*() check of the resource ends up here: the
     * list and "create" need an open vault, a record also needs the admin on
     * its access list. Bulk and other actions are not offered.
     */
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $admin = static::vaultAdmin();

        if (! $admin) {
            return Response::deny();
        }

        if ($record === null) {
            return in_array($action, ['viewAny', 'create'], true) ? Response::allow() : Response::deny();
        }

        return in_array($action, ['view', 'update', 'delete'], true)
            && $record instanceof MarketplaceVaultEntry
            && $record->isAccessibleBy($admin)
                ? Response::allow()
                : Response::deny();
    }

    public static function canGloballySearch(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Date de acces')
                    ->icon('heroicon-o-key')
                    ->description('Datele sunt criptate în baza de date. Le văd doar conturile selectate la „Acces”.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nume')
                            ->required()
                            ->maxLength(191)
                            ->placeholder('ex: Cont Brevo, Hosting Ploi, Google Ads')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('url')
                            ->label('URL')
                            ->maxLength(2048)
                            ->placeholder('https://')
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->dehydrateStateUsing(function (?string $state): ?string {
                                $state = trim((string) $state);
                                if ($state === '') {
                                    return null;
                                }

                                // Only http(s) links, so a stored javascript: URL can never become a link.
                                return preg_match('#^https?://#i', $state) ? $state : 'https://' . $state;
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->maxLength(255)
                            ->autocomplete('off'),

                        Forms\Components\TextInput::make('password')
                            ->label('Parolă')
                            ->password()
                            ->revealable()
                            ->maxLength(1000)
                            ->autocomplete('new-password'),

                        Forms\Components\Checkbox::make('requires_2fa')
                            ->label('Necesită 2FA')
                            ->helperText('Autentificarea cere și un cod de verificare (SMS, email sau aplicație).')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Acces')
                    ->icon('heroicon-o-shield-check')
                    ->description('Doar super-administratorii selectați aici văd această intrare. Ceilalți nu o văd deloc, nici în listă.')
                    ->schema([
                        Forms\Components\Select::make('access_admin_ids')
                            ->label('Conturi cu acces')
                            ->multiple()
                            ->options(fn () => static::superAdminOptions())
                            ->default(fn () => ($id = static::vaultAdmin()?->id) ? [(string) $id] : [])
                            ->required()
                            ->searchable()
                            ->helperText('Dacă te scoți din listă, pierzi accesul la intrare după salvare.'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Date de acces')
                    ->icon('heroicon-o-key')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('url')
                            ->label('URL')
                            ->url(fn (MarketplaceVaultEntry $record) => $record->url, shouldOpenInNewTab: true)
                            ->color('primary')
                            ->copyable()
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('username')
                            ->label('Username')
                            ->copyable()
                            ->copyMessage('Username copiat')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('password')
                            ->label('Parolă')
                            ->formatStateUsing(fn () => self::PASSWORD_MASK)
                            ->copyable()
                            ->copyableState(fn (MarketplaceVaultEntry $record) => $record->password)
                            ->copyMessage('Parolă copiată')
                            ->placeholder('—')
                            ->hintAction(
                                Action::make('revealPassword')
                                    ->label('Arată')
                                    ->icon('heroicon-m-eye')
                                    ->color('gray')
                                    ->visible(fn (MarketplaceVaultEntry $record) => filled($record->password))
                                    ->modalHeading('Parolă')
                                    ->modalWidth('md')
                                    ->modalContent(function (MarketplaceVaultEntry $record) {
                                        // Checked again here: access may have been revoked while the page was open.
                                        abort_unless(static::canView($record), 403);

                                        return new HtmlString(
                                            '<div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1.05rem; word-break: break-all; user-select: all; padding: .75rem 1rem; border-radius: .5rem; background: rgba(127, 127, 127, .1);">'
                                            . e($record->password)
                                            . '</div>'
                                        );
                                    })
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Închide')
                            ),

                        Infolists\Components\IconEntry::make('requires_2fa')
                            ->label('Necesită 2FA')
                            ->boolean()
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('phone')
                            ->label('Telefon')
                            ->copyable()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('—'),
                    ]),

                Section::make('Acces')
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('accessAdmins.name')
                            ->label('Conturi cu acces')
                            ->badge()
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Adăugat de')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Ultima modificare')
                            ->dateTime('d.m.Y H:i'),

                        Infolists\Components\TextEntry::make('updater.name')
                            ->label('Modificat de')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->formatStateUsing(fn (?string $state) => $state ? (parse_url($state, PHP_URL_HOST) ?: $state) : null)
                    ->url(fn (MarketplaceVaultEntry $record) => $record->url, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->copyable()
                    ->copyMessage('Username copiat')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('password')
                    ->label('Parolă')
                    ->formatStateUsing(fn () => self::PASSWORD_MASK)
                    ->copyable()
                    ->copyableState(fn (MarketplaceVaultEntry $record) => $record->password)
                    ->copyMessage('Parolă copiată')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('requires_2fa')
                    ->label('2FA')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('accessAdmins.name')
                    ->label('Acces')
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->expandableLimitedList(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modificat')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateIcon('heroicon-o-lock-closed')
            ->emptyStateHeading('Nicio intrare în seif')
            ->emptyStateDescription('Aici apar doar intrările la care ai primit acces.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVaultEntries::route('/'),
            'create' => Pages\CreateVaultEntry::route('/create'),
            'view' => Pages\ViewVaultEntry::route('/{record}'),
            'edit' => Pages\EditVaultEntry::route('/{record}/edit'),
        ];
    }
}
