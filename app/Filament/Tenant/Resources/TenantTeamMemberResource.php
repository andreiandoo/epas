<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TenantTeamMemberResource\Pages;
use App\Models\Leisure\TenantTeamMember;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class TenantTeamMemberResource extends Resource
{
    protected static ?string $model = TenantTeamMember::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Echipa';
    protected static ?string $modelLabel = 'Membru echipă';
    protected static ?string $pluralModelLabel = 'Membri echipă';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value
            : (string) $tenant?->tenant_type;
        return $type === 'leisure';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant?->id;
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenantId)
            ->with('user:id,name,email');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Date personale')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('user.name')
                        ->label('Nume complet')
                        ->required()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->user?->name)),
                    Forms\Components\TextInput::make('user.email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->user?->email))
                        ->helperText('Adresa de login. Operatorul folosește acest email + parola pentru a accesa /operator.'),
                    Forms\Components\TextInput::make('initial_password')
                        ->label(fn ($context) => $context === 'create' ? 'Parolă inițială' : 'Parolă nouă (opțional)')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->minLength(6)
                        ->helperText(fn ($context) => $context === 'create'
                            ? 'Operatorul folosește acest email + această parolă pentru /operator.'
                            : 'Lasă gol pentru a păstra parola existentă. Completează dacă vrei să o resetezi.'),
                ]),

            SC\Section::make('Drepturi')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('role')
                        ->label('Rol general')
                        ->options([
                            'admin' => 'Administrator',
                            'manager' => 'Manager',
                            'staff' => 'Operator (staff)',
                        ])
                        ->default('staff')
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('leisure_role')
                        ->label('Rol leisure specific')
                        ->options(TenantTeamMember::LEISURE_ROLES)
                        ->required()
                        ->helperText('Determină ce ecrane vede operatorul în /operator.'),

                    Forms\Components\CheckboxList::make('permissions')
                        ->label('Permisiuni fine')
                        ->options([
                            'orders.view' => 'Vede comenzi',
                            'orders.refund' => 'Refund comenzi',
                            'tickets.scan' => 'Scanare bilete (check-in)',
                            'rentals.start' => 'Start rental',
                            'rentals.end' => 'Finalizare rental',
                            'rentals.force_end' => 'Forțează închidere rental',
                            'pos.checkout' => 'POS — vânzare',
                            'pos.cash_drawer' => 'POS — sertar cash + Z report',
                            'inventory.manage' => 'Gestiune inventar fizic',
                            'capacities.edit' => 'Modificare capacități',
                            'customers.view' => 'Vede clienți (CRM)',
                            'reports.view' => 'Vede rapoarte',
                        ])
                        ->columns(2)
                        ->helperText('Bypassed de role=admin sau leisure_role=admin.')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending (invitație trimisă)',
                            'active' => 'Activ',
                            'inactive' => 'Inactiv',
                        ])
                        ->default('active')
                        ->required(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Note interne')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            SC\Section::make('Program / Pontaj')
                ->description('Schimburi planificate. Apar în pagina "Pontaj" pentru vedere săptămânală.')
                ->icon('heroicon-o-calendar')
                ->collapsed(fn ($record) => $record === null)
                ->schema([
                    Forms\Components\Repeater::make('shifts')
                        ->label('')
                        ->relationship('shifts')
                        ->schema([
                            Forms\Components\DatePicker::make('shift_date')->label('Data')->required(),
                            Forms\Components\TimePicker::make('start_time')->label('Start')->seconds(false)->required(),
                            Forms\Components\TimePicker::make('end_time')->label('Sfârșit')->seconds(false)->required(),
                            Forms\Components\Select::make('position')
                                ->label('Poziție')
                                ->options(TenantTeamMember::LEISURE_ROLES)
                                ->placeholder('Folosește rolul implicit'),
                            Forms\Components\TextInput::make('location')->label('Locație / Gate'),
                            Forms\Components\Textarea::make('notes')->label('Notă')->rows(1)->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => trim(
                            ($state['shift_date'] ?? '?') . ' ' .
                            ($state['start_time'] ?? '') . '-' . ($state['end_time'] ?? '')
                        ))
                        ->defaultItems(0)
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                            $data['tenant_id'] = auth()->user()?->tenant?->id;
                            return $data;
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nume')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('role')->label('Rol')->badge(),
                Tables\Columns\TextColumn::make('leisure_role')
                    ->label('Rol leisure')
                    ->badge()
                    ->formatStateUsing(fn ($state) => TenantTeamMember::LEISURE_ROLES[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success', 'pending' => 'warning', 'inactive' => 'gray', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('accepted_at')->dateTime('d.m.Y')->label('Acceptat')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('leisure_role')->options(TenantTeamMember::LEISURE_ROLES),
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Activ', 'pending' => 'Pending', 'inactive' => 'Inactiv',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleStatus')
                    ->label(fn ($record) => $record->status === 'active' ? 'Dezactivează' : 'Activează')
                    ->icon('heroicon-o-power')
                    ->color(fn ($record) => $record->status === 'active' ? 'warning' : 'success')
                    ->action(fn ($record) => $record->update([
                        'status' => $record->status === 'active' ? 'inactive' : 'active',
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantTeamMembers::route('/'),
            'create' => Pages\CreateTenantTeamMember::route('/create'),
            'edit' => Pages\EditTenantTeamMember::route('/{record}/edit'),
        ];
    }
}
