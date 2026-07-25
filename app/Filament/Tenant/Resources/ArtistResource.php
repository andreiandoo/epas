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
                        ->placeholder('Scrie un nume, ex: Maia Morgenstern...')
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
                        ->placeholder('Actor, Regizor, Scenograf...')
                        ->maxLength(120),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Data nașterii')
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->maxDate(now())
                        ->visible($isTheater),
                ])->columns(2),

            SC\Section::make('Biografie')
                ->schema([
                    SC\Tabs::make('bio')
                        ->tabs([
                            SC\Tabs\Tab::make('Română')->schema([
                                Forms\Components\RichEditor::make('bio.ro')->label('Biografie (RO)'),
                            ]),
                            SC\Tabs\Tab::make('English')->schema([
                                Forms\Components\RichEditor::make('bio.en')->label('Biography (EN)'),
                            ]),
                        ])->columnSpanFull(),
                ]),

            SC\Section::make('Foto & Contact')
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
                        ->label('Status')
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
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
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
