<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ButtonBlock extends BaseBlock
{
    public static string $type = 'button';
    public static string $name = 'Button';
    public static string $description = 'Standalone call-to-action button';
    public static string $icon = 'heroicon-o-cursor-arrow-rays';
    public static string $category = 'navigation';

    public static function getSettingsSchema(): array
    {
        return [
            TextInput::make('url')
                ->label('Link URL')
                ->required()
                ->url()
                ->maxLength(500),

            Select::make('style')
                ->label('Button Style')
                ->options([
                    'primary' => 'Primary (Filled)',
                    'secondary' => 'Secondary',
                    'outline' => 'Outline',
                    'ghost' => 'Ghost (Text Only)',
                ])
                ->default('primary'),

            Select::make('size')
                ->label('Size')
                ->options([
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                    'xl' => 'Extra Large',
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

            Select::make('icon')
                ->label('Icon')
                ->options([
                    '' => 'None',
                    'arrow-right' => 'Arrow Right',
                    'arrow-down' => 'Arrow Down',
                    'ticket' => 'Ticket',
                    'calendar' => 'Calendar',
                    'heart' => 'Heart',
                    'play' => 'Play',
                    'external-link' => 'External Link',
                ])
                ->default(''),

            Select::make('iconPosition')
                ->label('Icon Position')
                ->options([
                    'left' => 'Left',
                    'right' => 'Right',
                ])
                ->default('right')
                ->visible(fn ($get) => !empty($get('icon'))),

            Toggle::make('fullWidth')
                ->label('Full Width')
                ->default(false),

            Toggle::make('openInNewTab')
                ->label('Open in New Tab')
                ->default(false),

            Toggle::make('animated')
                ->label('Hover Animation')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('text')
                ->label('Button Text')
                ->required()
                ->maxLength(100),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'url' => '#',
            'style' => 'primary',
            'size' => 'md',
            'alignment' => 'center',
            'icon' => 'arrow-right',
            'iconPosition' => 'right',
            'fullWidth' => false,
            'openInNewTab' => false,
            'animated' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'text' => 'Get Tickets',
            ],
            'ro' => [
                'text' => 'Cumpără Bilete',
            ],
        ];
    }
}
