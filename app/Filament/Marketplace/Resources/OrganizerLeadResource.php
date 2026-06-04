<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages;
use App\Models\Marketplace\OrganizerLead;
use App\Models\Marketplace\OrganizerLeadEvent;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament admin resource for prospective-organizer leads coming through
 * /devino-partener + /inregistrare-locatie on bilete.online (and any
 * leisure marketplace wiring up the same flow).
 *
 * Marketplace-scoped: HasMarketplaceContext narrows the base query so
 * a marketplace admin only sees leads belonging to their marketplace.
 *
 * Uses Filament 4 conventions throughout:
 *   - record-level actions go in ->recordActions([])
 *   - bulk actions go in ->toolbarActions([BulkActionGroup::make([...])])
 *   - badge columns are TextColumn::make()->badge()->color(fn)
 */
class OrganizerLeadResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = OrganizerLead::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Lead-uri partener';
    protected static ?string $modelLabel = 'Lead';
    protected static ?string $pluralModelLabel = 'Lead-uri partener';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        $client = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->when($client, fn ($q) => $q->where('marketplace_client_id', $client->id));
    }

    public static function getNavigationBadge(): ?string
    {
        $client = static::getMarketplaceClient();
        if (!$client) return null;
        $count = OrganizerLead::query()
            ->where('marketplace_client_id', $client->id)
            ->where('status', OrganizerLead::STATUS_NEW)
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $form): Schema
    {
        return $form->components([
            SC\Section::make('Identitate contact')->columns(2)->schema([
                Forms\Components\TextInput::make('contact_name')->label('Nume')->required()->maxLength(160),
                Forms\Components\TextInput::make('email')->label('Email')->email()->required()->maxLength(190),
                Forms\Components\TextInput::make('phone')->label('Telefon')->maxLength(40),
            ]),
            SC\Section::make('Locație')->columns(2)->schema([
                Forms\Components\TextInput::make('location_name')->label('Numele locației')->required()->maxLength(200),
                Forms\Components\TextInput::make('city')->label('Oraș')->required()->maxLength(120),
                Forms\Components\TextInput::make('website')->label('Website')->maxLength(255),
                Forms\Components\TextInput::make('volume_estimate')->label('Volum estimat bilete/lună')->maxLength(40),
            ]),
            SC\Section::make('Activitate')->columns(2)->schema([
                Forms\Components\TextInput::make('category_slug')->label('Categorie (slug)')->maxLength(120),
                Forms\Components\TextInput::make('category_name')->label('Categorie (nume)')->maxLength(200),
                Forms\Components\TextInput::make('category_other')->label('Categorie free-text')->maxLength(200),
            ]),
            SC\Section::make('Pipeline')->columns(2)->schema([
                Forms\Components\Select::make('status')
                    ->options(OrganizerLead::STATUSES)
                    ->required()
                    ->default(OrganizerLead::STATUS_NEW),
                Forms\Components\Select::make('source')->options(OrganizerLead::SOURCES)->required(),
                Forms\Components\Select::make('assigned_to_user_id')
                    ->label('Asignat lui')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\DateTimePicker::make('next_action_at')->label('Următoarea acțiune'),
                Forms\Components\Textarea::make('notes')->label('Note interne')->rows(3)->columnSpanFull(),
            ]),
            SC\Section::make('Sursă vizită (read-only)')->columns(2)->collapsed()->schema([
                Forms\Components\TextInput::make('prefill_tip')->label('Prefill tip')->disabled(),
                Forms\Components\TextInput::make('prefill_loc')->label('Prefill loc')->disabled(),
                Forms\Components\Textarea::make('referrer')->label('Referrer')->disabled()->rows(2),
                Forms\Components\TextInput::make('utm_source')->disabled(),
                Forms\Components\TextInput::make('utm_medium')->disabled(),
                Forms\Components\TextInput::make('utm_campaign')->disabled(),
                Forms\Components\TextInput::make('utm_content')->disabled(),
                Forms\Components\TextInput::make('utm_term')->disabled(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('location_name')->label('Locație')->searchable()->wrap()->weight('bold')
                    ->description(fn (OrganizerLead $r) => $r->city),
                Tables\Columns\TextColumn::make('contact_name')->label('Contact')->searchable()
                    ->description(fn (OrganizerLead $r) => $r->email),
                Tables\Columns\TextColumn::make('activity_type_label')->label('Tip activitate')->wrap(),

                // Filament 4: TextColumn::make()->badge()->color(fn) — there's
                // no separate BadgeColumn class. Color callback uses match()
                // to return one Tailwind-equivalent color name per status.
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => OrganizerLead::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        OrganizerLead::STATUS_NEW            => 'warning',
                        OrganizerLead::STATUS_CONTACTED      => 'info',
                        OrganizerLead::STATUS_IN_NEGOTIATION => 'primary',
                        OrganizerLead::STATUS_DEMO_SCHEDULED => 'primary',
                        OrganizerLead::STATUS_ACCEPTED       => 'success',
                        OrganizerLead::STATUS_REJECTED       => 'danger',
                        OrganizerLead::STATUS_GHOSTED,
                        OrganizerLead::STATUS_ARCHIVED       => 'gray',
                        default                              => 'gray',
                    }),

                Tables\Columns\TextColumn::make('landing_views')->label('LP')->numeric()->alignCenter()
                    ->tooltip('Câte vizite a făcut pe /devino-partener'),
                Tables\Columns\TextColumn::make('onboarding_views')->label('OB')->numeric()->alignCenter()
                    ->tooltip('Câte vizite a făcut pe /inregistrare-locatie'),
                Tables\Columns\TextColumn::make('assignedTo.name')->label('Asignat')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('next_action_at')->label('Next action')->dateTime('d M Y H:i')->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->label('Trimis')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('utm_source')->label('UTM src')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('utm_campaign')->label('Campanie')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrganizerLead::STATUSES)->multiple(),
                SelectFilter::make('source')->options(OrganizerLead::SOURCES)->multiple(),
                SelectFilter::make('assigned_to_user_id')
                    ->label('Asignat lui')
                    ->relationship('assignedTo', 'name'),
                Filter::make('city')->schema([Forms\Components\TextInput::make('city')])
                    ->query(fn ($q, array $data) => isset($data['city']) && $data['city'] !== ''
                        ? $q->where('city', 'ILIKE', '%'.$data['city'].'%')
                        : $q),
                Filter::make('has_phone')
                    ->label('Are telefon')
                    ->query(fn ($q) => $q->whereNotNull('phone'))
                    ->toggle(),
                Filter::make('needs_action')
                    ->label('Necesită acțiune (next_action_at trecut)')
                    ->query(fn ($q) => $q->where('next_action_at', '<', now()))
                    ->toggle(),
            ])
            // Filament 4 — record-level actions
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('contacted')
                    ->label('Marchează contactat')
                    ->icon('heroicon-o-phone')
                    ->color('info')
                    ->visible(fn (OrganizerLead $r) => $r->status === OrganizerLead::STATUS_NEW)
                    ->action(function (OrganizerLead $r) {
                        $r->transitionTo(OrganizerLead::STATUS_CONTACTED, 'Marcat contactat din tabel', auth()->id());
                        Notification::make()->success()->title('Marcat contactat')->send();
                    }),
            ])
            // Filament 4 — bulk actions live under ->toolbarActions()
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->label('Asignează lui…')
                        ->icon('heroicon-o-user')
                        ->form([
                            Forms\Components\Select::make('user_id')
                                ->label('User')
                                ->options(User::query()->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $r) {
                                $r->update(['assigned_to_user_id' => $data['user_id']]);
                                OrganizerLeadEvent::create([
                                    'lead_id'               => $r->id,
                                    'marketplace_client_id' => $r->marketplace_client_id,
                                    'event_type'            => OrganizerLeadEvent::TYPE_ASSIGNED,
                                    'summary'               => 'Asignat din bulk action',
                                    'payload'               => ['user_id' => $data['user_id']],
                                    'performed_by_user_id'  => auth()->id(),
                                ]);
                            }
                            Notification::make()->success()->title('Asignat')->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizerLeads::route('/'),
            'create' => Pages\CreateOrganizerLead::route('/create'),
            'view'   => Pages\ViewOrganizerLead::route('/{record}'),
            'edit'   => Pages\EditOrganizerLead::route('/{record}/edit'),
        ];
    }
}
