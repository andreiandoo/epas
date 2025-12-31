<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class LogoBlock extends BaseBlock
{
    public static string $type = 'logo';
    public static string $name = 'Logo';
    public static string $description = 'Display a logo with optional link';
    public static string $icon = 'heroicon-o-photo';
    public static string $category = 'navigation';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('size')
                ->label('Logo Size')
                ->options([
                    'xs' => 'Extra Small (24px)',
                    'sm' => 'Small (32px)',
                    'md' => 'Medium (48px)',
                    'lg' => 'Large (64px)',
                    'xl' => 'Extra Large (96px)',
                    'custom' => 'Custom',
                ])
                ->default('md'),

            TextInput::make('customHeight')
                ->label('Custom Height (px)')
                ->numeric()
                ->minValue(16)
                ->maxValue(200)
                ->visible(fn ($get) => $get('size') === 'custom'),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->default('left'),

            Toggle::make('linkToHome')
                ->label('Link to Homepage')
                ->default(true),

            Toggle::make('showInDarkMode')
                ->label('Has Dark Mode Version')
                ->default(false)
                ->helperText('Upload a separate logo for dark mode'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            FileUpload::make('logo')
                ->label('Logo Image')
                ->image()
                ->directory('logos')
                ->disk('public'),

            FileUpload::make('logoDark')
                ->label('Dark Mode Logo')
                ->image()
                ->directory('logos')
                ->disk('public'),

            TextInput::make('altText')
                ->label('Alt Text')
                ->maxLength(200),

            TextInput::make('link')
                ->label('Custom Link URL')
                ->url()
                ->maxLength(500)
                ->helperText('Leave empty to link to homepage'),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'size' => 'md',
            'customHeight' => 48,
            'alignment' => 'left',
            'linkToHome' => true,
            'showInDarkMode' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'logo' => null,
                'logoDark' => null,
                'altText' => 'Company Logo',
                'link' => '',
            ],
            'ro' => [
                'logo' => null,
                'logoDark' => null,
                'altText' => 'Logo Companie',
                'link' => '',
            ],
        ];
    }
}
