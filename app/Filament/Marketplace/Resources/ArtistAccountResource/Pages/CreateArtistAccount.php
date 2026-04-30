<?php

namespace App\Filament\Marketplace\Resources\ArtistAccountResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ArtistAccountResource;
use App\Models\Artist;
use App\Models\MarketplaceArtistAccount;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin-side artist-account creation. Bypasses the public register flow:
 * - account is created `active` and email_verified_at = now
 * - admin enters or generates a password
 * - linked artist is required (no orphan accounts from this path)
 *
 * The public flow (ambilet.ro/artist/inregistrare) still produces
 * `pending` accounts that go through review.
 */
class CreateArtistAccount extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ArtistAccountResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Cont')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->label('Prenume')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('last_name')
                        ->label('Nume')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(190)
                        ->rules([
                            // Unique per marketplace_client (matches the
                            // model's UNIQUE(marketplace_client_id, email)).
                            fn () => Rule::unique('marketplace_artist_accounts', 'email')
                                ->where('marketplace_client_id', static::getMarketplaceClient()?->id),
                        ]),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\Select::make('locale')
                        ->label('Limbă')
                        ->options(['ro' => 'Română', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español'])
                        ->default('ro')
                        ->required(),
                ])->columns(2),

            SC\Section::make('Profil revendicat')
                ->icon('heroicon-o-musical-note')
                ->schema([
                    Forms\Components\Select::make('artist_id')
                        ->label('Artist')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->getSearchResultsUsing(fn (string $search) => Artist::query()
                            ->whereHas('marketplaceClients', function ($q) {
                                $q->where('marketplace_artist_partners.marketplace_client_id', static::getMarketplaceClient()?->id);
                            })
                            ->where(function ($q) use ($search) {
                                $needle = '%' . mb_strtolower($search) . '%';
                                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                                  ->orWhereRaw('LOWER(slug) LIKE ?', [$needle]);
                            })
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->toArray())
                        ->getOptionLabelUsing(fn ($value) => Artist::find($value)?->name)
                        ->helperText('Doar artiștii care sunt parteneri ai marketplace-ului tău apar aici.')
                        ->rules([
                            // Reject if the artist already has an active or
                            // pending claim on this marketplace.
                            function (?MarketplaceArtistAccount $record) {
                                return function (string $attribute, $value, $fail) use ($record) {
                                    $existing = MarketplaceArtistAccount::where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                        ->where('artist_id', $value)
                                        ->whereIn('status', ['pending', 'active']);
                                    if ($record) {
                                        $existing->where('id', '!=', $record->id);
                                    }
                                    if ($existing->exists()) {
                                        $fail('Acest artist are deja un cont activ sau în review.');
                                    }
                                };
                            },
                        ]),
                ]),

            SC\Section::make('Parolă')
                ->icon('heroicon-o-key')
                ->description('Setează manual sau generează una aleator. Artistul își poate schimba parola din /artist/cont/setari.')
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->label('Parolă')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->maxLength(255)
                        ->revealable()
                        ->suffixAction(
                            \Filament\Actions\Action::make('generate_password')
                                ->icon('heroicon-m-arrow-path')
                                ->label('Generează')
                                ->action(function (Set $set) {
                                    $set('password', Str::random(12));
                                })
                        ),
                    Forms\Components\Toggle::make('send_credentials_email')
                        ->label('Trimite email cu credențiale')
                        ->helperText('Artistul primește pe email-ul de mai sus credențialele de login. (Funcție pregătită pentru viitor — momentan necredențialele se transmit manual.)')
                        ->default(false)
                        ->disabled()
                        ->dehydrated(false),
                ])->columns(2),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Tie the new account to the current marketplace and short-circuit
        // the review workflow (admin-created = trusted, email-verified).
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $data['status'] = 'active';
        $data['email_verified_at'] = now();
        $data['approved_at'] = now();
        $data['approved_by'] = auth()->id();
        $data['claim_submitted_at'] = now();

        // The model casts password to 'hashed' so plaintext is auto-hashed
        // on save — but we double-hash defensively in case casts are
        // stripped.
        if (isset($data['password']) && !str_starts_with($data['password'], '$2y$')) {
            $data['password'] = Hash::make($data['password']);
        }

        // Send_credentials_email is a UI-only flag (dehydrated=false) so
        // it never lands in the DB.

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Cont creat')
            ->body('Contul de artist a fost creat și marcat ca activ.')
            ->success()
            ->send();
    }
}
