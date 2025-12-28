<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class AudioBlock extends BaseBlock
{
    public static string $type = 'audio';
    public static string $name = 'Audio Player';
    public static string $description = 'Embed audio files or podcasts';
    public static string $icon = 'heroicon-o-speaker-wave';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Player Style')
                ->options([
                    'default' => 'Default',
                    'minimal' => 'Minimal',
                    'card' => 'Card with Cover',
                ])
                ->default('default'),

            Toggle::make('autoplay')
                ->label('Autoplay')
                ->default(false),

            Toggle::make('loop')
                ->label('Loop')
                ->default(false),

            Toggle::make('showDownload')
                ->label('Allow Download')
                ->default(false),

            Toggle::make('showWaveform')
                ->label('Show Waveform')
                ->default(false),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->maxLength(200),

            TextInput::make('artist')
                ->label('Artist/Author')
                ->maxLength(150),

            FileUpload::make('audioFile')
                ->label('Audio File')
                ->acceptedFileTypes(['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3'])
                ->directory('audio')
                ->disk('public'),

            TextInput::make('audioUrl')
                ->label('Or Audio URL')
                ->url()
                ->maxLength(500)
                ->helperText('External audio URL (if not uploading)'),

            FileUpload::make('coverImage')
                ->label('Cover Image')
                ->image()
                ->directory('audio-covers')
                ->disk('public'),

            TextInput::make('duration')
                ->label('Duration')
                ->placeholder('e.g., 3:45')
                ->maxLength(20),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'default',
            'autoplay' => false,
            'loop' => false,
            'showDownload' => false,
            'showWaveform' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Audio Track',
                'artist' => '',
                'audioFile' => null,
                'audioUrl' => '',
                'coverImage' => null,
                'duration' => '',
            ],
            'ro' => [
                'title' => 'Piesă Audio',
                'artist' => '',
                'audioFile' => null,
                'audioUrl' => '',
                'coverImage' => null,
                'duration' => '',
            ],
        ];
    }
}
