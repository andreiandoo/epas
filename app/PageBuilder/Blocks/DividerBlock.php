<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Select;

class DividerBlock extends BaseBlock
{
    public static string $type = 'divider';
    public static string $name = 'Divider';
    public static string $description = 'Horizontal line divider';
    public static string $icon = 'heroicon-o-minus';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Style')
                ->options([
                    'solid' => 'Solid Line',
                    'dashed' => 'Dashed Line',
                    'dotted' => 'Dotted Line',
                    'gradient' => 'Gradient',
                ])
                ->default('solid'),

            Select::make('width')
                ->label('Width')
                ->options([
                    'full' => 'Full Width',
                    'large' => 'Large (75%)',
                    'medium' => 'Medium (50%)',
                    'small' => 'Small (25%)',
                ])
                ->default('full'),

            Select::make('thickness')
                ->label('Thickness')
                ->options([
                    'thin' => 'Thin (1px)',
                    'normal' => 'Normal (2px)',
                    'thick' => 'Thick (4px)',
                ])
                ->default('thin'),

            Select::make('color')
                ->label('Color')
                ->options([
                    'border' => 'Border Color (Default)',
                    'primary' => 'Primary Color',
                    'muted' => 'Muted',
                ])
                ->default('border'),

            Select::make('spacing')
                ->label('Vertical Spacing')
                ->options([
                    'none' => 'None',
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                ])
                ->default('medium'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'solid',
            'width' => 'full',
            'thickness' => 'thin',
            'color' => 'border',
            'spacing' => 'medium',
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
