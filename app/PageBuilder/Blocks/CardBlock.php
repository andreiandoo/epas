<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CardBlock extends BaseBlock
{
    public static string $type = 'card';
    public static string $name = 'Cards';
    public static string $description = 'Display content in card format with images and links';
    public static string $icon = 'heroicon-o-rectangle-group';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('columns')
                ->label('Columns')
                ->options([
                    1 => '1 Column',
                    2 => '2 Columns',
                    3 => '3 Columns',
                    4 => '4 Columns',
                ])
                ->default(3),

            Select::make('style')
                ->label('Card Style')
                ->options([
                    'default' => 'Default',
                    'bordered' => 'Bordered',
                    'shadow' => 'Shadow',
                    'minimal' => 'Minimal',
                ])
                ->default('shadow'),

            Toggle::make('showImage')
                ->label('Show Images')
                ->default(true),

            Toggle::make('equalHeight')
                ->label('Equal Height Cards')
                ->default(true),

            Select::make('imagePosition')
                ->label('Image Position')
                ->options([
                    'top' => 'Top',
                    'left' => 'Left',
                    'right' => 'Right',
                ])
                ->default('top'),
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

            Repeater::make('cards')
                ->label('Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Card Title')
                        ->required()
                        ->maxLength(200),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(500),

                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('cards')
                        ->disk('public'),

                    TextInput::make('link')
                        ->label('Link URL')
                        ->url()
                        ->maxLength(500),

                    TextInput::make('linkText')
                        ->label('Link Text')
                        ->default('Learn More')
                        ->maxLength(100),
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
            'columns' => 3,
            'style' => 'shadow',
            'showImage' => true,
            'equalHeight' => true,
            'imagePosition' => 'top',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Our Features',
                'subtitle' => 'Discover what makes us special',
                'cards' => [
                    [
                        'title' => 'Easy Booking',
                        'description' => 'Book your tickets in just a few clicks with our intuitive interface.',
                        'image' => null,
                        'link' => '#',
                        'linkText' => 'Learn More',
                    ],
                    [
                        'title' => 'Secure Payments',
                        'description' => 'Your transactions are protected with industry-standard security.',
                        'image' => null,
                        'link' => '#',
                        'linkText' => 'Learn More',
                    ],
                    [
                        'title' => '24/7 Support',
                        'description' => 'Our team is always here to help you with any questions.',
                        'image' => null,
                        'link' => '#',
                        'linkText' => 'Learn More',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Caracteristicile Noastre',
                'subtitle' => 'Descoperă ce ne face speciali',
                'cards' => [
                    [
                        'title' => 'Rezervare Ușoară',
                        'description' => 'Rezervă biletele în doar câteva clickuri cu interfața noastră intuitivă.',
                        'image' => null,
                        'link' => '#',
                        'linkText' => 'Află Mai Multe',
                    ],
                    [
                        'title' => 'Plăți Securizate',
                        'description' => 'Tranzacțiile tale sunt protejate cu securitate de nivel industrial.',
                        'image' => null,
                        'link' => '#',
                        'linkText' => 'Află Mai Multe',
                    ],
                    [
                        'title' => 'Suport 24/7',
                        'description' => 'Echipa noastră este mereu aici să te ajute cu orice întrebări.',
                        'image' => null,
                        'link' => '#',
                        'linkText' => 'Află Mai Multe',
                    ],
                ],
            ],
        ];
    }
}
