<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class EventGridBlock extends BaseBlock
{
    public static string $type = 'event-grid';
    public static string $name = 'Event Grid';
    public static string $description = 'Display events in a responsive grid layout';
    public static string $icon = 'heroicon-o-squares-2x2';
    public static string $category = 'events';

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

            Select::make('source')
                ->label('Event Source')
                ->options([
                    'upcoming' => 'Upcoming Events',
                    'featured' => 'Featured Events',
                    'all' => 'All Events',
                    'category' => 'By Category',
                ])
                ->default('upcoming')
                ->live(),

            Select::make('category')
                ->label('Category')
                ->options([]) // Will be populated dynamically
                ->searchable()
                ->visible(fn ($get) => $get('source') === 'category'),

            Select::make('limit')
                ->label('Number of Events')
                ->options([
                    3 => '3 Events',
                    6 => '6 Events',
                    9 => '9 Events',
                    12 => '12 Events',
                ])
                ->default(6),

            Toggle::make('showPagination')
                ->label('Show Pagination')
                ->default(false),

            Select::make('cardStyle')
                ->label('Card Style')
                ->options([
                    'default' => 'Default',
                    'compact' => 'Compact',
                    'large' => 'Large Image',
                ])
                ->default('default'),
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
            'columns' => 3,
            'source' => 'upcoming',
            'category' => null,
            'limit' => 6,
            'showPagination' => false,
            'cardStyle' => 'default',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Upcoming Events',
                'subtitle' => '',
            ],
            'ro' => [
                'title' => 'Evenimente Viitoare',
                'subtitle' => '',
            ],
        ];
    }
}
