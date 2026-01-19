<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class CustomHtmlBlock extends BaseBlock
{
    public static string $type = 'custom-html';
    public static string $name = 'Custom HTML';
    public static string $description = 'Raw HTML content for advanced users';
    public static string $icon = 'heroicon-o-code-bracket';
    public static string $category = 'advanced';

    public static function getSettingsSchema(): array
    {
        return [
            Toggle::make('fullWidth')
                ->label('Full Width')
                ->helperText('Remove container constraints')
                ->default(false),

            Toggle::make('allowScripts')
                ->label('Allow Scripts')
                ->helperText('Enable JavaScript execution (use with caution)')
                ->default(false),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Textarea::make('html')
                ->label('HTML Code')
                ->rows(10)
                ->placeholder('<div class="my-custom-section">...</div>')
                ->helperText('Enter your custom HTML code. Use Tailwind CSS classes for styling.')
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'fullWidth' => false,
            'allowScripts' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'html' => '<!-- Your custom HTML here -->',
            ],
            'ro' => [
                'html' => '<!-- HTML-ul tău personalizat aici -->',
            ],
        ];
    }
}
