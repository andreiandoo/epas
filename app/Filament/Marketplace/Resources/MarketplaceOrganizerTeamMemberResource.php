<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\MarketplaceOrganizerTeamMemberResource\Pages;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Resource ascuns (nu apare in navigation) — accesat via link direct din tab-ul
 * Echipa al OrganizerResource. Ofera Edit pentru membrii echipei unui organizator.
 * Create-ul nu e expus aici (team members se creeaza prin invite flow din
 * /organizator/echipa in contul organizatorului).
 */
class MarketplaceOrganizerTeamMemberResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceOrganizerTeamMember::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user';
    protected static ?string $modelLabel = 'Membru echipă';
    protected static ?string $pluralModelLabel = 'Membri echipă';
    protected static UnitEnum|string|null $navigationGroup = 'Administrare';

    public static function shouldRegisterNavigation(): bool
    {
        // Ascuns din meniu — accesat doar via link direct din tab-ul Echipa.
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $mpId = self::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $mpId));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make(__('Detalii membru'))
                ->description(__('Modificări pe contul de team member. Parola nu se schimbă aici.'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Nume complet'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->rule(function (?MarketplaceOrganizerTeamMember $record) {
                            // Email unic per (organizator, email) — mirror constraint DB
                            return \Illuminate\Validation\Rule::unique('marketplace_organizer_team_members', 'email')
                                ->where(fn ($q) => $q->where('marketplace_organizer_id', $record?->marketplace_organizer_id))
                                ->ignore($record?->id);
                        }),
                    Forms\Components\Select::make('role')
                        ->label(__('Rol'))
                        ->options([
                            'admin' => __('Administrator'),
                            'manager' => __('Manager'),
                            'staff' => __('Staff'),
                        ])
                        ->required(),
                    Forms\Components\Select::make('leisure_role')
                        ->label(__('Rol leisure (opțional)'))
                        ->options([
                            'check_in' => __('✅ Check-in'),
                            'rental_operator' => __('🛶 Operator închirieri'),
                            'pos_cashier' => __('💵 Casier POS'),
                            'inventory_manager' => __('📦 Manager inventar'),
                            'pos_manager' => __('🧾 Manager POS'),
                            'admin' => __('⭐ Admin leisure'),
                        ])
                        ->searchable()
                        ->nullable(),
                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options([
                            'active' => __('Activ'),
                            'pending' => __('Invitat (pending)'),
                            'inactive' => __('Inactiv'),
                        ])
                        ->required(),
                    Forms\Components\Placeholder::make('organizer_info')
                        ->label(__('Organizator'))
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->organizer?->name
                            . ' (id ' . ($record?->marketplace_organizer_id ?? '—') . ')'),
                ]),
            SC\Section::make(__('Metadata'))
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('accepted_at')
                        ->label(__('Invitația acceptată'))
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->accepted_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '— niciodată'),
                    Forms\Components\Placeholder::make('invite_sent_at')
                        ->label(__('Invitația trimisă'))
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->invite_sent_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '—'),
                    Forms\Components\Placeholder::make('created_at')
                        ->label(__('Adăugat'))
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->created_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '—'),
                ])
                ->collapsed(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => Pages\EditMarketplaceOrganizerTeamMember::route('/{record}/edit'),
        ];
    }

    /**
     * Resource-ul nu are pagina de index (accesat doar via link direct din tab-ul
     * Echipa al organizerului). Filament cere getIndexUrl() ca fallback pentru
     * breadcrumbs + butonul "back to list" din header. Trimitem catre lista
     * organizatorilor ca acolo se merge inapoi logic.
     */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return url('/marketplace/organizers');
    }
}
