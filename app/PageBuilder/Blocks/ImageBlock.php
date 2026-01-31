<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ImageBlock extends BaseBlock
{
    public static string $type = 'image';
    public static string $name = 'Image';
    public static string $description = 'Single image with optional caption and link';
    public static string $icon = 'heroicon-o-photo';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            FileUpload::make('imageUrl')
                ->label('Image')
                ->image()
                ->required()
                ->directory('page-builder/images')
                ->disk('public')
                ->visibility('public')
                ->imageResizeMode('cover')
                ->imageCropAspectRatio(null)
                ->columnSpanFull(),

            Select::make('size')
                ->label('Size')
                ->options([
                    'small' => 'Small (400px)',
                    'medium' => 'Medium (600px)',
                    'large' => 'Large (800px)',
                    'full' => 'Full Width',
                ])
                ->default('medium'),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('center'),

            Select::make('borderRadius')
                ->label('Border Radius')
                ->options([
                    'none' => 'None',
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                    'full' => 'Full (Circle)',
                ])
                ->default('md'),

            Toggle::make('shadow')
                ->label('Add Shadow')
                ->default(true),

            Toggle::make('lightbox')
                ->label('Enable Lightbox (Click to Enlarge)')
                ->default(true),

            TextInput::make('linkUrl')
                ->label('Link URL (optional)')
                ->url()
                ->maxLength(500),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('alt')
                ->label('Alt Text')
                ->required()
                ->maxLength(200)
                ->helperText('Describe the image for accessibility'),

            TextInput::make('caption')
                ->label('Caption')
                ->maxLength(300),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'imageUrl' => null,
            'size' => 'medium',
            'alignment' => 'center',
            'borderRadius' => 'md',
            'shadow' => true,
            'lightbox' => true,
            'linkUrl' => null,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'alt' => '',
                'caption' => '',
            ],
            'ro' => [
                'alt' => '',
                'caption' => '',
            ],
        ];
    }
}
