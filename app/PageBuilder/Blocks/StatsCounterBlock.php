<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class StatsCounterBlock extends BaseBlock
{
    public static string $type = 'stats-counter';
    public static string $name = 'Stats Counter';
    public static string $description = 'Display animated statistics and counters';
    public static string $icon = 'heroicon-o-chart-bar';
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
                ->default(4),

            Select::make('style')
                ->label('Style')
                ->options([
                    'simple' => 'Simple',
                    'card' => 'Cards',
                    'icon' => 'With Icons',
                    'bordered' => 'Bordered',
                ])
                ->default('simple'),

            Toggle::make('animateOnScroll')
                ->label('Animate on Scroll')
                ->default(true),

            Select::make('animationDuration')
                ->label('Animation Duration')
                ->options([
                    1000 => '1 second',
                    2000 => '2 seconds',
                    3000 => '3 seconds',
                ])
                ->default(2000)
                ->visible(fn ($get) => $get('animateOnScroll')),

            Toggle::make('showDividers')
                ->label('Show Dividers')
                ->default(false),

            Select::make('backgroundColor')
                ->label('Background')
                ->options([
                    'transparent' => 'Transparent',
                    'white' => 'White',
                    'gray' => 'Gray',
                    'primary' => 'Primary Color',
                ])
                ->default('transparent'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            Repeater::make('stats')
                ->label('Statistics')
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(20)
                        ->helperText('e.g., 1000, 50+, 99%'),

                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(50),

                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->maxLength(10)
                        ->helperText('e.g., $, €'),

                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(10)
                        ->helperText('e.g., +, %, K'),

                    Select::make('icon')
                        ->label('Icon')
                        ->options([
                            '' => 'None',
                            'users' => 'Users',
                            'ticket' => 'Ticket',
                            'calendar' => 'Calendar',
                            'star' => 'Star',
                            'heart' => 'Heart',
                            'globe' => 'Globe',
                            'chart' => 'Chart',
                        ])
                        ->default(''),
                ])
                ->defaultItems(4)
                ->minItems(2)
                ->maxItems(6)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 4,
            'style' => 'simple',
            'animateOnScroll' => true,
            'animationDuration' => 2000,
            'showDividers' => false,
            'backgroundColor' => 'transparent',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'stats' => [
                    ['value' => '10000', 'label' => 'Tickets Sold', 'prefix' => '', 'suffix' => '+', 'icon' => 'ticket'],
                    ['value' => '500', 'label' => 'Events', 'prefix' => '', 'suffix' => '+', 'icon' => 'calendar'],
                    ['value' => '50', 'label' => 'Venues', 'prefix' => '', 'suffix' => '', 'icon' => 'globe'],
                    ['value' => '99', 'label' => 'Happy Customers', 'prefix' => '', 'suffix' => '%', 'icon' => 'heart'],
                ],
            ],
            'ro' => [
                'title' => '',
                'stats' => [
                    ['value' => '10000', 'label' => 'Bilete Vândute', 'prefix' => '', 'suffix' => '+', 'icon' => 'ticket'],
                    ['value' => '500', 'label' => 'Evenimente', 'prefix' => '', 'suffix' => '+', 'icon' => 'calendar'],
                    ['value' => '50', 'label' => 'Locații', 'prefix' => '', 'suffix' => '', 'icon' => 'globe'],
                    ['value' => '99', 'label' => 'Clienți Mulțumiți', 'prefix' => '', 'suffix' => '%', 'icon' => 'heart'],
                ],
            ],
        ];
    }
}
