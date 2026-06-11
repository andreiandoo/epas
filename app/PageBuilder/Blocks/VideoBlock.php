<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class VideoBlock extends BaseBlock
{
    public static string $type = 'video';
    public static string $name = 'Video';
    public static string $description = 'Embed YouTube, Vimeo, or custom videos';
    public static string $icon = 'heroicon-o-play-circle';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('platform')
                ->label('Video Platform')
                ->options([
                    'youtube' => 'YouTube',
                    'vimeo' => 'Vimeo',
                    'custom' => 'Custom URL',
                ])
                ->default('youtube')
                ->live(),

            TextInput::make('videoId')
                ->label('Video ID')
                ->required()
                ->visible(fn ($get) => in_array($get('platform'), ['youtube', 'vimeo']))
                ->helperText(fn ($get) => match($get('platform')) {
                    'youtube' => 'e.g., dQw4w9WgXcQ from youtube.com/watch?v=dQw4w9WgXcQ',
                    'vimeo' => 'e.g., 123456789 from vimeo.com/123456789',
                    default => ''
                }),

            TextInput::make('customUrl')
                ->label('Video URL')
                ->url()
                ->visible(fn ($get) => $get('platform') === 'custom'),

            Select::make('aspectRatio')
                ->label('Aspect Ratio')
                ->options([
                    '16:9' => '16:9 (Standard)',
                    '4:3' => '4:3',
                    '21:9' => '21:9 (Cinematic)',
                    '1:1' => '1:1 (Square)',
                ])
                ->default('16:9'),

            Select::make('maxWidth')
                ->label('Maximum Width')
                ->options([
                    'sm' => 'Small (480px)',
                    'md' => 'Medium (640px)',
                    'lg' => 'Large (800px)',
                    'xl' => 'Extra Large (1024px)',
                    'full' => 'Full Width',
                ])
                ->default('lg'),

            Toggle::make('autoplay')
                ->label('Autoplay (muted)')
                ->default(false),

            Toggle::make('loop')
                ->label('Loop Video')
                ->default(false),

            Toggle::make('controls')
                ->label('Show Controls')
                ->default(true),

            Toggle::make('showTitle')
                ->label('Show Video Title')
                ->default(false),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            TextInput::make('caption')
                ->label('Caption')
                ->maxLength(300),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'platform' => 'youtube',
            'videoId' => '',
            'customUrl' => '',
            'aspectRatio' => '16:9',
            'maxWidth' => 'lg',
            'autoplay' => false,
            'loop' => false,
            'controls' => true,
            'showTitle' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'caption' => '',
            ],
            'ro' => [
                'title' => '',
                'caption' => '',
            ],
        ];
    }
}
