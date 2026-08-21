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
        $mp = self::currentMarketplace();
        return parent::getEloquentQuery()
            ->whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $mp?->id));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Detalii membru')
                ->description('Modificări pe contul de team member. Parola nu se schimbă aici.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nume complet')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
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
                        ->label('Rol')
                        ->options([
                            'admin' => 'Administrator',
                            'manager' => 'Manager',
                            'staff' => 'Staff',
                        ])
                        ->required(),
                    Forms\Components\Select::make('leisure_role')
                        ->label('Rol leisure (opțional)')
                        ->options([
                            'check_in' => '✅ Check-in',
                            'rental_operator' => '🛶 Operator închirieri',
                            'pos_cashier' => '💵 Casier POS',
                            'inventory_manager' => '📦 Manager inventar',
                            'pos_manager' => '🧾 Manager POS',
                            'admin' => '⭐ Admin leisure',
                        ])
                        ->searchable()
                        ->nullable(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Activ',
                            'pending' => 'Invitat (pending)',
                            'inactive' => 'Inactiv',
                        ])
                        ->required(),
                    Forms\Components\Placeholder::make('organizer_info')
                        ->label('Organizator')
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->organizer?->name
                            . ' (id ' . ($record?->marketplace_organizer_id ?? '—') . ')'),
                ]),
            SC\Section::make('Metadata')
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('accepted_at')
                        ->label('Invitația acceptată')
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->accepted_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '— niciodată'),
                    Forms\Components\Placeholder::make('invite_sent_at')
                        ->label('Invitația trimisă')
                        ->content(fn (?MarketplaceOrganizerTeamMember $record) => $record?->invite_sent_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '—'),
                    Forms\Components\Placeholder::make('created_at')
                        ->label('Adăugat')
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
}
