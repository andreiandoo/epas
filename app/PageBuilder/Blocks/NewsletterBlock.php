<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class NewsletterBlock extends BaseBlock
{
    public static string $type = 'newsletter';
    public static string $name = 'Newsletter Signup';
    public static string $description = 'Email subscription form';
    public static string $icon = 'heroicon-o-envelope';
    public static string $category = 'marketing';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Form Style')
                ->options([
                    'inline' => 'Inline (Email + Button)',
                    'stacked' => 'Stacked',
                    'card' => 'Card with Background',
                ])
                ->default('inline'),

            Select::make('background')
                ->label('Background')
                ->options([
                    'none' => 'None',
                    'light' => 'Light Gray',
                    'primary' => 'Primary Color',
                    'dark' => 'Dark',
                ])
                ->default('light'),

            Toggle::make('showNameField')
                ->label('Include Name Field')
                ->default(false),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                ])
                ->default('center'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->maxLength(200),

            TextInput::make('subtitle')
                ->label('Subtitle')
                ->maxLength(500),

            TextInput::make('buttonText')
                ->label('Button Text')
                ->default('Subscribe')
                ->maxLength(50),

            TextInput::make('placeholder')
                ->label('Email Placeholder')
                ->default('Enter your email')
                ->maxLength(100),

            TextInput::make('successMessage')
                ->label('Success Message')
                ->default('Thank you for subscribing!')
                ->maxLength(200),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'inline',
            'background' => 'light',
            'showNameField' => false,
            'alignment' => 'center',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Stay Updated',
                'subtitle' => 'Get notified about new events and exclusive offers',
                'buttonText' => 'Subscribe',
                'placeholder' => 'Enter your email',
                'successMessage' => 'Thank you for subscribing!',
            ],
            'ro' => [
                'title' => 'Rămâi la Curent',
                'subtitle' => 'Primește notificări despre evenimente noi și oferte exclusive',
                'buttonText' => 'Abonează-te',
                'placeholder' => 'Introdu adresa de email',
                'successMessage' => 'Mulțumim pentru abonare!',
            ],
        ];
    }
}
