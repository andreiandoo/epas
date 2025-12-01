<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class HeadingBlock extends BaseBlock
{
    public static string $type = 'heading';
    public static string $name = 'Heading';
    public static string $description = 'Standalone heading with customizable styling';
    public static string $icon = 'heroicon-o-h1';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('level')
                ->label('Heading Level')
                ->options([
                    'h1' => 'H1 - Page Title',
                    'h2' => 'H2 - Section Title',
                    'h3' => 'H3 - Subsection',
                    'h4' => 'H4 - Minor Heading',
                ])
                ->default('h2'),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('left'),

            Select::make('size')
                ->label('Size')
                ->options([
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                    'xl' => 'Extra Large',
                ])
                ->default('md'),

            Toggle::make('showDivider')
                ->label('Show Divider Line')
                ->default(false),

            Select::make('dividerStyle')
                ->label('Divider Style')
                ->options([
                    'solid' => 'Solid Line',
                    'dashed' => 'Dashed',
                    'gradient' => 'Gradient',
                ])
                ->default('solid')
                ->visible(fn ($get) => $get('showDivider')),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('heading')
                ->label('Heading Text')
                ->required()
                ->maxLength(200),

            TextInput::make('subheading')
                ->label('Subheading (optional)')
                ->maxLength(300),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'level' => 'h2',
            'alignment' => 'left',
            'size' => 'md',
            'showDivider' => false,
            'dividerStyle' => 'solid',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'heading' => 'Section Heading',
                'subheading' => '',
            ],
            'ro' => [
                'heading' => 'Titlu Secțiune',
                'subheading' => '',
            ],
        ];
    }
}
