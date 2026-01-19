<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class EventListBlock extends BaseBlock
{
    public static string $type = 'event-list';
    public static string $name = 'Event List';
    public static string $description = 'Display events in a vertical list layout';
    public static string $icon = 'heroicon-o-list-bullet';
    public static string $category = 'events';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('source')
                ->label('Event Source')
                ->options([
                    'upcoming' => 'Upcoming Events',
                    'featured' => 'Featured Events',
                    'past' => 'Past Events',
                    'category' => 'By Category',
                ])
                ->default('upcoming')
                ->live(),

            Select::make('category')
                ->label('Category')
                ->options([]) // Populated dynamically
                ->searchable()
                ->visible(fn ($get) => $get('source') === 'category'),

            Select::make('limit')
                ->label('Number of Events')
                ->options([
                    3 => '3 Events',
                    5 => '5 Events',
                    10 => '10 Events',
                    15 => '15 Events',
                ])
                ->default(5),

            Select::make('style')
                ->label('List Style')
                ->options([
                    'default' => 'Default (Image + Details)',
                    'compact' => 'Compact (No Image)',
                    'timeline' => 'Timeline',
                ])
                ->default('default'),

            Toggle::make('showImage')
                ->label('Show Event Image')
                ->default(true),

            Toggle::make('showDate')
                ->label('Show Date')
                ->default(true),

            Toggle::make('showVenue')
                ->label('Show Venue')
                ->default(true),

            Toggle::make('showPrice')
                ->label('Show Price')
                ->default(true),

            Toggle::make('showDescription')
                ->label('Show Description')
                ->default(false),

            Toggle::make('showPagination')
                ->label('Show Pagination')
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

            TextInput::make('emptyText')
                ->label('Empty State Text')
                ->maxLength(200),

            TextInput::make('viewAllUrl')
                ->label('View All Link')
                ->url()
                ->maxLength(255),

            TextInput::make('viewAllText')
                ->label('View All Button Text')
                ->maxLength(50),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'source' => 'upcoming',
            'category' => null,
            'limit' => 5,
            'style' => 'default',
            'showImage' => true,
            'showDate' => true,
            'showVenue' => true,
            'showPrice' => true,
            'showDescription' => false,
            'showPagination' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Upcoming Events',
                'subtitle' => '',
                'emptyText' => 'No events found',
                'viewAllUrl' => '/events',
                'viewAllText' => 'View All Events',
            ],
            'ro' => [
                'title' => 'Evenimente Viitoare',
                'subtitle' => '',
                'emptyText' => 'Nu au fost găsite evenimente',
                'viewAllUrl' => '/events',
                'viewAllText' => 'Vezi Toate Evenimentele',
            ],
        ];
    }
}
