<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class BreadcrumbBlock extends BaseBlock
{
    public static string $type = 'breadcrumb';
    public static string $name = 'Breadcrumb Navigation';
    public static string $description = 'Navigation breadcrumb trail';
    public static string $icon = 'heroicon-o-chevron-double-right';
    public static string $category = 'navigation';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('separator')
                ->label('Separator Style')
                ->options([
                    'chevron' => 'Chevron (>)',
                    'slash' => 'Slash (/)',
                    'arrow' => 'Arrow (→)',
                    'dot' => 'Dot (•)',
                ])
                ->default('chevron'),

            Select::make('style')
                ->label('Visual Style')
                ->options([
                    'default' => 'Default',
                    'pills' => 'Pills',
                    'underline' => 'Underline Links',
                ])
                ->default('default'),

            Toggle::make('showHomeIcon')
                ->label('Show Home Icon')
                ->default(true),

            Toggle::make('showCurrentPage')
                ->label('Show Current Page')
                ->default(true),

            Select::make('alignment')
                ->label('Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                ])
                ->default('left'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Repeater::make('items')
                ->label('Breadcrumb Items')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('url')
                        ->label('URL')
                        ->maxLength(500)
                        ->helperText('Leave empty for current page'),

                    Toggle::make('isCurrentPage')
                        ->label('Is Current Page')
                        ->default(false),
                ])
                ->defaultItems(3)
                ->minItems(1)
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'separator' => 'chevron',
            'style' => 'default',
            'showHomeIcon' => true,
            'showCurrentPage' => true,
            'alignment' => 'left',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'items' => [
                    ['label' => 'Home', 'url' => '/', 'isCurrentPage' => false],
                    ['label' => 'Events', 'url' => '/events', 'isCurrentPage' => false],
                    ['label' => 'Current Event', 'url' => '', 'isCurrentPage' => true],
                ],
            ],
            'ro' => [
                'items' => [
                    ['label' => 'Acasă', 'url' => '/', 'isCurrentPage' => false],
                    ['label' => 'Evenimente', 'url' => '/events', 'isCurrentPage' => false],
                    ['label' => 'Eveniment Curent', 'url' => '', 'isCurrentPage' => true],
                ],
            ],
        ];
    }
}
