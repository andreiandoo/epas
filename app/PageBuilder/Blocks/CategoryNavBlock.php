<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CategoryNavBlock extends BaseBlock
{
    public static string $type = 'category-nav';
    public static string $name = 'Category Navigation';
    public static string $description = 'Display event categories as navigation tiles';
    public static string $icon = 'heroicon-o-tag';
    public static string $category = 'navigation';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Display Style')
                ->options([
                    'tiles' => 'Tiles with Icons',
                    'cards' => 'Cards with Images',
                    'pills' => 'Pills/Tags',
                    'list' => 'List',
                ])
                ->default('tiles'),

            Select::make('columns')
                ->label('Columns')
                ->options([
                    3 => '3 Columns',
                    4 => '4 Columns',
                    5 => '5 Columns',
                    6 => '6 Columns',
                ])
                ->default(4),

            Toggle::make('showCount')
                ->label('Show Event Count')
                ->default(true),

            Select::make('limit')
                ->label('Max Categories')
                ->options([
                    4 => '4 Categories',
                    6 => '6 Categories',
                    8 => '8 Categories',
                    12 => '12 Categories',
                ])
                ->default(6),

            Toggle::make('showAll')
                ->label('Show "View All" Link')
                ->default(true),
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
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'tiles',
            'columns' => 4,
            'showCount' => true,
            'limit' => 6,
            'showAll' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Browse by Category',
                'subtitle' => '',
            ],
            'ro' => [
                'title' => 'Răsfoiește pe Categorii',
                'subtitle' => '',
            ],
        ];
    }
}
