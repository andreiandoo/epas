<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class FormBlock extends BaseBlock
{
    public static string $type = 'form';
    public static string $name = 'Custom Form';
    public static string $description = 'Build custom forms with various field types';
    public static string $icon = 'heroicon-o-clipboard-document-list';
    public static string $category = 'advanced';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('layout')
                ->label('Form Layout')
                ->options([
                    'vertical' => 'Vertical',
                    'horizontal' => 'Horizontal Labels',
                    'inline' => 'Inline Fields',
                ])
                ->default('vertical'),

            Select::make('columns')
                ->label('Form Columns')
                ->options([
                    1 => '1 Column',
                    2 => '2 Columns',
                ])
                ->default(1),

            Toggle::make('showLabels')
                ->label('Show Field Labels')
                ->default(true),

            Toggle::make('showRequired')
                ->label('Show Required Indicator')
                ->default(true),

            Select::make('buttonAlign')
                ->label('Submit Button Alignment')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                    'full' => 'Full Width',
                ])
                ->default('left'),
        ];
    }

    public static function getContentSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Form Title')
                ->maxLength(200),

            TextInput::make('description')
                ->label('Form Description')
                ->maxLength(500),

            Repeater::make('fields')
                ->label('Form Fields')
                ->schema([
                    TextInput::make('name')
                        ->label('Field Name (ID)')
                        ->required()
                        ->alphaDash()
                        ->maxLength(50),

                    TextInput::make('label')
                        ->label('Field Label')
                        ->required()
                        ->maxLength(100),

                    Select::make('type')
                        ->label('Field Type')
                        ->options([
                            'text' => 'Text',
                            'email' => 'Email',
                            'tel' => 'Phone',
                            'number' => 'Number',
                            'textarea' => 'Text Area',
                            'select' => 'Dropdown',
                            'checkbox' => 'Checkbox',
                            'radio' => 'Radio Buttons',
                            'date' => 'Date',
                            'file' => 'File Upload',
                        ])
                        ->required()
                        ->default('text'),

                    TextInput::make('placeholder')
                        ->label('Placeholder')
                        ->maxLength(200),

                    TextInput::make('options')
                        ->label('Options (comma-separated)')
                        ->helperText('For select, radio, or checkbox')
                        ->visible(fn ($get) => in_array($get('type'), ['select', 'radio', 'checkbox'])),

                    Toggle::make('required')
                        ->label('Required')
                        ->default(false),

                    Select::make('width')
                        ->label('Field Width')
                        ->options([
                            'full' => 'Full Width',
                            'half' => 'Half Width',
                        ])
                        ->default('full'),
                ])
                ->defaultItems(3)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),

            TextInput::make('submitText')
                ->label('Submit Button Text')
                ->default('Submit')
                ->maxLength(50),

            TextInput::make('successMessage')
                ->label('Success Message')
                ->default('Thank you for your submission!')
                ->maxLength(300),

            TextInput::make('recipientEmail')
                ->label('Send Submissions To')
                ->email()
                ->maxLength(200)
                ->helperText('Email address to receive form submissions'),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'layout' => 'vertical',
            'columns' => 1,
            'showLabels' => true,
            'showRequired' => true,
            'buttonAlign' => 'left',
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Contact Form',
                'description' => 'Fill out the form below and we will get back to you.',
                'fields' => [
                    [
                        'name' => 'name',
                        'label' => 'Your Name',
                        'type' => 'text',
                        'placeholder' => 'Enter your name',
                        'options' => '',
                        'required' => true,
                        'width' => 'full',
                    ],
                    [
                        'name' => 'email',
                        'label' => 'Email Address',
                        'type' => 'email',
                        'placeholder' => 'Enter your email',
                        'options' => '',
                        'required' => true,
                        'width' => 'full',
                    ],
                    [
                        'name' => 'message',
                        'label' => 'Message',
                        'type' => 'textarea',
                        'placeholder' => 'Your message...',
                        'options' => '',
                        'required' => true,
                        'width' => 'full',
                    ],
                ],
                'submitText' => 'Send Message',
                'successMessage' => 'Thank you! We will respond shortly.',
                'recipientEmail' => '',
            ],
            'ro' => [
                'title' => 'Formular de Contact',
                'description' => 'Completează formularul de mai jos și te vom contacta.',
                'fields' => [
                    [
                        'name' => 'name',
                        'label' => 'Numele Tău',
                        'type' => 'text',
                        'placeholder' => 'Introdu numele tău',
                        'options' => '',
                        'required' => true,
                        'width' => 'full',
                    ],
                    [
                        'name' => 'email',
                        'label' => 'Adresa de Email',
                        'type' => 'email',
                        'placeholder' => 'Introdu emailul tău',
                        'options' => '',
                        'required' => true,
                        'width' => 'full',
                    ],
                    [
                        'name' => 'message',
                        'label' => 'Mesaj',
                        'type' => 'textarea',
                        'placeholder' => 'Mesajul tău...',
                        'options' => '',
                        'required' => true,
                        'width' => 'full',
                    ],
                ],
                'submitText' => 'Trimite Mesaj',
                'successMessage' => 'Mulțumim! Te vom contacta în curând.',
                'recipientEmail' => '',
            ],
        ];
    }
}
