<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class TextContentBlock extends BaseBlock
{
    public static string $type = 'text-content';
    public static string $name = 'Text Content';
    public static string $description = 'Rich text section for custom content';
    public static string $icon = 'heroicon-o-document-text';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('alignment')
                ->label('Text Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('left'),

            Select::make('maxWidth')
                ->label('Content Width')
                ->options([
                    'full' => 'Full Width',
                    'large' => 'Large (1024px)',
                    'medium' => 'Medium (768px)',
                    'small' => 'Small (640px)',
                ])
                ->default('large'),

            Select::make('padding')
                ->label('Vertical Padding')
                ->options([
                    'none' => 'None',
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                ])
                ->default('medium'),

            Select::make('background')
                ->label('Background')
                ->options([
                    'none' => 'None',
                    'light' => 'Light Gray',
                    'primary' => 'Primary Color (Light)',
                ])
                ->default('none'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title (optional)')
                ->maxLength(200),

            RichEditor::make('content')
                ->label('Content')
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'link',
                    'orderedList',
                    'bulletList',
                    'h2',
                    'h3',
                    'blockquote',
                ])
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'alignment' => 'left',
            'maxWidth' => 'large',
            'padding' => 'medium',
            'background' => 'none',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => '',
                'content' => '<p>Add your content here...</p>',
            ],
            'ro' => [
                'title' => '',
                'content' => '<p>Adaugă conținutul tău aici...</p>',
            ],
        ];
    }
}
