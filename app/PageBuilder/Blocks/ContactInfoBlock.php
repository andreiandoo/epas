<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContactInfoBlock extends BaseBlock
{
    public static string $type = 'contact-info';
    public static string $name = 'Contact Information';
    public static string $description = 'Display contact details with icons';
    public static string $icon = 'heroicon-o-phone';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('layout')
                ->label('Layout')
                ->options([
                    'vertical' => 'Vertical List',
                    'horizontal' => 'Horizontal Row',
                    'grid' => '2-Column Grid',
                    'card' => 'Card Style',
                ])
                ->default('vertical'),

            Toggle::make('showIcons')
                ->label('Show Icons')
                ->default(true),

            Toggle::make('showLabels')
                ->label('Show Labels')
                ->default(true),

            Select::make('iconStyle')
                ->label('Icon Style')
                ->options([
                    'default' => 'Default',
                    'circle' => 'Circle Background',
                    'square' => 'Square Background',
                ])
                ->default('default'),

            Toggle::make('clickable')
                ->label('Make Links Clickable')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            Textarea::make('description')
                ->label('Description')
                ->rows(2)
                ->maxLength(500),

            Repeater::make('contacts')
                ->label('Contact Items')
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'phone' => 'Phone',
                            'email' => 'Email',
                            'address' => 'Address',
                            'hours' => 'Business Hours',
                            'website' => 'Website',
                            'custom' => 'Custom',
                        ])
                        ->required()
                        ->default('phone'),

                    TextInput::make('icon')
                        ->label('Custom Icon (Heroicon)')
                        ->placeholder('e.g., heroicon-o-phone')
                        ->visible(fn ($get) => $get('type') === 'custom'),

                    TextInput::make('label')
                        ->label('Label')
                        ->maxLength(100),

                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(300),

                    TextInput::make('link')
                        ->label('Link (optional)')
                        ->maxLength(500)
                        ->helperText('Auto-generated for phone/email if empty'),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'layout' => 'vertical',
            'showIcons' => true,
            'showLabels' => true,
            'iconStyle' => 'default',
            'clickable' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Contact Us',
                'description' => '',
                'contacts' => [
                    [
                        'type' => 'phone',
                        'icon' => '',
                        'label' => 'Phone',
                        'value' => '+40 123 456 789',
                        'link' => '',
                    ],
                    [
                        'type' => 'email',
                        'icon' => '',
                        'label' => 'Email',
                        'value' => 'contact@example.com',
                        'link' => '',
                    ],
                    [
                        'type' => 'address',
                        'icon' => '',
                        'label' => 'Address',
                        'value' => '123 Main Street, City, Country',
                        'link' => '',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Contactează-ne',
                'description' => '',
                'contacts' => [
                    [
                        'type' => 'phone',
                        'icon' => '',
                        'label' => 'Telefon',
                        'value' => '+40 123 456 789',
                        'link' => '',
                    ],
                    [
                        'type' => 'email',
                        'icon' => '',
                        'label' => 'Email',
                        'value' => 'contact@example.com',
                        'link' => '',
                    ],
                    [
                        'type' => 'address',
                        'icon' => '',
                        'label' => 'Adresă',
                        'value' => 'Strada Principală 123, Oraș, Țară',
                        'link' => '',
                    ],
                ],
            ],
        ];
    }
}
