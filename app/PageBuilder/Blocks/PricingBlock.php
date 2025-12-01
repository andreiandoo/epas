<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class PricingBlock extends BaseBlock
{
    public static string $type = 'pricing';
    public static string $name = 'Pricing Table';
    public static string $description = 'Display pricing plans and packages';
    public static string $icon = 'heroicon-o-currency-euro';
    public static string $category = 'marketing';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('columns')
                ->label('Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                    4 => '4 Columns',
                ])
                ->default(3),

            Select::make('style')
                ->label('Card Style')
                ->options([
                    'default' => 'Default',
                    'bordered' => 'Bordered',
                    'shadow' => 'Shadow',
                    'gradient' => 'Gradient Header',
                ])
                ->default('shadow'),

            Toggle::make('showBadge')
                ->label('Show Popular Badge')
                ->default(true),

            Toggle::make('showToggle')
                ->label('Show Monthly/Yearly Toggle')
                ->default(false),

            Select::make('buttonStyle')
                ->label('Button Style')
                ->options([
                    'primary' => 'Primary',
                    'secondary' => 'Secondary',
                    'outline' => 'Outline',
                ])
                ->default('primary'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Section Title')
                ->maxLength(200),

            TextInput::make('subtitle')
                ->label('Section Subtitle')
                ->maxLength(500),

            Repeater::make('plans')
                ->label('Pricing Plans')
                ->schema([
                    TextInput::make('name')
                        ->label('Plan Name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('price')
                        ->label('Price')
                        ->required()
                        ->maxLength(50),

                    TextInput::make('period')
                        ->label('Billing Period')
                        ->placeholder('e.g., /month, /year')
                        ->maxLength(50),

                    TextInput::make('description')
                        ->label('Short Description')
                        ->maxLength(200),

                    Toggle::make('featured')
                        ->label('Featured/Popular')
                        ->default(false),

                    TextInput::make('badge')
                        ->label('Badge Text')
                        ->placeholder('e.g., Most Popular')
                        ->maxLength(50),

                    Repeater::make('features')
                        ->label('Features')
                        ->simple(
                            TextInput::make('feature')
                                ->required()
                                ->maxLength(150),
                        )
                        ->defaultItems(4)
                        ->columnSpanFull(),

                    TextInput::make('buttonText')
                        ->label('Button Text')
                        ->default('Get Started')
                        ->maxLength(50),

                    TextInput::make('buttonLink')
                        ->label('Button Link')
                        ->url()
                        ->maxLength(500),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 3,
            'style' => 'shadow',
            'showBadge' => true,
            'showToggle' => false,
            'buttonStyle' => 'primary',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Choose Your Plan',
                'subtitle' => 'Simple, transparent pricing',
                'plans' => [
                    [
                        'name' => 'Basic',
                        'price' => '€9',
                        'period' => '/month',
                        'description' => 'Perfect for getting started',
                        'featured' => false,
                        'badge' => '',
                        'features' => ['Up to 100 tickets', 'Basic analytics', 'Email support'],
                        'buttonText' => 'Get Started',
                        'buttonLink' => '#',
                    ],
                    [
                        'name' => 'Pro',
                        'price' => '€29',
                        'period' => '/month',
                        'description' => 'For growing businesses',
                        'featured' => true,
                        'badge' => 'Most Popular',
                        'features' => ['Unlimited tickets', 'Advanced analytics', 'Priority support', 'Custom branding'],
                        'buttonText' => 'Get Started',
                        'buttonLink' => '#',
                    ],
                    [
                        'name' => 'Enterprise',
                        'price' => '€99',
                        'period' => '/month',
                        'description' => 'For large organizations',
                        'featured' => false,
                        'badge' => '',
                        'features' => ['Everything in Pro', 'Dedicated manager', 'SLA guarantee', 'API access'],
                        'buttonText' => 'Contact Us',
                        'buttonLink' => '#',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Alege Planul Tău',
                'subtitle' => 'Prețuri simple și transparente',
                'plans' => [
                    [
                        'name' => 'Basic',
                        'price' => '€9',
                        'period' => '/lună',
                        'description' => 'Perfect pentru început',
                        'featured' => false,
                        'badge' => '',
                        'features' => ['Până la 100 bilete', 'Analiză de bază', 'Suport email'],
                        'buttonText' => 'Începe',
                        'buttonLink' => '#',
                    ],
                    [
                        'name' => 'Pro',
                        'price' => '€29',
                        'period' => '/lună',
                        'description' => 'Pentru afaceri în creștere',
                        'featured' => true,
                        'badge' => 'Cel Mai Popular',
                        'features' => ['Bilete nelimitate', 'Analiză avansată', 'Suport prioritar', 'Branding personalizat'],
                        'buttonText' => 'Începe',
                        'buttonLink' => '#',
                    ],
                    [
                        'name' => 'Enterprise',
                        'price' => '€99',
                        'period' => '/lună',
                        'description' => 'Pentru organizații mari',
                        'featured' => false,
                        'badge' => '',
                        'features' => ['Tot ce e în Pro', 'Manager dedicat', 'Garanție SLA', 'Acces API'],
                        'buttonText' => 'Contactează-ne',
                        'buttonLink' => '#',
                    ],
                ],
            ],
        ];
    }
}
