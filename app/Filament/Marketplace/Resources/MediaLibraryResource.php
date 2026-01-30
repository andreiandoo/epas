<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\MediaLibraryResource\Pages;
use App\Models\MediaLibrary;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class MediaLibraryResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MediaLibrary::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';
    protected static UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Media Library';
    protected static ?string $modelLabel = 'Media';
    protected static ?string $pluralModelLabel = 'Media Library';
    protected static ?int $navigationSort = 50;

    /**
     * Filter query to only show media for current marketplace
     */
    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            // Hidden marketplace_client_id
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

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
                                                    Browserul tău nu suportă video.
                                                </video>
                                            </div>
                                        ");
                                    }

                                    // Generic file icon
                                    $icon = match (true) {
                                        str_contains($record->mime_type ?? '', 'pdf') => '📄',
                                        str_contains($record->mime_type ?? '', 'word') => '📝',
                                        str_contains($record->mime_type ?? '', 'excel') || str_contains($record->mime_type ?? '', 'spreadsheet') => '📊',
                                        default => '📁',
                                    };

                                    return new HtmlString("
                                        <div style='text-align: center; padding: 40px; background: #1e293b; border-radius: 8px;'>
                                            <div style='font-size: 64px; margin-bottom: 16px;'>{$icon}</div>
                                            <div style='color: #94a3b8; font-size: 14px;'>" . e($record->filename) . "</div>
                                            <a href='{$url}' target='_blank' style='display: inline-block; margin-top: 16px; padding: 8px 16px; background: #10b981; color: white; border-radius: 4px; text-decoration: none;'>
                                                Descarcă Fișierul
                                            </a>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Edit metadata
                    SC\Section::make('Metadate')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Titlu')
                                ->maxLength(500)
                                ->placeholder('Titlu opțional pentru media'),

                            Forms\Components\TextInput::make('alt_text')
                                ->label('Text Alternativ')
                                ->maxLength(500)
                                ->placeholder('Text alternativ pentru accesibilitate'),

                            Forms\Components\Select::make('collection')
                                ->label('Colecție')
                                ->options([
                                    'artists' => 'Artiști',
                                    'events' => 'Evenimente',
                                    'products' => 'Produse',
                                    'venues' => 'Locații',
                                    'blog' => 'Blog',
                                    'shop' => 'Magazin',
                                    'gallery' => 'Galerie',
                                    'documents' => 'Documente',
                                    'other' => 'Altele',
                                ])
                                ->searchable(),
                        ])
                        ->columns(1),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    // File Info
                    SC\Section::make('Informații Fișier')
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
                                                <span style='color: #64748b;'>Dimensiuni</span>
                                                <span style='color: #e2e8f0;'>{$record->width} × {$record->height}</span>
                                            </div>
                                        ";
                                    }

                                    $existsOnDisk = $record->existsOnDisk();
                                    $statusBadge = $existsOnDisk
                                        ? "<span style='padding: 2px 8px; background: rgba(16, 185, 129, 0.2); color: #10b981; border-radius: 4px; font-size: 12px;'>✓ Există</span>"
                                        : "<span style='padding: 2px 8px; background: rgba(239, 68, 68, 0.2); color: #ef4444; border-radius: 4px; font-size: 12px;'>✗ Lipsă</span>";

                                    return new HtmlString("
                                        <div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Nume Fișier</span>
                                                <span style='color: #e2e8f0; font-size: 12px; word-break: break-all;'>" . e($record->filename) . "</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Tip</span>
                                                <span style='color: #e2e8f0;'>{$record->mime_type}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Mărime</span>
                                                <span style='color: #e2e8f0;'>{$record->human_readable_size}</span>
                                            </div>
                                            {$dimensions}
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Director</span>
                                                <span style='color: #e2e8f0; font-size: 12px;'>" . e($record->directory ?? '/') . "</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='color: #64748b;'>Status</span>
                                                {$statusBadge}
                                            </div>
                                            <div style='display: flex; justify-content: space-between; padding: 8px 0;'>
                                                <span style='color: #64748b;'>Încărcat</span>
                                                <span style='color: #e2e8f0;'>" . ($record->created_at ? $record->created_at->format('d M Y H:i') : '-') . "</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // URL Section
                    SC\Section::make('URL-uri')
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
                                        <div>
                                            <div style='color: #64748b; font-size: 12px; margin-bottom: 4px;'>URL Public</div>
                                            <div style='background: #1e293b; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 11px; word-break: break-all; color: #10b981;'>
                                                <a href='{$url}' target='_blank' style='color: #10b981; text-decoration: none;'>{$url}</a>
                                            </div>
                                        </div>
                                    ");
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
                    ->defaultImageUrl(fn (MediaLibrary $record) => match (true) {
                        str_contains($record->mime_type ?? '', 'pdf') => 'https://ui-avatars.com/api/?name=PDF&color=ef4444&background=1e293b',
                        str_contains($record->mime_type ?? '', 'video') => 'https://ui-avatars.com/api/?name=VID&color=8b5cf6&background=1e293b',
                        str_contains($record->mime_type ?? '', 'word') => 'https://ui-avatars.com/api/?name=DOC&color=3b82f6&background=1e293b',
                        str_contains($record->mime_type ?? '', 'excel') || str_contains($record->mime_type ?? '', 'spreadsheet') => 'https://ui-avatars.com/api/?name=XLS&color=22c55e&background=1e293b',
                        default => 'https://ui-avatars.com/api/?name=FILE&color=64748b&background=1e293b',
                    })
                    ->visible(fn (MediaLibrary $record) => $record->is_image),

                Tables\Columns\TextColumn::make('filename')
                    ->label('Nume Fișier')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn (MediaLibrary $record) => $record->filename)
                    ->description(fn (MediaLibrary $record) => $record->directory),

                Tables\Columns\TextColumn::make('collection')
                    ->label('Colecție')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'artists' => 'info',
                        'events' => 'success',
                        'products' => 'warning',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'artists' => 'Artiști',
                        'events' => 'Evenimente',
                        'products' => 'Produse',
                        'venues' => 'Locații',
                        'blog' => 'Blog',
                        'shop' => 'Magazin',
                        'gallery' => 'Galerie',
                        'documents' => 'Documente',
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Tip')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match (true) {
                        str_starts_with($state ?? '', 'image/') => 'Imagine',
                        str_starts_with($state ?? '', 'video/') => 'Video',
                        str_contains($state ?? '', 'pdf') => 'PDF',
                        str_contains($state ?? '', 'word') => 'Word',
                        str_contains($state ?? '', 'excel') || str_contains($state ?? '', 'spreadsheet') => 'Excel',
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('human_readable_size')
                    ->label('Mărime')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('size', $direction))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Dimensiuni')
                    ->getStateUsing(fn (MediaLibrary $record) => $record->width && $record->height
                        ? "{$record->width}×{$record->height}"
                        : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Încărcat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                // Year Filter
                Tables\Filters\SelectFilter::make('year')
                    ->label('An')
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();

                        $years = MediaLibrary::query()
                            ->where('marketplace_client_id', $marketplace?->id)
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
                    ->label('Lună')
                    ->options([
                        1 => 'Ianuarie',
                        2 => 'Februarie',
                        3 => 'Martie',
                        4 => 'Aprilie',
                        5 => 'Mai',
                        6 => 'Iunie',
                        7 => 'Iulie',
                        8 => 'August',
                        9 => 'Septembrie',
                        10 => 'Octombrie',
                        11 => 'Noiembrie',
                        12 => 'Decembrie',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $month): Builder => $query->whereMonth('created_at', $month)
                        );
                    }),

                // Collection Filter
                Tables\Filters\SelectFilter::make('collection')
                    ->label('Colecție')
                    ->options([
                        'artists' => 'Artiști',
                        'events' => 'Evenimente',
                        'products' => 'Produse',
                        'venues' => 'Locații',
                        'blog' => 'Blog',
                        'shop' => 'Magazin',
                        'gallery' => 'Galerie',
                        'documents' => 'Documente',
                    ]),

                // File Type Filter
                Tables\Filters\SelectFilter::make('file_type')
                    ->label('Tip Fișier')
                    ->options([
                        'image' => 'Imagini',
                        'video' => 'Video-uri',
                        'pdf' => 'PDF-uri',
                        'document' => 'Documente',
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
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (MediaLibrary $record) => $record->url)
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('export_urls')
                        ->label('Exportă URL-uri')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->action(function (Collection $records) {
                            \Filament\Notifications\Notification::make()
                                ->title('URL-uri Exportate')
                                ->body("S-au exportat {$records->count()} URL-uri")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Nicio fișier media')
            ->emptyStateDescription('Încarcă fișiere prin aplicație sau scanează fișierele existente.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaLibrary::route('/'),
            'view' => Pages\ViewMedia::route('/{record}'),
        ];
    }
}
