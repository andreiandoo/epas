<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;

class TestimonialsBlock extends BaseBlock
{
    public static string $type = 'testimonials';
    public static string $name = 'Testimonials';
    public static string $description = 'Customer reviews and testimonials';
    public static string $icon = 'heroicon-o-chat-bubble-left-right';
    public static string $category = 'social-proof';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('layout')
                ->label('Layout')
                ->options([
                    'carousel' => 'Carousel',
                    'grid' => 'Grid',
                    'single' => 'Single Featured',
                ])
                ->default('carousel'),

            Select::make('columns')
                ->label('Grid Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                ])
                ->default(3)
                ->visible(fn ($get) => $get('layout') === 'grid'),

            Toggle::make('autoplay')
                ->label('Autoplay Carousel')
                ->default(true)
                ->visible(fn ($get) => $get('layout') === 'carousel'),

            Toggle::make('showRating')
                ->label('Show Star Rating')
                ->default(true),

            Toggle::make('showAvatar')
                ->label('Show Avatars')
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

            Repeater::make('testimonials')
                ->label('Testimonials')
                ->schema([
                    TextInput::make('name')
                        ->label('Customer Name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('role')
                        ->label('Role/Title')
                        ->maxLength(100),

                    Textarea::make('quote')
                        ->label('Testimonial')
                        ->required()
                        ->rows(3)
                        ->maxLength(500),

                    Select::make('rating')
                        ->label('Rating')
                        ->options([
                            5 => '5 Stars',
                            4 => '4 Stars',
                            3 => '3 Stars',
                        ])
                        ->default(5),

                    FileUpload::make('avatar')
                        ->label('Avatar')
                        ->image()
                        ->avatar()
                        ->directory('testimonials')
                        ->disk('public'),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'layout' => 'carousel',
            'columns' => 3,
            'autoplay' => true,
            'showRating' => true,
            'showAvatar' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'What Our Customers Say',
                'subtitle' => '',
                'testimonials' => [
                    [
                        'name' => 'John Doe',
                        'role' => 'Event Attendee',
                        'quote' => 'Amazing experience! The booking process was so smooth.',
                        'rating' => 5,
                        'avatar' => null,
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Ce Spun Clienții Noștri',
                'subtitle' => '',
                'testimonials' => [
                    [
                        'name' => 'Ion Popescu',
                        'role' => 'Participant la Eveniment',
                        'quote' => 'O experiență extraordinară! Procesul de rezervare a fost foarte simplu.',
                        'rating' => 5,
                        'avatar' => null,
                    ],
                ],
            ],
        ];
    }
}
