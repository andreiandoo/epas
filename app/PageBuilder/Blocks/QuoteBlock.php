<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class QuoteBlock extends BaseBlock
{
    public static string $type = 'quote';
    public static string $name = 'Quote / Blockquote';
    public static string $description = 'Display a prominent quote or testimonial';
    public static string $icon = 'heroicon-o-chat-bubble-bottom-center-text';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Quote Style')
                ->options([
                    'default' => 'Default',
                    'bordered' => 'Left Border',
                    'centered' => 'Centered',
                    'card' => 'Card Style',
                    'large' => 'Large Feature',
                ])
                ->default('bordered'),

            Toggle::make('showQuoteMarks')
                ->label('Show Quote Marks')
                ->default(true),

            Toggle::make('showAvatar')
                ->label('Show Author Avatar')
                ->default(false),

            Select::make('backgroundColor')
                ->label('Background')
                ->options([
                    'none' => 'None',
                    'light' => 'Light Gray',
                    'primary' => 'Primary Color',
                    'gradient' => 'Gradient',
                ])
                ->default('none'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Textarea::make('quote')
                ->label('Quote Text')
                ->required()
                ->rows(4)
                ->maxLength(1000),

            TextInput::make('author')
                ->label('Author Name')
                ->maxLength(100),

            TextInput::make('authorTitle')
                ->label('Author Title/Role')
                ->maxLength(150),

            FileUpload::make('avatar')
                ->label('Author Avatar')
                ->image()
                ->avatar()
                ->directory('quotes')
                ->disk('public'),

            TextInput::make('source')
                ->label('Source (optional)')
                ->placeholder('e.g., Company Name, Book Title')
                ->maxLength(200),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'bordered',
            'showQuoteMarks' => true,
            'showAvatar' => false,
            'backgroundColor' => 'none',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'quote' => 'This platform has transformed how we manage our events. The experience is seamless and professional.',
                'author' => 'Jane Smith',
                'authorTitle' => 'Event Manager',
                'avatar' => null,
                'source' => '',
            ],
            'ro' => [
                'quote' => 'Această platformă a transformat modul în care ne gestionăm evenimentele. Experiența este perfectă și profesională.',
                'author' => 'Maria Ionescu',
                'authorTitle' => 'Manager Evenimente',
                'avatar' => null,
                'source' => '',
            ],
        ];
    }
}
