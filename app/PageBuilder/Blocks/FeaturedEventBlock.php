<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class FeaturedEventBlock extends BaseBlock
{
    public static string $type = 'featured-event';
    public static string $name = 'Featured Event';
    public static string $description = 'Highlight a single event with large display';
    public static string $icon = 'heroicon-o-star';
    public static string $category = 'events';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('eventSource')
                ->label('Event Source')
                ->options([
                    'auto' => 'Auto (Next Upcoming)',
                    'featured' => 'Featured Event',
                    'manual' => 'Select Manually',
                ])
                ->default('auto')
                ->live(),

            TextInput::make('eventId')
                ->label('Event ID')
                ->numeric()
                ->visible(fn ($get) => $get('eventSource') === 'manual'),

            Select::make('layout')
                ->label('Layout')
                ->options([
                    'left' => 'Image Left',
                    'right' => 'Image Right',
                    'overlay' => 'Text Overlay',
                    'full' => 'Full Width',
                ])
                ->default('left'),

            Select::make('imageSize')
                ->label('Image Size')
                ->options([
                    'small' => 'Small (40%)',
                    'medium' => 'Medium (50%)',
                    'large' => 'Large (60%)',
                ])
                ->default('medium'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('badge')
                ->label('Badge Text (optional)')
                ->placeholder('e.g., Featured, New, Hot')
                ->maxLength(50),

            TextInput::make('ctaText')
                ->label('Button Text')
                ->default('Get Tickets')
                ->maxLength(50),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'eventSource' => 'auto',
            'eventId' => null,
            'layout' => 'left',
            'imageSize' => 'medium',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'badge' => 'Featured',
                'ctaText' => 'Get Tickets',
            ],
            'ro' => [
                'badge' => 'Recomandat',
                'ctaText' => 'Cumpără Bilete',
            ],
        ];
    }
}
