<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class MapBlock extends BaseBlock
{
    public static string $type = 'map';
    public static string $name = 'Map';
    public static string $description = 'Interactive map with markers';
    public static string $icon = 'heroicon-o-map-pin';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('provider')
                ->label('Map Provider')
                ->options([
                    'google' => 'Google Maps',
                    'openstreetmap' => 'OpenStreetMap',
                ])
                ->default('openstreetmap'),

            TextInput::make('latitude')
                ->label('Center Latitude')
                ->numeric()
                ->default(44.4268)
                ->helperText('e.g., 44.4268 for Bucharest'),

            TextInput::make('longitude')
                ->label('Center Longitude')
                ->numeric()
                ->default(26.1025)
                ->helperText('e.g., 26.1025 for Bucharest'),

            Select::make('zoom')
                ->label('Zoom Level')
                ->options([
                    10 => 'City (10)',
                    12 => 'District (12)',
                    14 => 'Neighborhood (14)',
                    16 => 'Street (16)',
                    18 => 'Building (18)',
                ])
                ->default(14),

            Select::make('height')
                ->label('Map Height')
                ->options([
                    'sm' => 'Small (250px)',
                    'md' => 'Medium (400px)',
                    'lg' => 'Large (500px)',
                    'xl' => 'Extra Large (600px)',
                ])
                ->default('md'),

            Toggle::make('showMarker')
                ->label('Show Center Marker')
                ->default(true),

            Toggle::make('allowInteraction')
                ->label('Allow Zoom/Pan')
                ->default(true),

            Toggle::make('showFullscreenControl')
                ->label('Show Fullscreen Button')
                ->default(true),

            Select::make('borderRadius')
                ->label('Border Radius')
                ->options([
                    'none' => 'None',
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ])
                ->default('md'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            TextInput::make('address')
                ->label('Address (for display)')
                ->maxLength(300),

            Repeater::make('markers')
                ->label('Additional Markers')
                ->schema([
                    TextInput::make('lat')
                        ->label('Latitude')
                        ->numeric()
                        ->required(),

                    TextInput::make('lng')
                        ->label('Longitude')
                        ->numeric()
                        ->required(),

                    TextInput::make('title')
                        ->label('Marker Title')
                        ->maxLength(100),

                    TextInput::make('description')
                        ->label('Marker Description')
                        ->maxLength(200),
                ])
                ->collapsible()
                ->defaultItems(0)
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'provider' => 'openstreetmap',
            'latitude' => 44.4268,
            'longitude' => 26.1025,
            'zoom' => 14,
            'height' => 'md',
            'showMarker' => true,
            'allowInteraction' => true,
            'showFullscreenControl' => true,
            'borderRadius' => 'md',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'address' => '',
                'markers' => [],
            ],
            'ro' => [
                'title' => '',
                'address' => '',
                'markers' => [],
            ],
        ];
    }
}
