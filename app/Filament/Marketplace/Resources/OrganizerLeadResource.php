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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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

    /**
     * Activity-type → tip code mapping used to generate personalized
     * outbound URLs for /devino-partener. The keys are the EXACT codes
     * accepted by the PERSO_ALIASES table in devino-partener.php, so
     * a saved lead with category_slug='muzeu' yields
     * https://bilete.online/devino-partener?tip=muzeu&loc=…
     * and that page renders the muzeu-flavored copy on arrival.
     */
    public const CATEGORY_OPTIONS = [
        'escape'          => 'Escape rooms',
        'muzeu'           => 'Muzee & expoziții',
        'parc-distractii' => 'Parcuri de distracții',
        'parc-aventura'   => 'Parcuri de aventură',
        'natura'          => 'Natură & outdoor',
        'acvarii-zoo'     => 'Acvarii, zoo & animale',
        'ateliere'        => 'Ateliere creative',
        'tururi'          => 'Tururi turistice',
        'educatie'        => 'Educație experiențială',
        'familie'         => 'Familie & copii',
        'corporate'       => 'Corporate & grupuri',
        'cultura'         => 'Cultură & artă',
    ];

    /**
     * Build the public link a sales person should send to a lead. Used
     * both by the form preview (live) and by the View page (after save).
     * Returns empty string when no useful params are set, so callers can
     * decide whether to render a placeholder.
     */
    public static function buildCampaignLink(?string $tip, ?string $loc): string
    {
        $params = [];
        if ($tip) $params['tip'] = $tip;
        if ($loc) $params['loc'] = $loc;
        if (empty($params)) return '';
        return 'https://bilete.online/devino-partener?' . http_build_query($params);
    }

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
            // One wide section — collapsing contact + location + activity +
            // pipeline into a single grid so the page reads top-to-bottom
            // instead of jumping between fragmented section cards.
            SC\Section::make('Lead')
                ->description('Datele despre lead + linkul pe care îl trimiți. Categoria + Numele locației construiesc automat URL-ul personalizat.')
                ->columns(12)
                ->schema([
                    // ─── Contact ─────────────────────────────────────────
                    Forms\Components\TextInput::make('contact_name')
                        ->label('Nume contact')->required()->maxLength(160)
                        ->columnSpan(['md' => 6, 'sm' => 12]),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')->email()->required()->maxLength(190)
                        ->columnSpan(['md' => 4, 'sm' => 12]),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')->maxLength(40)
                        ->columnSpan(['md' => 2, 'sm' => 12]),

                    // ─── Locație ─────────────────────────────────────────
                    Forms\Components\TextInput::make('location_name')
                        ->label('Numele locației')->required()->maxLength(200)
                        ->live(debounce: 400)
                        ->helperText('Va apărea ca „loc=…" în URL-ul personalizat')
                        ->columnSpan(['md' => 6, 'sm' => 12]),
                    Forms\Components\TextInput::make('city')
                        ->label('Oraș')->required()->maxLength(120)
                        ->columnSpan(['md' => 3, 'sm' => 12]),
                    Forms\Components\TextInput::make('website')
                        ->label('Website (opțional)')->url()->maxLength(255)
                        ->placeholder('https://…')
                        ->columnSpan(['md' => 3, 'sm' => 12]),

                    // ─── Activitate ──────────────────────────────────────
                    Forms\Components\Select::make('category_slug')
                        ->label('Categorie activitate')
                        ->options(self::CATEGORY_OPTIONS)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            // Keep the display name in sync so reports
                            // that read category_name still work.
                            $set('category_name', self::CATEGORY_OPTIONS[$state] ?? null);
                        })
                        ->searchable()
                        ->helperText('Determină „tip=…" din URL — pagina /devino-partener afișează copy specific categoriei.')
                        ->columnSpan(['md' => 6, 'sm' => 12]),
                    Forms\Components\TextInput::make('category_other')
                        ->label('Sau descriere alternativă (dacă nu se potrivește niciuna)')
                        ->placeholder('ex. Centru de echitație, planetariu')
                        ->maxLength(200)
                        ->columnSpan(['md' => 6, 'sm' => 12]),
                    Forms\Components\Hidden::make('category_name'),

                    Forms\Components\TextInput::make('volume_estimate')
                        ->label('Volum estimat bilete/lună (ex: 100-500)')
                        ->maxLength(40)
                        ->columnSpan(['md' => 4, 'sm' => 12]),

                    // ─── Pipeline ────────────────────────────────────────
                    Forms\Components\Select::make('status')
                        ->options(OrganizerLead::STATUSES)
                        ->required()
                        ->default(OrganizerLead::STATUS_NEW)
                        ->columnSpan(['md' => 4, 'sm' => 12]),
                    Forms\Components\Select::make('source')
                        ->options(OrganizerLead::SOURCES)
                        ->default('manual')
                        ->required()
                        ->columnSpan(['md' => 4, 'sm' => 12]),
                    Forms\Components\Select::make('assigned_to_user_id')
                        ->label('Asignat lui')
                        ->relationship('assignedTo', 'name')
                        ->searchable()->preload()
                        ->columnSpan(['md' => 4, 'sm' => 12]),
                    Forms\Components\DateTimePicker::make('next_action_at')
                        ->label('Următoarea acțiune')
                        ->columnSpan(['md' => 4, 'sm' => 12]),
                    Forms\Components\Textarea::make('notes')
                        ->label('Note interne')->rows(2)
                        ->columnSpan(12),
                ]),

            // Live preview of the campaign URL — updates as the rep fills
            // in category + location. Hidden until both are set; clickable
            // anchor opens the destination in a new tab so the rep can
            // verify the page renders correctly with that personalization.
            SC\Section::make('Link partener personalizat')
                ->description('Linkul pe care îl trimiți leadului. Se actualizează automat când completezi categoria + numele locației.')
                ->schema([
                    Forms\Components\Placeholder::make('campaign_link')
                        ->hiddenLabel()
                        ->content(function (Get $get) {
                            $tip = $get('category_slug');
                            $loc = $get('location_name');
                            $url = self::buildCampaignLink($tip, $loc);
                            if ($url === '') {
                                return new HtmlString(
                                    '<em class="text-gray-500">Completează categoria + numele locației pentru a vedea linkul.</em>'
                                );
                            }
                            $safe = e($url);
                            return new HtmlString(<<<HTML
<div class="flex items-center gap-3">
    <a href="{$safe}" target="_blank" rel="noopener"
       class="font-mono text-sm text-primary-600 hover:text-primary-700 break-all underline">
        {$safe}
    </a>
    <button type="button"
            onclick="navigator.clipboard.writeText(this.previousElementSibling.href).then(() => { this.textContent = 'Copiat ✓'; setTimeout(() => this.textContent = 'Copiază', 1500); })"
            class="px-3 py-1 text-xs font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-md whitespace-nowrap">
        Copiază
    </button>
</div>
<p class="mt-2 text-xs text-gray-500">Pagina se va personaliza cu copy specific categoriei + va afișa banner-ul de bun-venit pentru locație.</p>
HTML);
                        }),
                ]),

            // Read-only acquisition context — collapsed by default; useful
            // when a lead actually arrived via the public form and the
            // utm/referrer fields got auto-filled.
            SC\Section::make('Sursă vizită (din public form)')
                ->description('Aceste câmpuri sunt populate automat când leadul ajunge prin /inregistrare-locatie. La adăugare manuală pot fi lăsate goale.')
                ->collapsed()
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('prefill_tip')->label('Prefill tip')->disabled(),
                    Forms\Components\TextInput::make('prefill_loc')->label('Prefill loc')->disabled(),
                    Forms\Components\TextInput::make('utm_source')->disabled(),
                    Forms\Components\TextInput::make('utm_medium')->disabled(),
                    Forms\Components\TextInput::make('utm_campaign')->disabled(),
                    Forms\Components\TextInput::make('utm_content')->disabled(),
                    Forms\Components\TextInput::make('utm_term')->disabled(),
                    Forms\Components\Textarea::make('referrer')->disabled()->rows(2)->columnSpan(4),
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
