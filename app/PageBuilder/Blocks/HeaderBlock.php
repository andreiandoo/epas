<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class HeaderBlock extends BaseBlock
{
    public static string $type = 'header';
    public static string $name = 'Site Header';
    public static string $description = 'Website header with logo and navigation';
    public static string $icon = 'heroicon-o-bars-3-center-left';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Header Style')
                ->options([
                    'default' => 'Default',
                    'centered' => 'Centered Logo',
                    'minimal' => 'Minimal',
                    'transparent' => 'Transparent',
                ])
                ->default('default'),

            Toggle::make('sticky')
                ->label('Sticky Header')
                ->default(true),

            Toggle::make('showCta')
                ->label('Show CTA Button')
                ->default(true),

            Toggle::make('showSocialLinks')
                ->label('Show Social Links')
                ->default(false),

            Select::make('mobileMenu')
                ->label('Mobile Menu Style')
                ->options([
                    'hamburger' => 'Hamburger Menu',
                    'slide' => 'Slide-in Panel',
                    'fullscreen' => 'Fullscreen Overlay',
                ])
                ->default('hamburger'),

            Select::make('backgroundColor')
                ->label('Background')
                ->options([
                    'white' => 'White',
                    'dark' => 'Dark',
                    'transparent' => 'Transparent',
                    'primary' => 'Primary Color',
                ])
                ->default('white'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            FileUpload::make('logo')
                ->label('Logo')
                ->image()
                ->directory('header')
                ->disk('public'),

            FileUpload::make('logoDark')
                ->label('Logo (Dark/Alt)')
                ->image()
                ->directory('header')
                ->disk('public'),

            TextInput::make('logoAlt')
                ->label('Logo Alt Text')
                ->default('Logo')
                ->maxLength(100),

            Repeater::make('navigation')
                ->label('Navigation Links')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(50),

                    TextInput::make('url')
                        ->label('URL')
                        ->required()
                        ->maxLength(500),

                    Toggle::make('isExternal')
                        ->label('Open in New Tab')
                        ->default(false),

                    Repeater::make('children')
                        ->label('Dropdown Items')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(50),

                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(500),
                        ])
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),

            TextInput::make('ctaText')
                ->label('CTA Button Text')
                ->default('Get Started')
                ->maxLength(50),

            TextInput::make('ctaUrl')
                ->label('CTA Button URL')
                ->default('/contact')
                ->maxLength(500),

            TextInput::make('phone')
                ->label('Phone Number (optional)')
                ->tel()
                ->maxLength(30),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'default',
            'sticky' => true,
            'showCta' => true,
            'showSocialLinks' => false,
            'mobileMenu' => 'hamburger',
            'backgroundColor' => 'white',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'logo' => null,
                'logoDark' => null,
                'logoAlt' => 'Logo',
                'navigation' => [
                    ['label' => 'Home', 'url' => '/', 'isExternal' => false, 'children' => []],
                    ['label' => 'Events', 'url' => '/events', 'isExternal' => false, 'children' => []],
                    ['label' => 'About', 'url' => '/about', 'isExternal' => false, 'children' => []],
                    ['label' => 'Contact', 'url' => '/contact', 'isExternal' => false, 'children' => []],
                ],
                'ctaText' => 'Get Tickets',
                'ctaUrl' => '/events',
                'phone' => '',
            ],
            'ro' => [
                'logo' => null,
                'logoDark' => null,
                'logoAlt' => 'Logo',
                'navigation' => [
                    ['label' => 'Acasă', 'url' => '/', 'isExternal' => false, 'children' => []],
                    ['label' => 'Evenimente', 'url' => '/events', 'isExternal' => false, 'children' => []],
                    ['label' => 'Despre', 'url' => '/about', 'isExternal' => false, 'children' => []],
                    ['label' => 'Contact', 'url' => '/contact', 'isExternal' => false, 'children' => []],
                ],
                'ctaText' => 'Cumpără Bilete',
                'ctaUrl' => '/events',
                'phone' => '',
            ],
        ];
    }
}
