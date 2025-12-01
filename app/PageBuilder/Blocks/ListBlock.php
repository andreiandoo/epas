<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ListBlock extends BaseBlock
{
    public static string $type = 'list';
    public static string $name = 'List';
    public static string $description = 'Bulleted, numbered, or icon lists';
    public static string $icon = 'heroicon-o-list-bullet';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('listType')
                ->label('List Type')
                ->options([
                    'bullet' => 'Bullet Points',
                    'numbered' => 'Numbered',
                    'check' => 'Checkmarks',
                    'icon' => 'Custom Icons',
                    'none' => 'No Marker',
                ])
                ->default('bullet'),

            Select::make('columns')
                ->label('Columns')
                ->options([
                    1 => '1 Column',
                    2 => '2 Columns',
                    3 => '3 Columns',
                ])
                ->default(1),

            Select::make('spacing')
                ->label('Item Spacing')
                ->options([
                    'compact' => 'Compact',
                    'normal' => 'Normal',
                    'relaxed' => 'Relaxed',
                ])
                ->default('normal'),

            Toggle::make('dividers')
                ->label('Show Dividers Between Items')
                ->default(false),

            TextInput::make('iconName')
                ->label('Custom Icon (Heroicon name)')
                ->placeholder('e.g., heroicon-o-check-circle')
                ->visible(fn ($get) => $get('listType') === 'icon'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('List Title (optional)')
                ->maxLength(200),

            Repeater::make('items')
                ->label('List Items')
                ->schema([
                    TextInput::make('text')
                        ->label('Item Text')
                        ->required()
                        ->maxLength(500),

                    TextInput::make('subtext')
                        ->label('Subtext (optional)')
                        ->maxLength(300),
                ])
                ->defaultItems(4)
                ->minItems(1)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'listType' => 'bullet',
            'columns' => 1,
            'spacing' => 'normal',
            'dividers' => false,
            'iconName' => 'heroicon-o-check-circle',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'items' => [
                    ['text' => 'Easy online ticket purchasing', 'subtext' => ''],
                    ['text' => 'Secure payment processing', 'subtext' => ''],
                    ['text' => 'Instant email confirmation', 'subtext' => ''],
                    ['text' => 'Mobile-friendly tickets', 'subtext' => ''],
                ],
            ],
            'ro' => [
                'title' => '',
                'items' => [
                    ['text' => 'Cumpărare ușoară de bilete online', 'subtext' => ''],
                    ['text' => 'Procesare securizată a plăților', 'subtext' => ''],
                    ['text' => 'Confirmare instantanee pe email', 'subtext' => ''],
                    ['text' => 'Bilete compatibile cu mobilul', 'subtext' => ''],
                ],
            ],
        ];
    }
}
