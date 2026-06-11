<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TabsBlock extends BaseBlock
{
    public static string $type = 'tabs';
    public static string $name = 'Tabs';
    public static string $description = 'Tabbed content sections for organized information';
    public static string $icon = 'heroicon-o-rectangle-stack';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Tab Style')
                ->options([
                    'default' => 'Default',
                    'pills' => 'Pills',
                    'underline' => 'Underline',
                    'boxed' => 'Boxed',
                ])
                ->default('default'),

            Select::make('alignment')
                ->label('Tab Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                    'full' => 'Full Width',
                ])
                ->default('left'),

            Toggle::make('vertical')
                ->label('Vertical Layout')
                ->default(false)
                ->helperText('Display tabs vertically on larger screens'),

            Toggle::make('showIcon')
                ->label('Show Tab Icons')
                ->default(false),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            Repeater::make('tabs')
                ->label('Tabs')
                ->schema([
                    TextInput::make('title')
                        ->label('Tab Title')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('icon')
                        ->label('Icon (Heroicon name)')
                        ->placeholder('e.g., heroicon-o-star')
                        ->maxLength(100),

                    RichEditor::make('content')
                        ->label('Tab Content')
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'link',
                            'bulletList',
                            'orderedList',
                            'h2',
                            'h3',
                        ]),
                ])
                ->defaultItems(3)
                ->minItems(1)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'default',
            'alignment' => 'left',
            'vertical' => false,
            'showIcon' => false,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'tabs' => [
                    [
                        'title' => 'About',
                        'icon' => 'heroicon-o-information-circle',
                        'content' => '<p>Welcome to our platform. Learn more about what we offer.</p>',
                    ],
                    [
                        'title' => 'Services',
                        'icon' => 'heroicon-o-briefcase',
                        'content' => '<p>Discover our comprehensive range of services designed for you.</p>',
                    ],
                    [
                        'title' => 'Contact',
                        'icon' => 'heroicon-o-envelope',
                        'content' => '<p>Get in touch with us for more information.</p>',
                    ],
                ],
            ],
            'ro' => [
                'tabs' => [
                    [
                        'title' => 'Despre',
                        'icon' => 'heroicon-o-information-circle',
                        'content' => '<p>Bine ați venit pe platforma noastră. Aflați mai multe despre ce oferim.</p>',
                    ],
                    [
                        'title' => 'Servicii',
                        'icon' => 'heroicon-o-briefcase',
                        'content' => '<p>Descoperă gama noastră completă de servicii concepute pentru tine.</p>',
                    ],
                    [
                        'title' => 'Contact',
                        'icon' => 'heroicon-o-envelope',
                        'content' => '<p>Contactează-ne pentru mai multe informații.</p>',
                    ],
                ],
            ],
        ];
    }
}
