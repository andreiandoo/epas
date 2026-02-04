<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerDocumentResource\Pages;
use App\Models\OrganizerDocument;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrganizerDocumentResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = OrganizerDocument::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documents';

    protected static ?string $slug = 'organizer-documents';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->with(['organizer', 'event', 'taxTemplate']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Information')
                    ->icon('heroicon-o-document')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Document Title')
                            ->disabled(),

                        Forms\Components\TextInput::make('document_type')
                            ->label('Document Type')
                            ->formatStateUsing(fn ($state) => OrganizerDocument::TYPES[$state] ?? $state)
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('issued_at')
                            ->label('Issue Date')
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Organizer Details')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\Placeholder::make('organizer_info')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->organizer) {
                                    return 'No organizer data';
                                }
                                $org = $record->organizer;
                                return new HtmlString("
                                    <div class='space-y-2 text-sm'>
                                        <div><strong>Name:</strong> {$org->name}</div>
                                        <div><strong>Company:</strong> {$org->company_name}</div>
                                        <div><strong>Email:</strong> {$org->email}</div>
                                        <div><strong>Tax ID:</strong> {$org->company_tax_id}</div>
                                        <div><strong>Registration:</strong> {$org->company_registration}</div>
                                        <div><strong>Address:</strong> {$org->company_address}</div>
                                        <div><strong>City:</strong> {$org->city}</div>
                                    </div>
                                ");
                            }),
                    ]),

                Section::make('Event Details')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Forms\Components\Placeholder::make('event_info')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->event) {
                                    return 'No event data';
                                }
                                $event = $record->event;
                                $venue = $event->venue;

                                $venueName = $venue ? ($venue->getTranslation('name', 'ro') ?? '-') : ($event->venue_name ?? '-');
                                $venueAddress = $venue?->address ?? $event->venue_address ?? '-';
                                $venueCity = $venue?->city ?? $event->venue_city ?? '-';

                                $eventName = is_array($event->title)
                                    ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: '-')
                                    : ($event->title ?? $event->name ?? '-');
                                // Escape HTML to prevent XSS
                                $venueName = htmlspecialchars((string) $venueName);
                                $venueAddress = htmlspecialchars((string) $venueAddress);
                                $venueCity = htmlspecialchars((string) $venueCity);
                                $eventName = htmlspecialchars((string) $eventName);
                                $eventDate = $event->starts_at ? $event->starts_at->format('d.m.Y H:i') : '-';

                                return new HtmlString("
                                    <div class='space-y-2 text-sm'>
                                        <div><strong>Event Name:</strong> {$eventName}</div>
                                        <div><strong>Date:</strong> {$eventDate}</div>
                                        <div><strong>Venue:</strong> {$venueName}</div>
                                        <div><strong>Venue Address:</strong> {$venueAddress}</div>
                                        <div><strong>City:</strong> {$venueCity}</div>
                                    </div>
                                ");
                            }),
                    ]),

                Section::make('Document Preview')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Forms\Components\Placeholder::make('document_preview')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->html_content) {
                                    return 'No document content available';
                                }

                                // Create an isolated iframe for preview
                                $htmlContent = htmlspecialchars($record->html_content, ENT_QUOTES, 'UTF-8');

                                return new HtmlString("
                                    <div class='border border-gray-200 rounded-lg overflow-hidden bg-white'>
                                        <iframe
                                            id='document-preview-iframe'
                                            class='w-full'
                                            style='height: 800px; border: none;'
                                            srcdoc='{$htmlContent}'
                                        ></iframe>
                                    </div>
                                ");
                            }),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Document')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => OrganizerDocument::TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'cerere_avizare' => 'info',
                        'declaratie_impozite' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('organizer.company_name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(function ($record) {
                        return $record->event?->name;
                    }),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($record) => $record->formatted_file_size),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Type')
                    ->options(OrganizerDocument::TYPES),

                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizer')
                    ->relationship('organizer', 'company_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->company_name ?? $record->name ?? 'Unknown')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record])),
                Action::make('download')
                    ->label('')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record) => $record->download_url)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->file_path),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizerDocuments::route('/'),
            'view' => Pages\ViewOrganizerDocument::route('/{record}'),
        ];
    }
}
