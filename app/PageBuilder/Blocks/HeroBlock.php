<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class HeroBlock extends BaseBlock
{
    public static string $type = 'hero';
    public static string $name = 'Hero Banner';
    public static string $description = 'Full-width hero section with title, subtitle, and optional search';
    public static string $icon = 'heroicon-o-photo';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('backgroundType')
                ->label('Background Type')
                ->options([
                    'solid' => 'Solid Color',
                    'gradient' => 'Gradient',
                    'image' => 'Image',
                ])
                ->default('gradient')
                ->live(),

            FileUpload::make('backgroundImage')
                ->label('Background Image')
                ->image()
                ->directory('page-builder')
                ->disk('public')
                ->visibility('public')
                ->visible(fn ($get) => $get('backgroundType') === 'image'),

            Slider::make('overlayOpacity')
                ->label('Overlay Opacity')
                ->min(0)
                ->max(1)
                ->step(0.1)
                ->default(0.5)
                ->visible(fn ($get) => $get('backgroundType') === 'image'),

            Select::make('height')
                ->label('Height')
                ->options([
                    'small' => 'Small (400px)',
                    'medium' => 'Medium (500px)',
                    'large' => 'Large (600px)',
                    'full' => 'Full Screen',
                ])
                ->default('large'),

            Select::make('alignment')
                ->label('Text Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('center'),

            Toggle::make('showSearch')
                ->label('Show Search Bar')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(200),

            Textarea::make('subtitle')
                ->label('Subtitle')
                ->rows(2)
                ->maxLength(500),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'backgroundType' => 'gradient',
            'backgroundImage' => null,
            'overlayOpacity' => 0.5,
            'height' => 'large',
            'alignment' => 'center',
            'showSearch' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Welcome to Our Events',
                'subtitle' => 'Discover amazing experiences',
            ],
            'ro' => [
                'title' => 'Bine ați venit',
                'subtitle' => 'Descoperă experiențe unice',
            ],
        ];
    }
}
