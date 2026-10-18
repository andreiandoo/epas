<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ArtistResource\Pages;
use App\Models\TenantArtist;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ArtistResource extends Resource
{
    protected static ?string $model = TenantArtist::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Artiști';
    protected static ?string $modelLabel = 'Artist';

    public static function getModelLabel(): string
    {
        return __('Artist');
    }
    protected static ?string $pluralModelLabel = 'Artiști';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $isTheater = (bool) ($tenant?->isTheater());
        $isCompetition = $tenant?->tenant_type === \App\Enums\TenantType::Competition;
        // Agențiile de artiști (și artiștii / trupele) au un roster: profil public + booking
        $isRoster = in_array($tenant?->tenant_type, [\App\Enums\TenantType::Agency, \App\Enums\TenantType::Artist, \App\Enums\TenantType::Festival], true);

        return $schema->schema([
            Forms\Components\Hidden::make('tenant_id')->default($tenant?->id),

            SC\Section::make('Importă din biblioteca Tixello')
                ->description('Caută un artist existent în biblioteca globală și preia numele, fotografia și biografia lui.')
                ->icon('heroicon-o-book-open')
                ->collapsible()
                ->schema([
                    // Câmp de căutare dedicat (nu se salvează — doar pentru import)
                    Forms\Components\TextInput::make('library_query')
                        ->label('Caută artist în bibliotecă')
                        ->placeholder(__('Scrie un nume, ex: Maia Morgenstern...'))
                        ->live(debounce: 500)
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    // Rezultate live
                    Forms\Components\Placeholder::make('library_results')
                        ->label('')
                        ->content(function (SGet $get) {
                            $name = $get('library_query');
                            if (empty($name) || mb_strlen($name) < 2) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="text-xs text-gray-400 italic py-1">Scrie cel puțin 2 litere în câmpul de mai sus.</div>'
                                );
                            }

                            $artists = \App\Models\Artist::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%'])
                                ->limit(6)->get();

                            if ($artists->isEmpty()) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="text-xs text-gray-400 italic py-1">Niciun artist în bibliotecă pentru „' . e($name) . '".</div>'
                                );
                            }

                            $html = '<div class="space-y-1 py-1"><div class="text-xs font-medium text-amber-600 mb-1">Artiști din bibliotecă:</div>';
                            foreach ($artists as $artist) {
                                $city = $artist->city ? ' — ' . e($artist->city) : '';
                                $html .= '<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">'
                                    . '<span class="text-xs"><strong>' . e($artist->name) . '</strong>' . $city . '</span>'
                                    . '<button type="button" wire:click="importFromLibrary(' . $artist->id . ')" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-amber-700 bg-amber-100 rounded hover:bg-amber-200 dark:text-amber-300 dark:bg-amber-800 dark:hover:bg-amber-700">'
                                    . '+ Importă</button>'
                                    . '</div>';
                            }
                            $html .= '</div>';
                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ])
                ->visible(fn ($context) => $context === 'create'),

            SC\Section::make('Identitate')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nume')
                        ->required()
                        ->maxLength(190)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, SSet $set) {
                            if ($state) { $set('slug', Str::slug($state)); }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->rule('alpha_dash')
                        ->placeholder('generat-din-nume'),
                    Forms\Components\TextInput::make('role')
                        ->label('Rol / Funcție')
                        ->placeholder(__('Actor, Regizor, Scenograf...'))
                        ->helperText($isCompetition ? 'Pentru luptători scrie „fighter”: apare profilul sportiv mai jos.' : null)
                        ->live(onBlur: true)
                        ->maxLength(120),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Data nașterii')
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->maxDate(now())
                        ->visible($isTheater),
                ])->columns(2),

            // Profilul sportiv (gale de lupte): fight card-ul și pagina luptătorilor citesc din meta
            SC\Section::make('Profil de luptător')
                ->description('Recordul, categoria și datele afișate în fight card și pe pagina luptătorilor.')
                ->visible(fn (SGet $get) => $get('role') === 'fighter')
                ->schema([
                    Forms\Components\TextInput::make('meta.nickname')->label('Poreclă')->maxLength(60),
                    Forms\Components\TextInput::make('meta.country')->label('Țara (cod ISO, ex. RO)')->maxLength(2),
                    Forms\Components\TextInput::make('meta.weight_class')->label('Categoria')->placeholder('Ușoară (70 kg)')->maxLength(64),
                    Forms\Components\Select::make('meta.discipline')->label('Disciplina')->options(\App\Models\EventBout::DISCIPLINES),
                    Forms\Components\TextInput::make('meta.record.w')->label('Victorii')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('meta.record.l')->label('Înfrângeri')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('meta.record.d')->label('Egaluri')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('meta.team')->label('Echipa / sala')->maxLength(80),
                    Forms\Components\TextInput::make('meta.height_cm')->label('Înălțime (cm)')->numeric(),
                    Forms\Components\TextInput::make('meta.reach_cm')->label('Anvergură (cm)')->numeric(),
                    Forms\Components\Select::make('meta.stance')->label('Gardă')->options(['Orthodox' => 'Orthodox', 'Southpaw' => 'Southpaw', 'Switch' => 'Switch']),
                    Forms\Components\Toggle::make('meta.is_champion')->label('Campion en titre'),
                    Forms\Components\DatePicker::make('birth_date')->label('Data nașterii')->native(false)->displayFormat('d.m.Y')->maxDate(now()),
                ])->columns(3),

            // Profilul din roster (agenții): pagina artistului, filtrele rosterului și booking-ul citesc din meta
            SC\Section::make('Profil în roster')
                ->description('Ce apare pe site: în lista artiștilor (filtre după tip, gen, teritoriu) și pe pagina artistului, unde se cer booking-uri.')
                ->visible($isRoster)
                ->schema([
                    Forms\Components\Select::make('meta.kind')->label('Tip')->options(['solo' => 'Artist solo', 'band' => 'Trupă', 'dj' => 'DJ / producător', 'duo' => 'Duo', 'ensemble' => 'Ansamblu / orchestră'])->default('solo'),
                    Forms\Components\TagsInput::make('meta.genres')->label('Genuri')->placeholder('indie, pop, electronic…'),
                    Forms\Components\TextInput::make('meta.city')->label('Oraș')->maxLength(80),
                    Forms\Components\TextInput::make('meta.country')->label('Țara (cod ISO, ex. RO)')->maxLength(2),
                    Forms\Components\TextInput::make('meta.tagline')->label('O frază despre artist')->maxLength(140)->columnSpan(2),
                    Forms\Components\TagsInput::make('meta.territories')->label('Teritorii de booking')->placeholder('România, Europa…'),
                    Forms\Components\Toggle::make('meta.bookable')->label('Primește cereri de booking')->default(true),
                    Forms\Components\Toggle::make('meta.featured')->label('Recomandat pe prima pagină'),
                    Forms\Components\TextInput::make('meta.hero_url')->label('Imagine mare (link)')->url()->maxLength(500)->helperText('Gol = fotografia de portret'),
                    Forms\Components\TextInput::make('meta.youtube_id')->label('Video YouTube (ID)')->maxLength(20)->helperText('Partea de după watch?v= din link'),
                    Forms\Components\TextInput::make('meta.listeners')->label('Ascultători lunari')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('meta.links.spotify')->label('Spotify')->url(),
                    Forms\Components\TextInput::make('meta.links.instagram')->label('Instagram')->url(),
                    Forms\Components\TextInput::make('meta.links.youtube')->label('YouTube')->url(),
                    Forms\Components\TextInput::make('meta.links.tiktok')->label('TikTok')->url(),
                    Forms\Components\TextInput::make('meta.links.website')->label('Site')->url(),
                    Forms\Components\Textarea::make('meta.quote.text')->label('Citat de presă')->rows(2)->columnSpan(2),
                    Forms\Components\TextInput::make('meta.quote.source')->label('Sursa citatului')->maxLength(80),
                ])->columns(3),

            SC\Section::make('Biografie')
                ->schema([
                    SC\Tabs::make('bio')
                        ->tabs([
                            SC\Tabs\Tab::make('Română')->schema([
                                Forms\Components\RichEditor::make('bio.ro')->label('Biografie (RO)'),
                            ]),
                            SC\Tabs\Tab::make('English')->schema([
                                Forms\Components\RichEditor::make('bio.en')->label(__('Biography (EN)')),
                            ]),
                        ])->columnSpanFull(),
                ]),

            SC\Section::make(__('Foto & Contact'))
                ->schema([
                    Forms\Components\FileUpload::make('photo_url')
                        ->label('Fotografie (portret)')
                        ->helperText('Trage o imagine aici sau click pentru a încărca.')
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('artist-photos')
                        ->visibility('public')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('gallery')
                        ->label('Galerie foto')
                        ->helperText('Apare pe pagina publică a actorului. Trage pentru a reordona.')
                        ->multiple()->image()->reorderable()->appendFiles()
                        ->disk('public')->directory('artist-gallery')->visibility('public')->imageEditor()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('email')->label('Email')->email(),
                    Forms\Components\TextInput::make('phone')->label('Telefon'),
                ])->columns(2),

            SC\Section::make('Detalii')
                ->schema([
                    Forms\Components\Toggle::make('is_resident')->label('Membru rezident al trupei')->default(true),
                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options(['active' => 'Activ', 'inactive' => 'Inactiv'])
                        ->default('active'),
                    Forms\Components\DatePicker::make('contract_start')->label('Început contract'),
                    Forms\Components\DatePicker::make('contract_end')->label('Sfârșit contract'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_url')->label('Foto')->circular()->defaultImageUrl(url('/images/placeholder-avatar.png')),
                Tables\Columns\TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('role')->label('Rol')->badge()->color('warning'),
                Tables\Columns\IconColumn::make('is_resident')->label('Rezident')->boolean(),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Adăugat')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Activ', 'inactive' => 'Inactiv']),
                Tables\Filters\TernaryFilter::make('is_resident')->label('Rezident'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListArtists::route('/'),
            'create' => Pages\CreateArtist::route('/create'),
            'edit'   => Pages\EditArtist::route('/{record}/edit'),
        ];
    }
}
