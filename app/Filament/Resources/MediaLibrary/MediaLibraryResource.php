<?php

namespace App\Filament\Resources\MediaLibrary;

use App\Filament\Resources\MediaLibrary\Pages\ListMediaLibrary;
use App\Filament\Resources\MediaLibrary\Pages\ViewMedia;
use App\Models\MediaLibrary;
use App\Services\Media\ImageCompressionService;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

class MediaLibraryResource extends Resource
{
    protected static ?string $model = MediaLibrary::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';
    protected static UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Media Library';
    protected static ?string $modelLabel = 'Media';
    protected static ?string $pluralModelLabel = 'Media Library';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Grid::make(3)->schema([
                SC\Group::make()->columnSpan(2)->schema([
                    // Preview Section
                    SC\Section::make('Preview')
                        ->schema([
                            Forms\Components\Placeholder::make('media_preview')
                                ->hiddenLabel()
                                ->content(function (?MediaLibrary $record) {
                                    if (!$record) {
                                        return '';
                                    }

                                    $url = $record->url;

                                    if ($record->is_image) {
                                        return new HtmlString("
                                            <div style='text-align: center; padding: 20px; background: #1e293b; border-radius: 8px;'>
                                                <img src='{$url}' alt='" . e($record->filename) . "'
                                                     style='max-width: 100%; max-height: 400px; border-radius: 4px; object-fit: contain;'>
                                            </div>
                                        ");
                                    }

                                    if ($record->is_video) {
                                        return new HtmlString("
                                            <div style='text-align: center; padding: 20px; background: #1e293b; border-radius: 8px;'>
                                                <video controls style='max-width: 100%; max-height: 400px; border-radius: 4px;'>
                                                    <source src='{$url}' type='{$record->mime_type}'>
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                        ");
                                    }

                                    // Generic file icon
                                    $icon = match (true) {
                                        str_contains($record->mime_type ?? '', 'pdf') => '📄',
                                        str_contains($record->mime_type ?? '', 'word') => '📝',
                                        str_contains($record->mime_type ?? '', 'excel') || str_contains($record->mime_type ?? '', 'spreadsheet') => '📊',
                                        str_contains($record->mime_type ?? '', 'zip') || str_contains($record->mime_type ?? '', 'archive') => '📦',
                                        default => '📁',
                                    };

                                    return new HtmlString("
                                        <div style='text-align: center; padding: 40px; background: #1e293b; border-radius: 8px;'>
                                            <div style='font-size: 64px; margin-bottom: 16px;'>{$icon}</div>
                                            <div style='color: #94a3b8; font-size: 14px;'>" . e($record->filename) . "</div>
                                            <a href='{$url}' target='_blank' style='display: inline-block; margin-top: 16px; padding: 8px 16px; background: #3b82f6; color: white; border-radius: 4px; text-decoration: none;'>
                                                Download File
                                            </a>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Edit metadata
                    SC\Section::make('Metadata')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Title')
                                ->maxLength(500)
                                ->placeholder('Optional title for the media'),

                            Forms\Components\TextInput::make('alt_text')
                                ->label('Alt Text')
                                ->maxLength(500)
                                ->placeholder('Alternative text for accessibility'),

                            Forms\Components\Select::make('collection')
                                ->label('Collection')
                                ->options([
                                    'artists' => 'Artists',
                                    'events' => 'Events',
                                    'products' => 'Products',
                                    'venues' => 'Venues',
                                    'blog' => 'Blog',
                                    'shop' => 'Shop',
                                    'settings' => 'Settings',
                                    'documents' => 'Documents',
                                    'gallery' => 'Gallery',
                                    'other' => 'Other',
                                ])
                                ->searchable(),
                        ])
                        ->columns(1),

                    // Usage Tracking Section
                    SC\Section::make('Usage')
                        ->description('Where this file is being used')
                        ->icon('heroicon-o-link')
                        ->collapsed()
                        ->visible(fn (?MediaLibrary $record) => $record && $record->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('usage_info')
                                ->hiddenLabel()
                                ->content(function (?MediaLibrary $record) {
                                    if (!$record) {
                                        return '';
                                    }

                                    $usages = self::findFileUsages($record);

                                    if (empty($usages)) {
                                        return new HtmlString("
                                            <div style='text-align: center; padding: 20px; color: #64748b;'>
                                                <p>No usages found for this file.</p>
                                                <p style='font-size: 12px; margin-top: 8px;'>This file may be unused or manually referenced.</p>
                                            </div>
                                        ");
                                    }

                                    $html = "<div style='space-y-2;'>";
                                    foreach ($usages as $usage) {
                                        $html .= "
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #1e293b; border-radius: 8px; margin-bottom: 8px;'>
                                                <div>
                                                    <div style='font-weight: 600; color: #e2e8f0;'>{$usage['model']}</div>
                                                    <div style='font-size: 12px; color: #64748b;'>{$usage['field']} - ID: {$usage['id']}</div>
                                                </div>
                                                <span style='padding: 2px 8px; background: #334155; color: #94a3b8; border-radius: 4px; font-size: 11px;'>{$usage['type']}</span>
                                            </div>
                                        ";
                                    }
                                    $html .= "</div>";

                                    return new HtmlString($html);
                                }),
                        ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    // File Info
                    SC\Section::make('File Information')
                        ->compact()
                        ->schema([
                            Forms\Components\Placeholder::make('file_info')
                                ->hiddenLabel()
                                ->content(function (?MediaLibrary $record) {
                                    if (!$record) {
                                        return '';
                                    }

                                    $dimensions = '';
                                    if ($record->width && $record->height) {
                                        $dimensions = "
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Dimensions</span>
                                                <span style='color: #e2e8f0;'>{$record->width} × {$record->height}</span>
                                            </div>
                                        ";
                                    }

                                    $existsOnDisk = $record->existsOnDisk();
                                    $statusBadge = $existsOnDisk
                                        ? "<span style='padding: 2px 8px; background: rgba(16, 185, 129, 0.2); color: #10b981; border-radius: 4px; font-size: 12px;'>✓ Exists</span>"
                                        : "<span style='padding: 2px 8px; background: rgba(239, 68, 68, 0.2); color: #ef4444; border-radius: 4px; font-size: 12px;'>✗ Missing</span>";

                                    // Compression info
                                    $compressionInfo = '';
                                    if (isset($record->metadata['compressed_at'])) {
                                        $originalSize = $record->metadata['original_size'] ?? 0;
                                        $savedBytes = $originalSize - $record->size;
                                        $savedPct = $originalSize > 0 ? round(($savedBytes / $originalSize) * 100, 1) : 0;
                                        $savedHuman = ImageCompressionService::formatBytes($savedBytes);

                                        $compressionInfo = "
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Compressed</span>
                                                <span style='padding: 2px 8px; background: rgba(16, 185, 129, 0.2); color: #10b981; border-radius: 4px; font-size: 12px;'>✓ {$savedHuman} saved ({$savedPct}%)</span>
                                            </div>
                                        ";
                                    }

                                    return new HtmlString("
                                        <div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Filename</span>
                                                <span style='color: #e2e8f0; font-size: 12px; word-break: break-all;'>" . e($record->filename) . "</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Type</span>
                                                <span style='color: #e2e8f0;'>{$record->mime_type}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Size</span>
                                                <span style='color: #e2e8f0;'>{$record->human_readable_size}</span>
                                            </div>
                                            {$dimensions}
                                            {$compressionInfo}
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Directory</span>
                                                <span style='color: #e2e8f0; font-size: 12px;'>" . e($record->directory ?? '/') . "</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Disk</span>
                                                <span style='color: #e2e8f0;'>{$record->disk}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Status</span>
                                                {$statusBadge}
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Uploaded</span>
                                                <span style='color: #e2e8f0;'>" . ($record->created_at ? $record->created_at->format('d M Y H:i') : '-') . "</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0;'>
                                                <span style='color: #64748b;'>ID</span>
                                                <span style='color: #64748b; font-family: monospace;'>{$record->id}</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // URL Section
                    SC\Section::make('URLs')
                        ->compact()
                        ->schema([
                            Forms\Components\Placeholder::make('url_info')
                                ->hiddenLabel()
                                ->content(function (?MediaLibrary $record) {
                                    if (!$record) {
                                        return '';
                                    }

                                    $url = $record->url ?? '';

                                    return new HtmlString("
                                        <div style='margin-bottom: 12px;'>
                                            <div style='color: #64748b; font-size: 12px; margin-bottom: 4px;'>Public URL</div>
                                            <div style='background: #1e293b; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 11px; word-break: break-all; color: #3b82f6;'>
                                                <a href='{$url}' target='_blank' style='color: #3b82f6; text-decoration: none;'>{$url}</a>
                                            </div>
                                        </div>
                                        <div>
                                            <div style='color: #64748b; font-size: 12px; margin-bottom: 4px;'>Storage Path</div>
                                            <div style='background: #1e293b; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 11px; word-break: break-all; color: #e2e8f0;'>
                                                " . e($record->path) . "
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Association Info
                    SC\Section::make('Association')
                        ->compact()
                        ->visible(fn (?MediaLibrary $record) => $record && ($record->model_type || $record->collection))
                        ->schema([
                            Forms\Components\Placeholder::make('association_info')
                                ->hiddenLabel()
                                ->content(function (?MediaLibrary $record) {
                                    if (!$record) {
                                        return '';
                                    }

                                    $html = "<div>";

                                    if ($record->collection) {
                                        $html .= "
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Collection</span>
                                                <span style='padding: 2px 8px; background: #334155; color: #e2e8f0; border-radius: 4px; font-size: 12px;'>{$record->collection}</span>
                                            </div>
                                        ";
                                    }

                                    if ($record->model_type && $record->model_id) {
                                        $modelName = class_basename($record->model_type);
                                        $html .= "
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0;'>
                                                <span style='color: #64748b;'>Associated Model</span>
                                                <span style='color: #e2e8f0;'>{$modelName} #{$record->model_id}</span>
                                            </div>
                                        ";
                                    }

                                    $html .= "</div>";

                                    return new HtmlString($html);
                                }),
                        ]),
                ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Thumbnail
                Tables\Columns\ImageColumn::make('path')
                    ->label('')
                    ->disk('public')
                    ->width(60)
                    ->height(60)
                    ->square()
                    ->defaultImageUrl(fn (?MediaLibrary $record) => $record ? match (true) {
                        str_contains($record->mime_type ?? '', 'pdf') => 'https://ui-avatars.com/api/?name=PDF&color=ef4444&background=1e293b',
                        str_contains($record->mime_type ?? '', 'video') => 'https://ui-avatars.com/api/?name=VID&color=8b5cf6&background=1e293b',
                        str_contains($record->mime_type ?? '', 'word') => 'https://ui-avatars.com/api/?name=DOC&color=3b82f6&background=1e293b',
                        str_contains($record->mime_type ?? '', 'excel') || str_contains($record->mime_type ?? '', 'spreadsheet') => 'https://ui-avatars.com/api/?name=XLS&color=22c55e&background=1e293b',
                        default => 'https://ui-avatars.com/api/?name=FILE&color=64748b&background=1e293b',
                    } : null)
                    ->visible(fn (?MediaLibrary $record) => $record?->is_image ?? true),

                Tables\Columns\TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn (?MediaLibrary $record) => $record?->filename)
                    ->description(fn (?MediaLibrary $record) => $record?->directory),

                Tables\Columns\TextColumn::make('collection')
                    ->label('Collection')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'artists' => 'info',
                        'events' => 'success',
                        'products' => 'warning',
                        'settings' => 'gray',
                        default => 'primary',
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match (true) {
                        str_starts_with($state ?? '', 'image/') => 'Image',
                        str_starts_with($state ?? '', 'video/') => 'Video',
                        str_contains($state ?? '', 'pdf') => 'PDF',
                        str_contains($state ?? '', 'word') => 'Word',
                        str_contains($state ?? '', 'excel') || str_contains($state ?? '', 'spreadsheet') => 'Excel',
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('human_readable_size')
                    ->label('Size')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('size', $direction))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_compressed')
                    ->label('Compressed')
                    ->getStateUsing(fn (?MediaLibrary $record) => $record ? isset($record->metadata['compressed_at']) : false)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Dimensions')
                    ->getStateUsing(fn (?MediaLibrary $record) => $record && $record->width && $record->height
                        ? "{$record->width}×{$record->height}"
                        : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at_month')
                    ->label('Month')
                    ->getStateUsing(fn (?MediaLibrary $record) => $record?->created_at?->format('F Y'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('created_at', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Year Filter
                Tables\Filters\SelectFilter::make('year')
                    ->label('Year')
                    ->options(function () {
                        $years = MediaLibrary::query()
                            ->selectRaw('DISTINCT YEAR(created_at) as year')
                            ->whereNotNull('created_at')
                            ->orderBy('year', 'desc')
                            ->pluck('year', 'year')
                            ->toArray();

                        if (empty($years)) {
                            $currentYear = now()->year;
                            return [$currentYear => $currentYear];
                        }

                        return $years;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $year): Builder => $query->whereYear('created_at', $year)
                        );
                    }),

                // Month Filter
                Tables\Filters\SelectFilter::make('month')
                    ->label('Month')
                    ->options([
                        1 => 'January',
                        2 => 'February',
                        3 => 'March',
                        4 => 'April',
                        5 => 'May',
                        6 => 'June',
                        7 => 'July',
                        8 => 'August',
                        9 => 'September',
                        10 => 'October',
                        11 => 'November',
                        12 => 'December',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $month): Builder => $query->whereMonth('created_at', $month)
                        );
                    }),

                // Collection Filter
                Tables\Filters\SelectFilter::make('collection')
                    ->label('Collection')
                    ->options(function () {
                        return MediaLibrary::query()
                            ->whereNotNull('collection')
                            ->distinct()
                            ->pluck('collection', 'collection')
                            ->toArray();
                    }),

                // File Type Filter
                Tables\Filters\SelectFilter::make('file_type')
                    ->label('File Type')
                    ->options([
                        'image' => 'Images',
                        'video' => 'Videos',
                        'pdf' => 'PDFs',
                        'document' => 'Documents',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function (Builder $query, string $type) {
                            return match ($type) {
                                'image' => $query->where('mime_type', 'LIKE', 'image/%'),
                                'video' => $query->where('mime_type', 'LIKE', 'video/%'),
                                'pdf' => $query->where('mime_type', 'LIKE', '%pdf%'),
                                'document' => $query->whereIn('mime_type', [
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'text/plain',
                                    'text/csv',
                                ]),
                                default => $query,
                            };
                        });
                    }),

                // Compression Filter
                Tables\Filters\TernaryFilter::make('compressed')
                    ->label('Compressed')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('metadata')
                            ->whereRaw("JSON_EXTRACT(metadata, '$.compressed_at') IS NOT NULL"),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('metadata')
                              ->orWhereRaw("JSON_EXTRACT(metadata, '$.compressed_at') IS NULL");
                        }),
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                Action::make('compress')
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->iconButton()
                    ->color('warning')
                    ->tooltip('Compress image')
                    ->visible(fn (?MediaLibrary $record) => $record && $record->is_image && !isset($record->metadata['compressed_at']))
                    ->requiresConfirmation()
                    ->modalHeading('Compress Image')
                    ->modalDescription('This will compress the image to reduce file size.')
                    ->action(function (?MediaLibrary $record) {
                        if (!$record) return;
                        $service = new ImageCompressionService();
                        $service->quality(80);

                        $result = $service->compress($record);

                        if ($result->success && !$result->skipped) {
                            Notification::make()
                                ->title('Image Compressed')
                                ->body("Saved {$result->getSavedHuman()} ({$result->savedPercentage}% reduction)")
                                ->success()
                                ->send();
                        } elseif ($result->skipped) {
                            Notification::make()
                                ->title('Compression Skipped')
                                ->body($result->skipReason)
                                ->info()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Compression Failed')
                                ->body($result->error)
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (?MediaLibrary $record) => $record?->url)
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Bulk Compress Action
                    BulkAction::make('compress')
                        ->label('Compress Images')
                        ->icon('heroicon-o-arrow-down-on-square-stack')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Compress Selected Images')
                        ->modalDescription('This will compress all selected images. Non-image files will be skipped.')
                        ->form([
                            Forms\Components\Select::make('quality')
                                ->label('Quality')
                                ->options([
                                    90 => 'High (90%)',
                                    80 => 'Medium (80%)',
                                    70 => 'Standard (70%)',
                                    60 => 'Low (60%)',
                                ])
                                ->default(80)
                                ->required(),
                            Forms\Components\Toggle::make('convert_webp')
                                ->label('Convert to WebP')
                                ->default(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $service = new ImageCompressionService();
                            $service->quality((int) $data['quality']);

                            if ($data['convert_webp'] ?? false) {
                                $service->convertToWebp();
                            }

                            $results = $service->compressMany($records->filter(fn ($r) => $r->is_image));
                            $stats = ImageCompressionService::getStatistics($results);

                            Notification::make()
                                ->title('Compression Complete')
                                ->body(sprintf(
                                    'Processed %d images. Saved %s (%s%% reduction).',
                                    $stats['successful'],
                                    $stats['total_saved_human'],
                                    $stats['total_saved_percentage']
                                ))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),

                    BulkAction::make('export_urls')
                        ->label('Export URLs')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->action(function (Collection $records) {
                            $urls = $records->pluck('url')->filter()->implode("\n");
                            Notification::make()
                                ->title('URLs Exported')
                                ->body("Exported {$records->count()} URLs")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('change_collection')
                        ->label('Change Collection')
                        ->icon('heroicon-o-folder')
                        ->form([
                            Forms\Components\Select::make('collection')
                                ->label('Collection')
                                ->options([
                                    'artists' => 'Artists',
                                    'events' => 'Events',
                                    'products' => 'Products',
                                    'venues' => 'Venues',
                                    'blog' => 'Blog',
                                    'shop' => 'Shop',
                                    'gallery' => 'Gallery',
                                    'documents' => 'Documents',
                                    'uploads' => 'Uploads',
                                    'other' => 'Other',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn ($record) => $record->update(['collection' => $data['collection']]));

                            Notification::make()
                                ->title('Collection Updated')
                                ->body("Updated {$records->count()} files to collection: {$data['collection']}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No media files')
            ->emptyStateDescription('Upload files through the application or scan existing files.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    /**
     * Find usages of the media file across the application
     */
    protected static function findFileUsages(MediaLibrary $media): array
    {
        $usages = [];
        $path = $media->path;

        // Define models and their URL fields to search
        $modelsToSearch = [
            ['model' => \App\Models\Artist::class, 'fields' => ['main_image_url', 'logo_url', 'portrait_url'], 'name' => 'Artist'],
            ['model' => \App\Models\Event::class, 'fields' => ['poster_url', 'hero_image_url'], 'name' => 'Event'],
        ];

        // Add more models if they exist
        if (class_exists(\App\Models\Venue::class)) {
            $modelsToSearch[] = ['model' => \App\Models\Venue::class, 'fields' => ['image_url', 'logo_url'], 'name' => 'Venue'];
        }
        if (class_exists(\App\Models\BlogArticle::class)) {
            $modelsToSearch[] = ['model' => \App\Models\BlogArticle::class, 'fields' => ['featured_image'], 'name' => 'Blog Article'];
        }
        if (class_exists(\App\Models\ShopProduct::class)) {
            $modelsToSearch[] = ['model' => \App\Models\ShopProduct::class, 'fields' => ['image_url'], 'name' => 'Shop Product'];
        }

        foreach ($modelsToSearch as $config) {
            if (!class_exists($config['model'])) {
                continue;
            }

            foreach ($config['fields'] as $field) {
                try {
                    $records = $config['model']::where($field, $path)->get();
                    foreach ($records as $record) {
                        $usages[] = [
                            'model' => $config['name'],
                            'field' => $field,
                            'id' => $record->id,
                            'type' => 'Direct Reference',
                        ];
                    }
                } catch (\Throwable) {
                    // Skip if field doesn't exist
                }
            }
        }

        // Check polymorphic association
        if ($media->model_type && $media->model_id) {
            $usages[] = [
                'model' => class_basename($media->model_type),
                'field' => 'media association',
                'id' => $media->model_id,
                'type' => 'Associated',
            ];
        }

        return $usages;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaLibrary::route('/'),
            'view' => ViewMedia::route('/{record}'),
        ];
    }
}
