<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class EmbedBlock extends BaseBlock
{
    public static string $type = 'embed';
    public static string $name = 'Embed / iFrame';
    public static string $description = 'Embed external content, widgets, or iframes';
    public static string $icon = 'heroicon-o-code-bracket-square';
    public static string $category = 'advanced';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('type')
                ->label('Embed Type')
                ->options([
                    'url' => 'URL / iFrame',
                    'youtube' => 'YouTube Video',
                    'vimeo' => 'Vimeo Video',
                    'spotify' => 'Spotify',
                    'soundcloud' => 'SoundCloud',
                    'twitter' => 'Twitter/X Post',
                    'instagram' => 'Instagram Post',
                    'code' => 'Custom HTML/Script',
                ])
                ->default('url')
                ->reactive(),

            Select::make('aspectRatio')
                ->label('Aspect Ratio')
                ->options([
                    '16:9' => '16:9 (Widescreen)',
                    '4:3' => '4:3 (Standard)',
                    '1:1' => '1:1 (Square)',
                    '9:16' => '9:16 (Vertical)',
                    'auto' => 'Auto Height',
                ])
                ->default('16:9'),

            TextInput::make('maxWidth')
                ->label('Maximum Width (px)')
                ->numeric()
                ->minValue(200)
                ->maxValue(1920)
                ->default(800),

            Toggle::make('allowFullscreen')
                ->label('Allow Fullscreen')
                ->default(true),

            Toggle::make('lazyLoad')
                ->label('Lazy Load')
                ->default(true)
                ->helperText('Load only when visible on screen'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title (for accessibility)')
                ->maxLength(200),

            TextInput::make('url')
                ->label('Embed URL')
                ->url()
                ->maxLength(1000)
                ->helperText('For YouTube, Vimeo, etc. - just paste the video URL'),

            Textarea::make('code')
                ->label('Custom Embed Code')
                ->rows(6)
                ->helperText('Paste embed code from external services')
                ->visible(fn ($get) => $get('../settings.type') === 'code'),

            TextInput::make('caption')
                ->label('Caption (optional)')
                ->maxLength(300),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'type' => 'url',
            'aspectRatio' => '16:9',
            'maxWidth' => 800,
            'allowFullscreen' => true,
            'lazyLoad' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Embedded Content',
                'url' => '',
                'code' => '',
                'caption' => '',
            ],
            'ro' => [
                'title' => 'Conținut Încorporat',
                'url' => '',
                'code' => '',
                'caption' => '',
            ],
        ];
    }
}
