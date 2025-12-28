<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TimelineBlock extends BaseBlock
{
    public static string $type = 'timeline';
    public static string $name = 'Timeline';
    public static string $description = 'Display events or milestones chronologically';
    public static string $icon = 'heroicon-o-clock';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Timeline Style')
                ->options([
                    'vertical' => 'Vertical',
                    'horizontal' => 'Horizontal',
                    'alternating' => 'Alternating (Left/Right)',
                ])
                ->default('vertical'),

            Select::make('markerStyle')
                ->label('Marker Style')
                ->options([
                    'dot' => 'Dot',
                    'icon' => 'Icon',
                    'number' => 'Number',
                    'date' => 'Date',
                ])
                ->default('dot'),

            Toggle::make('showConnector')
                ->label('Show Connector Line')
                ->default(true),

            Toggle::make('animated')
                ->label('Animate on Scroll')
                ->default(false),

            Select::make('alignment')
                ->label('Content Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                ])
                ->default('left')
                ->visible(fn ($get) => $get('style') !== 'alternating'),
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

            Repeater::make('items')
                ->label('Timeline Items')
                ->schema([
                    TextInput::make('date')
                        ->label('Date/Time')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(200),

                    RichEditor::make('description')
                        ->label('Description')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'link',
                            'bulletList',
                        ]),

                    TextInput::make('icon')
                        ->label('Icon (Heroicon name)')
                        ->placeholder('e.g., heroicon-o-star')
                        ->maxLength(100),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'completed' => 'Completed',
                            'current' => 'Current',
                            'upcoming' => 'Upcoming',
                        ])
                        ->default('completed'),
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
            'style' => 'vertical',
            'markerStyle' => 'dot',
            'showConnector' => true,
            'animated' => false,
            'alignment' => 'left',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Our Journey',
                'subtitle' => '',
                'items' => [
                    [
                        'date' => '2020',
                        'title' => 'Company Founded',
                        'description' => '<p>Started with a vision to transform the events industry.</p>',
                        'icon' => 'heroicon-o-rocket-launch',
                        'status' => 'completed',
                    ],
                    [
                        'date' => '2022',
                        'title' => 'Platform Launch',
                        'description' => '<p>Launched our first version to the public.</p>',
                        'icon' => 'heroicon-o-globe-alt',
                        'status' => 'completed',
                    ],
                    [
                        'date' => '2024',
                        'title' => 'Growing Together',
                        'description' => '<p>Serving thousands of events worldwide.</p>',
                        'icon' => 'heroicon-o-arrow-trending-up',
                        'status' => 'current',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Povestea Noastră',
                'subtitle' => '',
                'items' => [
                    [
                        'date' => '2020',
                        'title' => 'Înființarea Companiei',
                        'description' => '<p>Am început cu viziunea de a transforma industria evenimentelor.</p>',
                        'icon' => 'heroicon-o-rocket-launch',
                        'status' => 'completed',
                    ],
                    [
                        'date' => '2022',
                        'title' => 'Lansarea Platformei',
                        'description' => '<p>Am lansat prima versiune pentru public.</p>',
                        'icon' => 'heroicon-o-globe-alt',
                        'status' => 'completed',
                    ],
                    [
                        'date' => '2024',
                        'title' => 'Creștem Împreună',
                        'description' => '<p>Servim mii de evenimente la nivel mondial.</p>',
                        'icon' => 'heroicon-o-arrow-trending-up',
                        'status' => 'current',
                    ],
                ],
            ],
        ];
    }
}
