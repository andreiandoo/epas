<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class SocialLinksBlock extends BaseBlock
{
    public static string $type = 'social-links';
    public static string $name = 'Social Links';
    public static string $description = 'Social media profile links';
    public static string $icon = 'heroicon-o-share';
    public static string $category = 'navigation';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Style')
                ->options([
                    'icons' => 'Icons Only',
                    'rounded' => 'Rounded Icons',
                    'square' => 'Square Icons',
                    'buttons' => 'Full Buttons',
                    'minimal' => 'Minimal (Text Links)',
                ])
                ->default('rounded'),

            Select::make('size')
                ->label('Size')
                ->options([
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ])
                ->default('md'),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('center'),

            Select::make('colorScheme')
                ->label('Color Scheme')
                ->options([
                    'brand' => 'Brand Colors',
                    'primary' => 'Primary Color',
                    'dark' => 'Dark',
                    'light' => 'Light',
                ])
                ->default('brand'),

            Toggle::make('showLabels')
                ->label('Show Labels')
                ->default(false),

            Toggle::make('openInNewTab')
                ->label('Open in New Tab')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(100),

            TextInput::make('facebook')
                ->label('Facebook URL')
                ->url()
                ->prefix('facebook.com/')
                ->maxLength(255),

            TextInput::make('instagram')
                ->label('Instagram URL')
                ->url()
                ->prefix('instagram.com/')
                ->maxLength(255),

            TextInput::make('twitter')
                ->label('X (Twitter) URL')
                ->url()
                ->prefix('x.com/')
                ->maxLength(255),

            TextInput::make('youtube')
                ->label('YouTube URL')
                ->url()
                ->prefix('youtube.com/')
                ->maxLength(255),

            TextInput::make('tiktok')
                ->label('TikTok URL')
                ->url()
                ->prefix('tiktok.com/')
                ->maxLength(255),

            TextInput::make('linkedin')
                ->label('LinkedIn URL')
                ->url()
                ->prefix('linkedin.com/')
                ->maxLength(255),

            TextInput::make('spotify')
                ->label('Spotify URL')
                ->url()
                ->maxLength(255),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'rounded',
            'size' => 'md',
            'alignment' => 'center',
            'colorScheme' => 'brand',
            'showLabels' => false,
            'openInNewTab' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Follow Us',
                'facebook' => '',
                'instagram' => '',
                'twitter' => '',
                'youtube' => '',
                'tiktok' => '',
                'linkedin' => '',
                'spotify' => '',
            ],
            'ro' => [
                'title' => 'Urmărește-ne',
                'facebook' => '',
                'instagram' => '',
                'twitter' => '',
                'youtube' => '',
                'tiktok' => '',
                'linkedin' => '',
                'spotify' => '',
            ],
        ];
    }
}
