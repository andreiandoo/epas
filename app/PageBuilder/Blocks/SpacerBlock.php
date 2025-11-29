<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;

class SpacerBlock extends BaseBlock
{
    public static string $type = 'spacer';
    public static string $name = 'Spacer';
    public static string $description = 'Add vertical spacing between sections';
    public static string $icon = 'heroicon-o-arrows-up-down';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('height')
                ->label('Height')
                ->options([
                    'xs' => 'Extra Small (16px)',
                    'sm' => 'Small (32px)',
                    'md' => 'Medium (48px)',
                    'lg' => 'Large (64px)',
                    'xl' => 'Extra Large (96px)',
                    '2xl' => '2X Large (128px)',
                ])
                ->default('md'),

            Select::make('mobileHeight')
                ->label('Mobile Height')
                ->options([
                    'same' => 'Same as Desktop',
                    'xs' => 'Extra Small (16px)',
                    'sm' => 'Small (32px)',
                    'md' => 'Medium (48px)',
                    'lg' => 'Large (64px)',
                ])
                ->default('same'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'height' => 'md',
            'mobileHeight' => 'same',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [],
            'ro' => [],
        ];
    }
}
