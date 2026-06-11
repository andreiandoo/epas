<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ReviewsBlock extends BaseBlock
{
    public static string $type = 'reviews';
    public static string $name = 'Reviews';
    public static string $description = 'Display user reviews with ratings and verified badges';
    public static string $icon = 'heroicon-o-star';
    public static string $category = 'social-proof';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('layout')
                ->label('Layout')
                ->options([
                    'grid' => 'Grid',
                    'list' => 'List',
                    'carousel' => 'Carousel',
                    'masonry' => 'Masonry',
                ])
                ->default('grid'),

            Select::make('columns')
                ->label('Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                ])
                ->default(2)
                ->visible(fn ($get) => in_array($get('layout'), ['grid', 'masonry'])),

            Toggle::make('showRating')
                ->label('Show Star Ratings')
                ->default(true),

            Toggle::make('showDate')
                ->label('Show Review Date')
                ->default(true),

            Toggle::make('showVerifiedBadge')
                ->label('Show Verified Badge')
                ->default(true),

            Toggle::make('showSummary')
                ->label('Show Rating Summary')
                ->default(true)
                ->helperText('Show average rating at the top'),
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

            Repeater::make('reviews')
                ->label('Reviews')
                ->schema([
                    TextInput::make('author')
                        ->label('Reviewer Name')
                        ->required()
                        ->maxLength(100),

                    FileUpload::make('avatar')
                        ->label('Avatar')
                        ->image()
                        ->avatar()
                        ->directory('reviews')
                        ->disk('public'),

                    Select::make('rating')
                        ->label('Rating')
                        ->options([
                            5 => '5 Stars',
                            4 => '4 Stars',
                            3 => '3 Stars',
                            2 => '2 Stars',
                            1 => '1 Star',
                        ])
                        ->default(5)
                        ->required(),

                    TextInput::make('title')
                        ->label('Review Title')
                        ->maxLength(150),

                    Textarea::make('content')
                        ->label('Review Content')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),

                    TextInput::make('date')
                        ->label('Review Date')
                        ->placeholder('e.g., Dec 2024')
                        ->maxLength(50),

                    Toggle::make('verified')
                        ->label('Verified Purchase')
                        ->default(true),

                    TextInput::make('eventName')
                        ->label('Event/Product Name')
                        ->maxLength(150),
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
            'layout' => 'grid',
            'columns' => 2,
            'showRating' => true,
            'showDate' => true,
            'showVerifiedBadge' => true,
            'showSummary' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Customer Reviews',
                'subtitle' => 'See what our customers are saying',
                'reviews' => [
                    [
                        'author' => 'Michael Brown',
                        'avatar' => null,
                        'rating' => 5,
                        'title' => 'Amazing Experience!',
                        'content' => 'The booking process was incredibly smooth. Highly recommend this platform for anyone looking to attend events.',
                        'date' => 'Dec 2024',
                        'verified' => true,
                        'eventName' => 'Summer Music Festival',
                    ],
                    [
                        'author' => 'Emily Wilson',
                        'avatar' => null,
                        'rating' => 5,
                        'title' => 'Best Event Platform',
                        'content' => 'User-friendly interface and excellent customer support. Will definitely use again!',
                        'date' => 'Nov 2024',
                        'verified' => true,
                        'eventName' => 'Tech Conference 2024',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Recenzii Clienți',
                'subtitle' => 'Vezi ce spun clienții noștri',
                'reviews' => [
                    [
                        'author' => 'Mihai Popescu',
                        'avatar' => null,
                        'rating' => 5,
                        'title' => 'Experiență Extraordinară!',
                        'content' => 'Procesul de rezervare a fost incredibil de simplu. Recomand cu căldură această platformă.',
                        'date' => 'Dec 2024',
                        'verified' => true,
                        'eventName' => 'Festival de Muzică',
                    ],
                    [
                        'author' => 'Elena Ionescu',
                        'avatar' => null,
                        'rating' => 5,
                        'title' => 'Cea Mai Bună Platformă',
                        'content' => 'Interfață ușor de utilizat și suport excelent. Voi folosi cu siguranță din nou!',
                        'date' => 'Nov 2024',
                        'verified' => true,
                        'eventName' => 'Conferință Tech 2024',
                    ],
                ],
            ],
        ];
    }
}
