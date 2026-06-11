<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class PartnersBlock extends BaseBlock
{
    public static string $type = 'partners';
    public static string $name = 'Partners / Sponsors';
    public static string $description = 'Logo carousel or grid for partners and sponsors';
    public static string $icon = 'heroicon-o-building-office-2';
    public static string $category = 'social-proof';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('layout')
                ->label('Layout')
                ->options([
                    'carousel' => 'Carousel',
                    'grid' => 'Grid',
                ])
                ->default('carousel'),

            Select::make('speed')
                ->label('Carousel Speed')
                ->options([
                    'slow' => 'Slow',
                    'normal' => 'Normal',
                    'fast' => 'Fast',
                ])
                ->default('normal')
                ->visible(fn ($get) => $get('layout') === 'carousel'),

            Select::make('logoSize')
                ->label('Logo Size')
                ->options([
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                ])
                ->default('medium'),

            Toggle::make('grayscale')
                ->label('Grayscale Logos')
                ->helperText('Show logos in grayscale, color on hover')
                ->default(true),

            Select::make('columns')
                ->label('Grid Columns')
                ->options([
                    4 => '4 Columns',
                    5 => '5 Columns',
                    6 => '6 Columns',
                ])
                ->default(5)
                ->visible(fn ($get) => $get('layout') === 'grid'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            Repeater::make('partners')
                ->label('Partners')
                ->schema([
                    TextInput::make('name')
                        ->label('Partner Name')
                        ->required()
                        ->maxLength(100),

                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->directory('partners')
                        ->disk('public')
                        ->required(),

                    TextInput::make('url')
                        ->label('Website URL')
                        ->url()
                        ->maxLength(255),
                ])
                ->defaultItems(4)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'layout' => 'carousel',
            'speed' => 'normal',
            'logoSize' => 'medium',
            'grayscale' => true,
            'columns' => 5,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Our Partners',
                'partners' => [],
            ],
            'ro' => [
                'title' => 'Partenerii Noștri',
                'partners' => [],
            ],
        ];
    }
}
