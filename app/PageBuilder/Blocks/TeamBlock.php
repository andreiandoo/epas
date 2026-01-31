<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TeamBlock extends BaseBlock
{
    public static string $type = 'team';
    public static string $name = 'Team Members';
    public static string $description = 'Showcase your team with photos and bios';
    public static string $icon = 'heroicon-o-user-group';
    public static string $category = 'content';

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
                ->default(4),

            Select::make('style')
                ->label('Card Style')
                ->options([
                    'default' => 'Default',
                    'rounded' => 'Circular Photos',
                    'overlay' => 'Image Overlay',
                    'minimal' => 'Minimal',
                ])
                ->default('default'),

            Toggle::make('showBio')
                ->label('Show Bio')
                ->default(true),

            Toggle::make('showSocial')
                ->label('Show Social Links')
                ->default(true),

            Select::make('imageRatio')
                ->label('Photo Ratio')
                ->options([
                    'square' => 'Square',
                    'portrait' => 'Portrait (3:4)',
                    'landscape' => 'Landscape (4:3)',
                ])
                ->default('square'),
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

            Repeater::make('members')
                ->label('Team Members')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('role')
                        ->label('Role/Position')
                        ->required()
                        ->maxLength(100),

                    Textarea::make('bio')
                        ->label('Short Bio')
                        ->rows(2)
                        ->maxLength(300),

                    FileUpload::make('photo')
                        ->label('Photo')
                        ->image()
                        ->directory('team')
                        ->disk('public'),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(200),

                    TextInput::make('linkedin')
                        ->label('LinkedIn URL')
                        ->url()
                        ->maxLength(300),

                    TextInput::make('twitter')
                        ->label('Twitter/X URL')
                        ->url()
                        ->maxLength(300),
                ])
                ->defaultItems(4)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'columns' => 4,
            'style' => 'default',
            'showBio' => true,
            'showSocial' => true,
            'imageRatio' => 'square',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Meet Our Team',
                'subtitle' => 'The people behind our success',
                'members' => [
                    [
                        'name' => 'John Smith',
                        'role' => 'CEO & Founder',
                        'bio' => 'Passionate about creating amazing event experiences.',
                        'photo' => null,
                        'email' => '',
                        'linkedin' => '',
                        'twitter' => '',
                    ],
                    [
                        'name' => 'Sarah Johnson',
                        'role' => 'Head of Operations',
                        'bio' => 'Expert in event logistics and customer satisfaction.',
                        'photo' => null,
                        'email' => '',
                        'linkedin' => '',
                        'twitter' => '',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Echipa Noastră',
                'subtitle' => 'Oamenii din spatele succesului nostru',
                'members' => [
                    [
                        'name' => 'Ion Popescu',
                        'role' => 'CEO & Fondator',
                        'bio' => 'Pasionat de crearea experiențelor extraordinare la evenimente.',
                        'photo' => null,
                        'email' => '',
                        'linkedin' => '',
                        'twitter' => '',
                    ],
                    [
                        'name' => 'Maria Ionescu',
                        'role' => 'Director Operațiuni',
                        'bio' => 'Expert în logistica evenimentelor și satisfacția clienților.',
                        'photo' => null,
                        'email' => '',
                        'linkedin' => '',
                        'twitter' => '',
                    ],
                ],
            ],
        ];
    }
}
