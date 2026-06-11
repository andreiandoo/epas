<?php

namespace App\PageBuilder\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class FileDownloadBlock extends BaseBlock
{
    public static string $type = 'file-download';
    public static string $name = 'File Downloads';
    public static string $description = 'List downloadable files and documents';
    public static string $icon = 'heroicon-o-document-arrow-down';
    public static string $category = 'content';

    public static function getSettingsSchema(): array
    {
        return [
            Select::make('style')
                ->label('Display Style')
                ->options([
                    'list' => 'Simple List',
                    'card' => 'Card Style',
                    'table' => 'Table View',
                    'grid' => 'Grid View',
                ])
                ->default('list'),

            Toggle::make('showFileSize')
                ->label('Show File Size')
                ->default(true),

            Toggle::make('showFileType')
                ->label('Show File Type Icon')
                ->default(true),

            Toggle::make('showDescription')
                ->label('Show Description')
                ->default(true),

            Toggle::make('openInNewTab')
                ->label('Open in New Tab')
                ->default(true),
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

            Repeater::make('files')
                ->label('Files')
                ->schema([
                    TextInput::make('name')
                        ->label('Display Name')
                        ->required()
                        ->maxLength(200),

                    TextInput::make('description')
                        ->label('Description')
                        ->maxLength(500),

                    FileUpload::make('file')
                        ->label('Upload File')
                        ->directory('downloads')
                        ->disk('public')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/zip',
                            'image/*',
                        ])
                        ->maxSize(50 * 1024), // 50MB

                    TextInput::make('externalUrl')
                        ->label('Or External URL')
                        ->url()
                        ->maxLength(500),

                    TextInput::make('fileSize')
                        ->label('File Size (if external)')
                        ->placeholder('e.g., 2.5 MB')
                        ->maxLength(50),

                    Select::make('fileType')
                        ->label('File Type')
                        ->options([
                            'pdf' => 'PDF',
                            'doc' => 'Word Document',
                            'xls' => 'Excel Spreadsheet',
                            'zip' => 'ZIP Archive',
                            'image' => 'Image',
                            'other' => 'Other',
                        ])
                        ->default('pdf'),
                ])
                ->defaultItems(2)
                ->collapsible()
                ->cloneable()
                ->columnSpanFull(),
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'style' => 'list',
            'showFileSize' => true,
            'showFileType' => true,
            'showDescription' => true,
            'openInNewTab' => true,
        ];
    }

    public static function getDefaultContent(): array
    {
        return [
            'en' => [
                'title' => 'Downloads',
                'subtitle' => '',
                'files' => [
                    [
                        'name' => 'Event Brochure',
                        'description' => 'Complete information about our upcoming events.',
                        'file' => null,
                        'externalUrl' => '',
                        'fileSize' => '',
                        'fileType' => 'pdf',
                    ],
                    [
                        'name' => 'Terms and Conditions',
                        'description' => 'Our terms of service document.',
                        'file' => null,
                        'externalUrl' => '',
                        'fileSize' => '',
                        'fileType' => 'pdf',
                    ],
                ],
            ],
            'ro' => [
                'title' => 'Descărcări',
                'subtitle' => '',
                'files' => [
                    [
                        'name' => 'Broșură Evenimente',
                        'description' => 'Informații complete despre evenimentele noastre.',
                        'file' => null,
                        'externalUrl' => '',
                        'fileSize' => '',
                        'fileType' => 'pdf',
                    ],
                    [
                        'name' => 'Termeni și Condiții',
                        'description' => 'Documentul nostru cu termenii serviciului.',
                        'file' => null,
                        'externalUrl' => '',
                        'fileSize' => '',
                        'fileType' => 'pdf',
                    ],
                ],
            ],
        ];
    }
}
