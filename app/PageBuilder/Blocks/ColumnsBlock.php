<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class ColumnsBlock extends BaseBlock
{
    public static string $type = 'columns';
    public static string $name = 'Columns Layout';
    public static string $description = 'Multi-column content layout';
    public static string $icon = 'heroicon-o-view-columns';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('columns')
                ->label('Number of Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                    4 => '4 Columns',
                ])
                ->default(2),

            Select::make('layout')
                ->label('Column Widths')
                ->options([
                    'equal' => 'Equal Width',
                    '2-1' => '2/3 + 1/3',
                    '1-2' => '1/3 + 2/3',
                    '1-1-2' => '1/4 + 1/4 + 1/2',
                    '2-1-1' => '1/2 + 1/4 + 1/4',
                ])
                ->default('equal')
                ->visible(fn ($get) => in_array($get('columns'), [2, 3])),

            Select::make('gap')
                ->label('Column Gap')
                ->options([
                    'none' => 'None',
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                    'xl' => 'Extra Large',
                ])
                ->default('md'),

            Select::make('verticalAlign')
                ->label('Vertical Alignment')
                ->options([
                    'top' => 'Top',
                    'center' => 'Center',
                    'bottom' => 'Bottom',
                    'stretch' => 'Stretch',
                ])
                ->default('top'),

            Toggle::make('reverseOnMobile')
                ->label('Reverse Order on Mobile')
                ->default(false),

            Toggle::make('stackOnMobile')
                ->label('Stack on Mobile')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Repeater::make('columns')
                ->label('Column Content')
                ->schema([
                    RichEditor::make('content')
                        ->label('Content')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'link',
                            'bulletList',
                            'orderedList',
                            'h2',
                            'h3',
                            'blockquote',
                        ])
                        ->columnSpanFull(),

                    Select::make('padding')
                        ->label('Inner Padding')
                        ->options([
                            'none' => 'None',
                            'sm' => 'Small',
                            'md' => 'Medium',
                            'lg' => 'Large',
                        ])
                        ->default('none'),

                    Select::make('background')
                        ->label('Background')
                        ->options([
                            'none' => 'None',
                            'light' => 'Light Gray',
                            'white' => 'White',
                            'primary-light' => 'Primary Light',
                        ])
                        ->default('none'),
                ])
                ->defaultItems(2)
                ->minItems(2)
                ->maxItems(4)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 2,
            'layout' => 'equal',
            'gap' => 'md',
            'verticalAlign' => 'top',
            'reverseOnMobile' => false,
            'stackOnMobile' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'columns' => [
                    [
                        'content' => '<h3>First Column</h3><p>Add your content here. This column supports rich text formatting.</p>',
                        'padding' => 'none',
                        'background' => 'none',
                    ],
                    [
                        'content' => '<h3>Second Column</h3><p>Add your content here. Use the formatting tools to style your text.</p>',
                        'padding' => 'none',
                        'background' => 'none',
                    ],
                ],
            ],
            'ro' => [
                'columns' => [
                    [
                        'content' => '<h3>Prima Coloană</h3><p>Adaugă conținutul tău aici. Această coloană suportă formatare text.</p>',
                        'padding' => 'none',
                        'background' => 'none',
                    ],
                    [
                        'content' => '<h3>A Doua Coloană</h3><p>Adaugă conținutul tău aici. Folosește uneltele de formatare pentru text.</p>',
                        'padding' => 'none',
                        'background' => 'none',
                    ],
                ],
            ],
        ];
    }
}
