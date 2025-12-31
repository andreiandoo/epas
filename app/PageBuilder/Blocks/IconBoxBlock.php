<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class IconBoxBlock extends BaseBlock
{
    public static string $type = 'icon-box';
    public static string $name = 'Icon Boxes';
    public static string $description = 'Feature boxes with icons and descriptions';
    public static string $icon = 'heroicon-o-cube';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('columns')
                ->label('Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                    4 => '4 Columns',
                ])
                ->default(3),

            Select::make('style')
                ->label('Box Style')
                ->options([
                    'default' => 'Default',
                    'bordered' => 'Bordered',
                    'filled' => 'Filled Background',
                    'minimal' => 'Minimal',
                ])
                ->default('default'),

            Select::make('iconPosition')
                ->label('Icon Position')
                ->options([
                    'top' => 'Top',
                    'left' => 'Left',
                    'inline' => 'Inline with Title',
                ])
                ->default('top'),

            Select::make('iconSize')
                ->label('Icon Size')
                ->options([
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ])
                ->default('md'),

            Toggle::make('showLink')
                ->label('Show Links')
                ->default(false),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            TextInput::make('subtitle')
                ->label('Section Subtitle')
                ->maxLength(500),

            Repeater::make('boxes')
                ->label('Icon Boxes')
                ->schema([
                    TextInput::make('icon')
                        ->label('Icon (Heroicon name)')
                        ->required()
                        ->placeholder('e.g., heroicon-o-star')
                        ->maxLength(100),

                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(150),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(300),

                    TextInput::make('link')
                        ->label('Link URL (optional)')
                        ->url()
                        ->maxLength(500),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 3,
            'style' => 'default',
            'iconPosition' => 'top',
            'iconSize' => 'md',
            'showLink' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Why Choose Us',
                'subtitle' => '',
                'boxes' => [
                    [
                        'icon' => 'heroicon-o-bolt',
                        'title' => 'Fast & Easy',
                        'description' => 'Quick and simple booking process.',
                        'link' => '',
                    ],
                    [
                        'icon' => 'heroicon-o-shield-check',
                        'title' => 'Secure',
                        'description' => 'Your data is always protected.',
                        'link' => '',
                    ],
                    [
                        'icon' => 'heroicon-o-heart',
                        'title' => 'Trusted',
                        'description' => 'Loved by thousands of customers.',
                        'link' => '',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'De Ce Să Ne Alegi',
                'subtitle' => '',
                'boxes' => [
                    [
                        'icon' => 'heroicon-o-bolt',
                        'title' => 'Rapid & Ușor',
                        'description' => 'Proces de rezervare rapid și simplu.',
                        'link' => '',
                    ],
                    [
                        'icon' => 'heroicon-o-shield-check',
                        'title' => 'Securizat',
                        'description' => 'Datele tale sunt mereu protejate.',
                        'link' => '',
                    ],
                    [
                        'icon' => 'heroicon-o-heart',
                        'title' => 'De Încredere',
                        'description' => 'Îndrăgit de mii de clienți.',
                        'link' => '',
                    ],
                ],
            ],
        ];
    }
}
