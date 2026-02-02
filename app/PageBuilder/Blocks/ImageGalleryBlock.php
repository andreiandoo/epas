<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ImageGalleryBlock extends BaseBlock
{
    public static string $type = 'image-gallery';
    public static string $name = 'Image Gallery';
    public static string $description = 'Photo gallery with lightbox support';
    public static string $icon = 'heroicon-o-squares-2x2';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('columns')
                ->label('Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                    4 => '4 Columns',
                    5 => '5 Columns',
                ])
                ->default(4),

            Select::make('style')
                ->label('Gallery Style')
                ->options([
                    'grid' => 'Uniform Grid',
                    'masonry' => 'Masonry',
                    'featured' => 'Featured First',
                ])
                ->default('grid'),

            Select::make('gap')
                ->label('Gap Between Images')
                ->options([
                    'none' => 'None',
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ])
                ->default('md'),

            Select::make('borderRadius')
                ->label('Image Border Radius')
                ->options([
                    'none' => 'None',
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ])
                ->default('md'),

            Toggle::make('lightbox')
                ->label('Enable Lightbox')
                ->default(true),

            Toggle::make('showCaptions')
                ->label('Show Captions on Hover')
                ->default(true),

            Toggle::make('lazyLoad')
                ->label('Lazy Load Images')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Gallery Title')
                ->maxLength(200),

            Repeater::make('images')
                ->label('Images')
                ->schema([
                    FileUpload::make('src')
                        ->label('Image')
                        ->image()
                        ->required()
                        ->directory('page-builder/gallery')
                        ->disk('public'),

                    TextInput::make('alt')
                        ->label('Alt Text')
                        ->maxLength(200),

                    TextInput::make('caption')
                        ->label('Caption')
                        ->maxLength(200),
                ])
                ->defaultItems(4)
                ->minItems(1)
                ->collapsible()
                ->grid(2)
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 4,
            'style' => 'grid',
            'gap' => 'md',
            'borderRadius' => 'md',
            'lightbox' => true,
            'showCaptions' => true,
            'lazyLoad' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'images' => [],
            ],
            'ro' => [
                'title' => '',
                'images' => [],
            ],
        ];
    }
}
