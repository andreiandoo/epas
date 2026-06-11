<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TableBlock extends BaseBlock
{
    public static string $type = 'table';
    public static string $name = 'Table';
    public static string $description = 'Display data in a table format';
    public static string $icon = 'heroicon-o-table-cells';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Table Style')
                ->options([
                    'default' => 'Default',
                    'striped' => 'Striped Rows',
                    'bordered' => 'Bordered',
                    'minimal' => 'Minimal',
                ])
                ->default('striped'),

            Toggle::make('showHeader')
                ->label('Show Header Row')
                ->default(true),

            Toggle::make('hoverable')
                ->label('Hoverable Rows')
                ->default(true),

            Toggle::make('responsive')
                ->label('Responsive (scroll on mobile)')
                ->default(true),

            Select::make('alignment')
                ->label('Text Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('left'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Table Title (optional)')
                ->maxLength(200),

            TextInput::make('caption')
                ->label('Table Caption (optional)')
                ->maxLength(300),

            Repeater::make('headers')
                ->label('Column Headers')
                ->simple(
                    TextInput::make('header')
                        ->required()
                        ->maxLength(100),
                )
                ->defaultItems(3)
                ->minItems(1)
                ->columnSpanFull(),

            Repeater::make('rows')
                ->label('Table Rows')
                ->schema([
                    Repeater::make('cells')
                        ->label('Cells')
                        ->simple(
                            TextInput::make('cell')
                                ->maxLength(500),
                        )
                        ->defaultItems(3)
                        ->minItems(1)
                        ->columnSpanFull(),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'striped',
            'showHeader' => true,
            'hoverable' => true,
            'responsive' => true,
            'alignment' => 'left',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'caption' => '',
                'headers' => ['Feature', 'Basic', 'Premium'],
                'rows' => [
                    ['cells' => ['Ticket Types', '2', 'Unlimited']],
                    ['cells' => ['Support', 'Email', '24/7 Priority']],
                    ['cells' => ['Analytics', 'Basic', 'Advanced']],
                ],
            ],
            'ro' => [
                'title' => '',
                'caption' => '',
                'headers' => ['Caracteristică', 'Basic', 'Premium'],
                'rows' => [
                    ['cells' => ['Tipuri de Bilete', '2', 'Nelimitat']],
                    ['cells' => ['Suport', 'Email', 'Prioritar 24/7']],
                    ['cells' => ['Analiză', 'De bază', 'Avansat']],
                ],
            ],
        ];
    }
}
