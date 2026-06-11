<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class MenuBlock extends BaseBlock
{
    public static string $type = 'menu';
    public static string $name = 'Navigation Menu';
    public static string $description = 'Standalone navigation menu with dropdowns';
    public static string $icon = 'heroicon-o-bars-3';
    public static string $category = 'navigation';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Menu Style')
                ->options([
                    'horizontal' => 'Horizontal',
                    'vertical' => 'Vertical',
                    'pills' => 'Pills',
                    'underline' => 'Underline',
                ])
                ->default('horizontal'),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                    'justified' => 'Justified',
                ])
                ->default('left'),

            Toggle::make('showIcons')
                ->label('Show Icons')
                ->default(false),

            Toggle::make('collapsible')
                ->label('Collapsible on Mobile')
                ->default(true),

            Select::make('dropdownStyle')
                ->label('Dropdown Style')
                ->options([
                    'default' => 'Default',
                    'mega' => 'Mega Menu',
                ])
                ->default('default'),

            Select::make('spacing')
                ->label('Item Spacing')
                ->options([
                    'compact' => 'Compact',
                    'normal' => 'Normal',
                    'relaxed' => 'Relaxed',
                ])
                ->default('normal'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Repeater::make('items')
                ->label('Menu Items')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('url')
                        ->label('URL')
                        ->maxLength(500),

                    TextInput::make('icon')
                        ->label('Icon (Heroicon name)')
                        ->placeholder('e.g., heroicon-o-home')
                        ->maxLength(100),

                    Toggle::make('isExternal')
                        ->label('Open in New Tab')
                        ->default(false),

                    Toggle::make('highlighted')
                        ->label('Highlight Item')
                        ->default(false),

                    Repeater::make('children')
                        ->label('Submenu Items')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(100),

                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(500),

                            TextInput::make('description')
                                ->label('Description (for mega menu)')
                                ->maxLength(200),
                        ])
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'horizontal',
            'alignment' => 'left',
            'showIcons' => false,
            'collapsible' => true,
            'dropdownStyle' => 'default',
            'spacing' => 'normal',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'items' => [
                    [
                        'label' => 'Home',
                        'url' => '/',
                        'icon' => 'heroicon-o-home',
                        'isExternal' => false,
                        'highlighted' => false,
                        'children' => [],
                    ],
                    [
                        'label' => 'Events',
                        'url' => '/events',
                        'icon' => 'heroicon-o-calendar',
                        'isExternal' => false,
                        'highlighted' => false,
                        'children' => [
                            ['label' => 'Upcoming Events', 'url' => '/events/upcoming', 'description' => ''],
                            ['label' => 'Past Events', 'url' => '/events/past', 'description' => ''],
                            ['label' => 'Categories', 'url' => '/events/categories', 'description' => ''],
                        ],
                    ],
                    [
                        'label' => 'About',
                        'url' => '/about',
                        'icon' => 'heroicon-o-information-circle',
                        'isExternal' => false,
                        'highlighted' => false,
                        'children' => [],
                    ],
                    [
                        'label' => 'Contact',
                        'url' => '/contact',
                        'icon' => 'heroicon-o-envelope',
                        'isExternal' => false,
                        'highlighted' => true,
                        'children' => [],
                    ],
                ],
            ],
            'ro' => [
                'items' => [
                    [
                        'label' => 'Acasă',
                        'url' => '/',
                        'icon' => 'heroicon-o-home',
                        'isExternal' => false,
                        'highlighted' => false,
                        'children' => [],
                    ],
                    [
                        'label' => 'Evenimente',
                        'url' => '/events',
                        'icon' => 'heroicon-o-calendar',
                        'isExternal' => false,
                        'highlighted' => false,
                        'children' => [
                            ['label' => 'Evenimente Viitoare', 'url' => '/events/upcoming', 'description' => ''],
                            ['label' => 'Evenimente Trecute', 'url' => '/events/past', 'description' => ''],
                            ['label' => 'Categorii', 'url' => '/events/categories', 'description' => ''],
                        ],
                    ],
                    [
                        'label' => 'Despre',
                        'url' => '/about',
                        'icon' => 'heroicon-o-information-circle',
                        'isExternal' => false,
                        'highlighted' => false,
                        'children' => [],
                    ],
                    [
                        'label' => 'Contact',
                        'url' => '/contact',
                        'icon' => 'heroicon-o-envelope',
                        'isExternal' => false,
                        'highlighted' => true,
                        'children' => [],
                    ],
                ],
            ],
        ];
    }
}
