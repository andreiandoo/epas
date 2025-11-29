<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CtaBannerBlock extends BaseBlock
{
    public static string $type = 'cta-banner';
    public static string $name = 'Call to Action Banner';
    public static string $description = 'Promotional banner with call-to-action button';
    public static string $icon = 'heroicon-o-megaphone';
    public static string $category = 'marketing';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Banner Style')
                ->options([
                    'primary' => 'Primary Color',
                    'secondary' => 'Secondary Color',
                    'gradient' => 'Gradient',
                    'image' => 'Background Image',
                    'dark' => 'Dark',
                ])
                ->default('primary')
                ->live(),

            FileUpload::make('backgroundImage')
                ->label('Background Image')
                ->image()
                ->directory('page-builder')
                ->disk('public')
                ->visibility('public')
                ->visible(fn ($get) => $get('style') === 'image'),

            Select::make('size')
                ->label('Banner Size')
                ->options([
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                ])
                ->default('medium'),

            Select::make('alignment')
                ->label('Content Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('center'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(200),

            TextInput::make('subtitle')
                ->label('Subtitle')
                ->maxLength(500),

            TextInput::make('buttonText')
                ->label('Button Text')
                ->required()
                ->maxLength(50),

            TextInput::make('buttonUrl')
                ->label('Button URL')
                ->required()
                ->url()
                ->maxLength(255),

            Select::make('buttonStyle')
                ->label('Button Style')
                ->options([
                    'primary' => 'Primary',
                    'secondary' => 'Secondary',
                    'white' => 'White',
                    'outline' => 'Outline',
                ])
                ->default('white'),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'primary',
            'backgroundImage' => null,
            'size' => 'medium',
            'alignment' => 'center',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Ready to Get Started?',
                'subtitle' => 'Join thousands of happy customers',
                'buttonText' => 'Get Started',
                'buttonUrl' => '/events',
                'buttonStyle' => 'white',
            ],
            'ro' => [
                'title' => 'Pregătit să Începi?',
                'subtitle' => 'Alătură-te miilor de clienți mulțumiți',
                'buttonText' => 'Începe Acum',
                'buttonUrl' => '/events',
                'buttonStyle' => 'white',
            ],
        ];
    }
}
