<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class AlertBannerBlock extends BaseBlock
{
    public static string $type = 'alert-banner';
    public static string $name = 'Alert Banner';
    public static string $description = 'Notification or announcement banner';
    public static string $icon = 'heroicon-o-megaphone';
    public static string $category = 'marketing';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('type')
                ->label('Alert Type')
                ->options([
                    'info' => 'Info (Blue)',
                    'success' => 'Success (Green)',
                    'warning' => 'Warning (Yellow)',
                    'error' => 'Error (Red)',
                    'promo' => 'Promo (Primary Color)',
                ])
                ->default('info'),

            Select::make('style')
                ->label('Style')
                ->options([
                    'banner' => 'Full Width Banner',
                    'rounded' => 'Rounded Container',
                    'minimal' => 'Minimal',
                ])
                ->default('banner'),

            Select::make('icon')
                ->label('Icon')
                ->options([
                    '' => 'Auto (Based on Type)',
                    'none' => 'No Icon',
                    'info' => 'Info',
                    'check' => 'Checkmark',
                    'warning' => 'Warning',
                    'bell' => 'Bell',
                    'star' => 'Star',
                    'gift' => 'Gift',
                    'ticket' => 'Ticket',
                ])
                ->default(''),

            Toggle::make('dismissible')
                ->label('Dismissible')
                ->default(true)
                ->helperText('Allow users to close the alert'),

            Toggle::make('showButton')
                ->label('Show Action Button')
                ->default(false)
                ->live(),

            TextInput::make('buttonUrl')
                ->label('Button URL')
                ->url()
                ->visible(fn ($get) => $get('showButton')),

            Toggle::make('sticky')
                ->label('Sticky (Fixed to Top)')
                ->default(false),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('message')
                ->label('Message')
                ->required()
                ->maxLength(300),

            TextInput::make('buttonText')
                ->label('Button Text')
                ->maxLength(50),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'type' => 'info',
            'style' => 'banner',
            'icon' => '',
            'dismissible' => true,
            'showButton' => false,
            'buttonUrl' => '',
            'sticky' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'message' => 'Check out our latest events!',
                'buttonText' => 'Learn More',
            ],
            'ro' => [
                'message' => 'Descoperă cele mai noi evenimente!',
                'buttonText' => 'Află Mai Multe',
            ],
        ];
    }
}
