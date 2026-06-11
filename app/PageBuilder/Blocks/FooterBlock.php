<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class FooterBlock extends BaseBlock
{
    public static string $type = 'footer';
    public static string $name = 'Site Footer';
    public static string $description = 'Website footer with links and contact info';
    public static string $icon = 'heroicon-o-bars-3-bottom-left';
    public static string $category = 'layout';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('columns')
                ->label('Number of Columns')
                ->options([
                    2 => '2 Columns',
                    3 => '3 Columns',
                    4 => '4 Columns',
                    5 => '5 Columns',
                ])
                ->default(4),

            Select::make('style')
                ->label('Footer Style')
                ->options([
                    'default' => 'Default',
                    'minimal' => 'Minimal',
                    'centered' => 'Centered',
                    'dark' => 'Dark Theme',
                ])
                ->default('default'),

            Toggle::make('showNewsletter')
                ->label('Show Newsletter Signup')
                ->default(true),

            Toggle::make('showSocialLinks')
                ->label('Show Social Links')
                ->default(true),

            Toggle::make('showPaymentIcons')
                ->label('Show Payment Icons')
                ->default(false),

            Toggle::make('showBackToTop')
                ->label('Show Back to Top Button')
                ->default(true),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            FileUpload::make('logo')
                ->label('Footer Logo')
                ->image()
                ->directory('footer')
                ->disk('public'),

            RichEditor::make('description')
                ->label('Company Description')
                ->toolbarButtons(['bold', 'italic', 'link']),

            Repeater::make('linkGroups')
                ->label('Link Columns')
                ->schema([
                    TextInput::make('title')
                        ->label('Column Title')
                        ->required()
                        ->maxLength(100),

                    Repeater::make('links')
                        ->label('Links')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(100),

                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(500),
                        ])
                        ->defaultItems(4)
                        ->columnSpanFull(),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->columnSpanFull(),

            TextInput::make('email')
                ->label('Contact Email')
                ->email()
                ->maxLength(200),

            TextInput::make('phone')
                ->label('Contact Phone')
                ->tel()
                ->maxLength(30),

            TextInput::make('address')
                ->label('Address')
                ->maxLength(300),

            Repeater::make('socialLinks')
                ->label('Social Links')
                ->schema([
                    Select::make('platform')
                        ->label('Platform')
                        ->options([
                            'facebook' => 'Facebook',
                            'instagram' => 'Instagram',
                            'twitter' => 'Twitter/X',
                            'linkedin' => 'LinkedIn',
                            'youtube' => 'YouTube',
                            'tiktok' => 'TikTok',
                        ])
                        ->required(),

                    TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->required()
                        ->maxLength(500),
                ])
                ->collapsible()
                ->columnSpanFull(),

            TextInput::make('copyright')
                ->label('Copyright Text')
                ->default('© 2024 Company Name. All rights reserved.')
                ->maxLength(200),

            Repeater::make('legalLinks')
                ->label('Legal Links')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('url')
                        ->label('URL')
                        ->required()
                        ->maxLength(500),
                ])
                ->defaultItems(2)
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 4,
            'style' => 'default',
            'showNewsletter' => true,
            'showSocialLinks' => true,
            'showPaymentIcons' => false,
            'showBackToTop' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'logo' => null,
                'description' => '<p>Your trusted platform for discovering and booking amazing events.</p>',
                'linkGroups' => [
                    [
                        'title' => 'Quick Links',
                        'links' => [
                            ['label' => 'Home', 'url' => '/'],
                            ['label' => 'Events', 'url' => '/events'],
                            ['label' => 'About Us', 'url' => '/about'],
                            ['label' => 'Contact', 'url' => '/contact'],
                        ],
                    ],
                    [
                        'title' => 'Support',
                        'links' => [
                            ['label' => 'FAQ', 'url' => '/faq'],
                            ['label' => 'Help Center', 'url' => '/help'],
                            ['label' => 'Refund Policy', 'url' => '/refund'],
                        ],
                    ],
                ],
                'email' => 'contact@example.com',
                'phone' => '+40 123 456 789',
                'address' => '123 Main Street, City, Country',
                'socialLinks' => [
                    ['platform' => 'facebook', 'url' => 'https://facebook.com'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com'],
                ],
                'copyright' => '© 2024 Company Name. All rights reserved.',
                'legalLinks' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                ],
            ],
            'ro' => [
                'logo' => null,
                'description' => '<p>Platforma ta de încredere pentru descoperirea și rezervarea evenimentelor.</p>',
                'linkGroups' => [
                    [
                        'title' => 'Linkuri Rapide',
                        'links' => [
                            ['label' => 'Acasă', 'url' => '/'],
                            ['label' => 'Evenimente', 'url' => '/events'],
                            ['label' => 'Despre Noi', 'url' => '/about'],
                            ['label' => 'Contact', 'url' => '/contact'],
                        ],
                    ],
                    [
                        'title' => 'Suport',
                        'links' => [
                            ['label' => 'Întrebări Frecvente', 'url' => '/faq'],
                            ['label' => 'Centru de Ajutor', 'url' => '/help'],
                            ['label' => 'Politica de Rambursare', 'url' => '/refund'],
                        ],
                    ],
                ],
                'email' => 'contact@example.com',
                'phone' => '+40 123 456 789',
                'address' => 'Strada Principală 123, Oraș, Țară',
                'socialLinks' => [
                    ['platform' => 'facebook', 'url' => 'https://facebook.com'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com'],
                ],
                'copyright' => '© 2024 Numele Companiei. Toate drepturile rezervate.',
                'legalLinks' => [
                    ['label' => 'Politica de Confidențialitate', 'url' => '/privacy'],
                    ['label' => 'Termeni și Condiții', 'url' => '/terms'],
                ],
            ],
        ];
    }
}
