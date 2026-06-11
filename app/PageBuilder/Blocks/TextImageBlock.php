<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class TextImageBlock extends BaseBlock
{
    public static string $type = 'text-image';
    public static string $name = 'Text + Image';
    public static string $description = 'Side-by-side text and image section';
    public static string $icon = 'heroicon-o-rectangle-group';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('imagePosition')
                ->label('Image Position')
                ->options([
                    'left' => 'Left',
                    'right' => 'Right',
                ])
                ->default('right'),

            Select::make('imageSize')
                ->label('Image Size')
                ->options([
                    'small' => 'Small (33%)',
                    'medium' => 'Medium (50%)',
                    'large' => 'Large (60%)',
                ])
                ->default('medium'),

            FileUpload::make('image')
                ->label('Image')
                ->image()
                ->directory('page-builder')
                ->disk('public')
                ->visibility('public'),

            Select::make('imageStyle')
                ->label('Image Style')
                ->options([
                    'square' => 'Square',
                    'rounded' => 'Rounded Corners',
                    'circle' => 'Circle',
                ])
                ->default('rounded'),

            Select::make('verticalAlign')
                ->label('Vertical Alignment')
                ->options([
                    'top' => 'Top',
                    'center' => 'Center',
                    'bottom' => 'Bottom',
                ])
                ->default('center'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->maxLength(200),

            RichEditor::make('content')
                ->label('Content')
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'link',
                    'bulletList',
                    'orderedList',
                ])
                ->columnSpanFull(),

            TextInput::make('buttonText')
                ->label('Button Text (optional)')
                ->maxLength(50),

            TextInput::make('buttonUrl')
                ->label('Button URL')
                ->url()
                ->maxLength(255),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'imagePosition' => 'right',
            'imageSize' => 'medium',
            'image' => null,
            'imageStyle' => 'rounded',
            'verticalAlign' => 'center',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'About Us',
                'content' => '<p>Tell your story here...</p>',
                'buttonText' => '',
                'buttonUrl' => '',
            ],
            'ro' => [
                'title' => 'Despre Noi',
                'content' => '<p>Spune-ți povestea aici...</p>',
                'buttonText' => '',
                'buttonUrl' => '',
            ],
        ];
    }
}
