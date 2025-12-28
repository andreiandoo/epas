<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class SliderBlock extends BaseBlock
{
    public static string $type = 'slider';
    public static string $name = 'Content Slider';
    public static string $description = 'Carousel/slider for images or content';
    public static string $icon = 'heroicon-o-arrows-right-left';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('height')
                ->label('Height')
                ->options([
                    'small' => 'Small (300px)',
                    'medium' => 'Medium (400px)',
                    'large' => 'Large (500px)',
                    'full' => 'Full Screen',
                ])
                ->default('medium'),

            Toggle::make('autoplay')
                ->label('Autoplay')
                ->default(true),

            Select::make('autoplaySpeed')
                ->label('Autoplay Speed')
                ->options([
                    3000 => '3 seconds',
                    5000 => '5 seconds',
                    7000 => '7 seconds',
                    10000 => '10 seconds',
                ])
                ->default(5000)
                ->visible(fn ($get) => $get('autoplay')),

            Toggle::make('showArrows')
                ->label('Show Navigation Arrows')
                ->default(true),

            Toggle::make('showDots')
                ->label('Show Pagination Dots')
                ->default(true),

            Toggle::make('loop')
                ->label('Infinite Loop')
                ->default(true),

            Toggle::make('pauseOnHover')
                ->label('Pause on Hover')
                ->default(true),

            Select::make('effect')
                ->label('Transition Effect')
                ->options([
                    'slide' => 'Slide',
                    'fade' => 'Fade',
                ])
                ->default('slide'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Repeater::make('slides')
                ->label('Slides')
                ->schema([
                    FileUpload::make('image')
                        ->label('Background Image')
                        ->image()
                        ->required()
                        ->directory('page-builder/slider')
                        ->disk('public')
                        ->columnSpanFull(),

                    TextInput::make('title')
                        ->label('Title')
                        ->maxLength(200),

                    TextInput::make('subtitle')
                        ->label('Subtitle')
                        ->maxLength(300),

                    TextInput::make('buttonText')
                        ->label('Button Text')
                        ->maxLength(50),

                    TextInput::make('buttonUrl')
                        ->label('Button URL')
                        ->url()
                        ->maxLength(500),

                    Select::make('overlayOpacity')
                        ->label('Overlay Opacity')
                        ->options([
                            0 => 'None',
                            0.3 => 'Light',
                            0.5 => 'Medium',
                            0.7 => 'Dark',
                        ])
                        ->default(0.5),

                    Select::make('contentPosition')
                        ->label('Content Position')
                        ->options([
                            'left' => 'Left',
                            'center' => 'Center',
                            'right' => 'Right',
                        ])
                        ->default('center'),
                ])
                ->defaultItems(3)
                ->minItems(1)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'height' => 'medium',
            'autoplay' => true,
            'autoplaySpeed' => 5000,
            'showArrows' => true,
            'showDots' => true,
            'loop' => true,
            'pauseOnHover' => true,
            'effect' => 'slide',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'slides' => [
                    [
                        'image' => null,
                        'title' => 'Welcome to Our Events',
                        'subtitle' => 'Discover amazing experiences',
                        'buttonText' => 'Explore Events',
                        'buttonUrl' => '/events',
                        'overlayOpacity' => 0.5,
                        'contentPosition' => 'center',
                    ],
                ],
            ],
            'ro' => [
                'slides' => [
                    [
                        'image' => null,
                        'title' => 'Bine ați venit la Evenimentele Noastre',
                        'subtitle' => 'Descoperă experiențe unice',
                        'buttonText' => 'Explorează Evenimente',
                        'buttonUrl' => '/events',
                        'overlayOpacity' => 0.5,
                        'contentPosition' => 'center',
                    ],
                ],
            ],
        ];
    }
}
