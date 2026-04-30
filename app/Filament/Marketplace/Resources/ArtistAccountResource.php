<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ArtistAccountResource\Pages;
use App\Models\Artist;
use App\Models\MarketplaceArtistAccount;
use App\Services\ArtistAccount\ArtistAccountApprovalService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ArtistAccountResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceArtistAccount::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Conturi Artist';
    protected static ?string $modelLabel = 'Cont Artist';
    protected static ?string $pluralModelLabel = 'Conturi Artist';

    // Nest under the existing "Artiști" menu item so artist content + artist
    // accounts live next to each other in the sidebar.
    protected static ?string $navigationParentItem = 'Artiști';
    protected static ?int $navigationSort = 5;

    /** Show pending count as a sidebar badge (warning color) so admins see
     *  inbound requests without having to open the page. */
    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);
    }

    /**
     * The form is intentionally read-only-ish — admins shouldn't edit account
     * fields directly. Lifecycle changes go through Filament actions
     * (Approve/Reject/Suspend/Reactivate/LinkArtist) which delegate to the
     * approval service. Form is used only by the View page.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Section::make('Aplicant')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\TextInput::make('first_name')
                                ->label('Prenume')
                                ->disabled(),
                            Forms\Components\TextInput::make('last_name')
                                ->label('Nume')
                                ->disabled(),
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->disabled(),
                            Forms\Components\TextInput::make('phone')
                                ->label('Telefon')
                                ->disabled(),
                            Forms\Components\TextInput::make('locale')
                                ->label('Limbă')
                                ->disabled(),
                            Forms\Components\Placeholder::make('email_verified_state')
                                ->label('Email verificat')
                                ->content(fn (?MarketplaceArtistAccount $record) => $record?->isEmailVerified()
                                    ? new \Illuminate\Support\HtmlString('<span style="color:#16a34a;font-weight:600">Da, ' . $record->email_verified_at->translatedFormat('d M Y, H:i') . '</span>')
                                    : new \Illuminate\Support\HtmlString('<span style="color:#dc2626;font-weight:600">Nu</span>')),
                        ])->columns(2),

                    SC\Section::make('Profil revendicat')
                        ->icon('heroicon-o-musical-note')
                        ->schema([
                            Forms\Components\Placeholder::make('artist_link')
                                ->label('Artist asociat')
                                ->content(function (?MarketplaceArtistAccount $record) {
                                    if (!$record || !$record->artist_id) {
                                        return new \Illuminate\Support\HtmlString('<span style="color:#94a3b8">— niciun artist linkat —</span>');
                                    }
                                    $artist = $record->artist;
                                    if (!$artist) {
                                        return new \Illuminate\Support\HtmlString('<span style="color:#dc2626">Artist ID ' . $record->artist_id . ' (record lipsă)</span>');
                                    }
                                    $url = ArtistResource::getUrl('edit', ['record' => $artist->id]);
                                    return new \Illuminate\Support\HtmlString(
                                        '<a href="' . htmlspecialchars($url) . '" style="color:#0ea5e9;font-weight:600;text-decoration:underline">' . htmlspecialchars($artist->name) . '</a>'
                                        . ' <span style="color:#94a3b8;font-size:13px">(/' . htmlspecialchars($artist->slug) . ')</span>'
                                    );
                                }),
                        ]),

                    SC\Section::make('Mesaj revendicare')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->visible(fn (?MarketplaceArtistAccount $record) => !empty($record?->claim_message) || !empty($record?->claim_proof))
                        ->schema([
                            Forms\Components\Textarea::make('claim_message')
                                ->label('Mesaj de la aplicant')
                                ->disabled()
                                ->rows(4),
                            Forms\Components\Placeholder::make('claim_proof_links')
                                ->label('Linkuri de dovadă')
                                ->visible(fn (?MarketplaceArtistAccount $record) => !empty($record?->claim_proof))
                                ->content(function (?MarketplaceArtistAccount $record) {
                                    $proofs = $record?->claim_proof ?? [];
                                    if (empty($proofs)) {
                                        return '—';
                                    }
                                    $items = array_map(
                                        fn ($url) => '<li><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" style="color:#0ea5e9;text-decoration:underline">' . htmlspecialchars($url) . '</a></li>',
                                        $proofs
                                    );
                                    return new \Illuminate\Support\HtmlString('<ul style="margin:0;padding-left:18px">' . implode('', $items) . '</ul>');
                                }),
                        ]),

                    SC\Section::make('Decizie aprobare')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->visible(fn (?MarketplaceArtistAccount $record) => $record && in_array($record->status, ['active', 'rejected', 'suspended']))
                        ->schema([
                            Forms\Components\Placeholder::make('approved_summary')
                                ->label('Aprobat')
                                ->visible(fn (?MarketplaceArtistAccount $record) => $record?->approved_at !== null)
                                ->content(function (?MarketplaceArtistAccount $record) {
                                    if (!$record?->approved_at) {
                                        return '—';
                                    }
                                    $approver = $record->approver?->name ?? 'admin necunoscut';
                                    return $record->approved_at->translatedFormat('d M Y, H:i') . ' • de ' . $approver;
                                }),
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Motiv respingere')
                                ->disabled()
                                ->visible(fn (?MarketplaceArtistAccount $record) => !empty($record?->rejection_reason))
                                ->rows(3),
                        ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    SC\Section::make('Status')
                        ->icon('heroicon-o-flag')
                        ->schema([
                            Forms\Components\Placeholder::make('status_badge')
                                ->hiddenLabel()
                                ->content(function (?MarketplaceArtistAccount $record) {
                                    $colors = [
                                        'pending' => ['#f59e0b', 'În review'],
                                        'active' => ['#16a34a', 'Activ'],
                                        'rejected' => ['#dc2626', 'Respins'],
                                        'suspended' => ['#6b7280', 'Suspendat'],
                                    ];
                                    [$color, $label] = $colors[$record?->status ?? 'pending'] ?? ['#6b7280', $record?->status ?? '—'];
                                    return new \Illuminate\Support\HtmlString(
                                        '<span style="display:inline-block;padding:6px 14px;background:' . $color . ';color:white;border-radius:9999px;font-size:14px;font-weight:600">' . htmlspecialchars($label) . '</span>'
                                    );
                                }),
                        ]),

                    SC\Section::make('Cronologie')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Forms\Components\Placeholder::make('claim_submitted_at_formatted')
                                ->label('Aplicat la')
                                ->content(fn (?MarketplaceArtistAccount $record) => $record?->claim_submitted_at?->translatedFormat('d M Y, H:i') ?? '—'),
                            Forms\Components\Placeholder::make('last_login_at_formatted')
                                ->label('Ultima conectare')
                                ->content(fn (?MarketplaceArtistAccount $record) => $record?->last_login_at?->translatedFormat('d M Y, H:i') ?? '—'),
                        ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('claim_submitted_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('artist.logo_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => null)
                    ->size(40),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nume')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('last_name', $direction)),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon(fn (MarketplaceArtistAccount $record) => $record->isEmailVerified() ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-circle')
                    ->iconColor(fn (MarketplaceArtistAccount $record) => $record->isEmailVerified() ? 'success' : 'warning')
                    ->copyable(),
                Tables\Columns\TextColumn::make('artist.name')
                    ->label('Profil revendicat')
                    ->placeholder('—')
                    ->url(fn (MarketplaceArtistAccount $record) => $record->artist_id
                        ? ArtistResource::getUrl('edit', ['record' => $record->artist_id])
                        : null)
                    ->openUrlInNewTab()
                    ->limit(35),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'danger' => 'rejected',
                        'gray' => 'suspended',
                    ])
                    ->formatStateUsing(fn (string $state) => [
                        'pending' => 'În review',
                        'active' => 'Activ',
                        'rejected' => 'Respins',
                        'suspended' => 'Suspendat',
                    ][$state] ?? $state),
                Tables\Columns\TextColumn::make('claim_submitted_at')
                    ->label('Aplicat')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->since()
                    ->tooltip(fn (MarketplaceArtistAccount $record) => $record->claim_submitted_at?->translatedFormat('d M Y, H:i')),
                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Aprobat de')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Ultim login')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('niciodată')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'În review',
                        'active' => 'Activ',
                        'rejected' => 'Respins',
                        'suspended' => 'Suspendat',
                    ])
                    ->default('pending'),
                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label('Email verificat')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('email_verified_at'),
                        false: fn (Builder $q) => $q->whereNull('email_verified_at'),
                    ),
                Tables\Filters\TernaryFilter::make('has_artist')
                    ->label('Cu profil linkat')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('artist_id'),
                        false: fn (Builder $q) => $q->whereNull('artist_id'),
                    ),
            ])
            ->recordActions(static::buildRecordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobă selectate')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Doar conturile cu email verificat vor fi aprobate. Restul vor fi sărite.')
                        ->action(function ($records): void {
                            $service = app(ArtistAccountApprovalService::class);
                            $admin = Auth::guard('marketplace_admin')->user() ?? Auth::user();

                            $approved = 0;
                            $skipped = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'pending') {
                                    $skipped++;
                                    continue;
                                }
                                if ($service->approve($record, $admin)) {
                                    $approved++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title("Aprobate: {$approved}")
                                ->body($skipped > 0 ? "Sărite (email neverificat sau status diferit): {$skipped}" : null)
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    /**
     * The lifecycle action stack. Visibility on each action is gated to the
     * states where it makes sense, so the row UI never shows a button that
     * would error out.
     */
    protected static function buildRecordActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobă')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobă cererea')
                ->modalDescription(fn (MarketplaceArtistAccount $record) => $record->isEmailVerified()
                    ? 'Contul va fi marcat activ și aplicantul va primi un email de confirmare.'
                    : 'Atenție: emailul nu este încă verificat. Aprobarea va eșua.')
                ->visible(fn (MarketplaceArtistAccount $record) => $record->status === 'pending')
                ->action(function (MarketplaceArtistAccount $record) {
                    $admin = Auth::guard('marketplace_admin')->user() ?? Auth::user();
                    $service = app(ArtistAccountApprovalService::class);

                    if (!$service->approve($record, $admin)) {
                        Notification::make()
                            ->title('Nu s-a putut aproba contul')
                            ->body('Aplicantul trebuie să-și verifice emailul înainte de aprobare.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title('Cont aprobat')
                        ->body('Aplicantul a primit emailul de confirmare.')
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label('Respinge')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (MarketplaceArtistAccount $record) => $record->status === 'pending')
                ->schema([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motiv (vizibil aplicantului)')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->action(function (MarketplaceArtistAccount $record, array $data) {
                    app(ArtistAccountApprovalService::class)->reject($record, $data['reason']);

                    Notification::make()
                        ->title('Cerere respinsă')
                        ->body('Aplicantul a fost notificat prin email cu motivul.')
                        ->success()
                        ->send();
                }),

            Action::make('suspend')
                ->label('Suspendă')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Suspendarea revocă toate token-urile active. Artistul va trebui să se reconecteze după reactivare.')
                ->visible(fn (MarketplaceArtistAccount $record) => $record->status === 'active')
                ->action(function (MarketplaceArtistAccount $record) {
                    app(ArtistAccountApprovalService::class)->suspend($record);
                    Notification::make()->title('Cont suspendat')->success()->send();
                }),

            Action::make('reactivate')
                ->label('Reactivează')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (MarketplaceArtistAccount $record) => $record->status === 'suspended')
                ->action(function (MarketplaceArtistAccount $record) {
                    app(ArtistAccountApprovalService::class)->reactivate($record);
                    Notification::make()->title('Cont reactivat')->success()->send();
                }),

            Action::make('link_artist')
                ->label(fn (MarketplaceArtistAccount $record) => $record->artist_id ? 'Schimbă profil' : 'Asociază profil')
                ->icon('heroicon-o-link')
                ->color('info')
                ->schema([
                    Forms\Components\Select::make('artist_id')
                        ->label('Profil de artist')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Artist::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%")
                            ->limit(20)
                            ->pluck('name', 'id')
                            ->toArray())
                        ->getOptionLabelUsing(fn ($value) => Artist::find($value)?->name)
                        ->required(),
                ])
                ->action(function (MarketplaceArtistAccount $record, array $data) {
                    app(ArtistAccountApprovalService::class)->linkArtist($record, (int) $data['artist_id']);
                    Notification::make()->title('Profil asociat')->success()->send();
                }),

            Action::make('unlink_artist')
                ->label('Dezasociază profil')
                ->icon('heroicon-o-link-slash')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (MarketplaceArtistAccount $record) => $record->artist_id !== null)
                ->action(function (MarketplaceArtistAccount $record) {
                    app(ArtistAccountApprovalService::class)->linkArtist($record, null);
                    Notification::make()->title('Profil dezasociat')->success()->send();
                }),

            Action::make('send_password_reset')
                ->label('Trimite reset parolă')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (MarketplaceArtistAccount $record) => $record->status === 'active')
                ->action(function (MarketplaceArtistAccount $record) {
                    $client = $record->marketplaceClient;
                    if (!$client) {
                        Notification::make()->title('Marketplace lipsă')->danger()->send();
                        return;
                    }

                    DB::table('marketplace_password_resets')
                        ->where('email', $record->email)
                        ->where('type', 'artist')
                        ->where('marketplace_client_id', $client->id)
                        ->delete();

                    $token = Str::random(64);
                    DB::table('marketplace_password_resets')->insert([
                        'email' => $record->email,
                        'type' => 'artist',
                        'marketplace_client_id' => $client->id,
                        'token' => Hash::make($token),
                        'created_at' => now(),
                    ]);

                    // Re-use the controller's reset email helper via reflection-free
                    // dispatch: instantiate the controller and call the protected
                    // helper through a tiny anonymous proxy. Simpler: just send a
                    // generic message here pointing to /artist/resetare-parola.
                    $domain = rtrim($client->domain, '/');
                    if ($domain && !str_starts_with($domain, 'http')) {
                        $domain = 'https://' . $domain;
                    }
                    $resetUrl = $domain . '/artist/resetare-parola?' . http_build_query([
                        'token' => $token,
                        'email' => $record->email,
                    ]);

                    $html = '<p>Salut ' . htmlspecialchars($record->first_name) . ',</p>'
                        . '<p>Un administrator a inițiat o resetare a parolei pentru contul tău de artist.</p>'
                        . '<p><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;background:#A51C30;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600">Resetează parola</a></p>'
                        . '<p style="color:#94a3b8;font-size:12px">Linkul expiră în 60 de minute.</p>';

                    \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                        $client,
                        $record->email,
                        $record->first_name ?: 'Artist',
                        'Resetare parolă — cont artist',
                        $html,
                        ['template_slug' => 'artist_password_reset']
                    );

                    Notification::make()
                        ->title('Email trimis')
                        ->body('Linkul de resetare a fost trimis la ' . $record->email)
                        ->success()
                        ->send();
                }),

            ViewAction::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArtistAccounts::route('/'),
            'create' => Pages\CreateArtistAccount::route('/create'),
            'view' => Pages\ViewArtistAccount::route('/{record}'),
        ];
    }
}
