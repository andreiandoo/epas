<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class EventForm
{
    public static function schema(): array
    {
        $locales = config('locales.available', ['en']);

        return [
            Tabs::make('Translations')
                ->tabs(collect($locales)->map(function (string $loc) {
                    return Tabs\Tab::make(strtoupper($loc))
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make("title.$loc")
                                    ->label('Title')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($loc) {
                                        $slugKey = "slug.$loc";
                                        if (! $get($slugKey)) {
                                            $set($slugKey, Str::slug((string) $state ?? ''));
                                        }
                                    })
                                    ->maxLength(255),

                                TextInput::make("subtitle.$loc")
                                    ->label('Subtitle')
                                    ->maxLength(255),
                            ]),

                            Textarea::make("short_description.$loc")
                                ->label('Short Description')
                                ->rows(3)
                                ->columnSpanFull(),

                            // TEMP FIX: Use Textarea instead of RichEditor to avoid tiptap-php schema error.
                            Textarea::make("description.$loc")
                                ->label('Description (plain text for now)')
                                ->rows(8)
                                ->columnSpanFull(),

                            TextInput::make("slug.$loc")
                                ->label('Slug')
                                ->helperText('Will default from Title if left empty.')
                                ->maxLength(255),
                        ])->columns(12);
                })->all()),
        ];
    }
}
