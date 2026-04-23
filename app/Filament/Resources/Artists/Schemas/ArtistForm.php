<?php

namespace App\Filament\Resources\Artists\Schemas;

use Filament\Forms;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;

class ArtistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // DISCOGRAPHY
                SC\Section::make('Discografie')
                    ->icon('heroicon-o-musical-note')
                    ->description('Lansări ale artistului. Apar pe pagina publică a artistului.')
                    ->collapsible()->collapsed()->persistCollapsed()
                    ->schema([
                        Forms\Components\Repeater::make('discography')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\FileUpload::make('image')
                                    ->label('Imagine album')
                                    ->image()
                                    ->disk('public')
                                    ->directory('artists/discography')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nume album')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('ex: Nopți fără somn'),
                                Forms\Components\Select::make('type')
                                    ->label('Tip')
                                    ->options([
                                        'album' => 'Album',
                                        'ep' => 'EP',
                                        'single' => 'Single',
                                        'live' => 'Live album',
                                        'live_dvd' => 'Live DVD / Blu-ray',
                                        'compilation' => 'Compilație',
                                        'soundtrack' => 'Coloană sonoră',
                                        'remix' => 'Album remix',
                                    ])
                                    ->native(false)
                                    ->required(),
                                Forms\Components\TextInput::make('year')
                                    ->label('An apariție')
                                    ->numeric()
                                    ->minValue(1900)
                                    ->maxValue((int) date('Y') + 1)
                                    ->required()
                                    ->placeholder('ex: 2024'),
                            ])
                            ->columns(4)
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Adaugă lansare')
                            ->itemLabel(function (array $state): ?string {
                                $name = $state['name'] ?? null;
                                $year = $state['year'] ?? null;
                                if (!$name) return null;
                                return $year ? ($name . ' · ' . $year) : $name;
                            })
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
