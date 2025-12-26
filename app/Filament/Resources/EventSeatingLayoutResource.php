<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventSeatingLayoutResource\Pages;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatingLayout;
use BackedEnum;
use UnitEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class EventSeatingLayoutResource extends Resource
{
    protected static ?string $model = EventSeatingLayout::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static UnitEnum|string|null $navigationGroup = 'Venues & Mapping';
    protected static ?int $navigationSort = 30;
    protected static BackedEnum|string|null $navigationLabel = 'Event Seating';
    protected static ?string $modelLabel = 'Event Seating Layout';
    protected static ?string $pluralModelLabel = 'Event Seating Layouts';

    //protected static ?string $navigationParentItem = 'Events';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Event & Layout')
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Event')
                        ->options(fn (): array => \App\Models\Event::query()
                            ->orderBy('starts_at', 'desc')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn ($event) => [
                                $event->id => $event->getTranslation('title', app()->getLocale()) ?? $event->getTranslation('title', 'en') ?? "Event #{$event->id}"
                            ])
                            ->all()
                        )
                        ->required()
                        ->searchable()
                        ->helperText('Select the event to attach this seating layout to')
                        ->columnSpan(1),

                    Forms\Components\Select::make('layout_id')
                        ->label('Base Seating Layout')
                        ->options(fn (): array => SeatingLayout::where('status', 'published')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->helperText('Choose a published seating layout to use as the template')
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                            'archived' => 'Archived',
                        ])
                        ->default('draft')
                        ->required()
                        ->helperText('Only published layouts are visible to the public')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            SC\Section::make('Publishing')
                ->schema([
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Published At')
                        ->native(false)
                        ->helperText('When this layout went live (auto-set when status changes to published)')
                        ->disabled()
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('archived_at')
                        ->label('Archived At')
                        ->native(false)
                        ->helperText('When this layout was archived (auto-set when status changes to archived)')
                        ->disabled()
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->collapsed(),

            SC\Section::make('Seat Inventory')
                ->schema([
                    Forms\Components\Placeholder::make('total_seats')
                        ->content(fn ($record) => $record?->getSeatStatusCounts()['total'] ?? 0)
                        ->label('Total Seats'),

                    Forms\Components\Placeholder::make('available_seats')
                        ->content(fn ($record) => $record?->getSeatStatusCounts()['available'] ?? 0)
                        ->label('Available'),

                    Forms\Components\Placeholder::make('held_seats')
                        ->content(fn ($record) => $record?->getSeatStatusCounts()['held'] ?? 0)
                        ->label('Held'),

                    Forms\Components\Placeholder::make('sold_seats')
                        ->content(fn ($record) => $record?->getSeatStatusCounts()['sold'] ?? 0)
                        ->label('Sold'),

                    Forms\Components\Placeholder::make('blocked_seats')
                        ->content(fn ($record) => $record?->getSeatStatusCounts()['blocked'] ?? 0)
                        ->label('Blocked'),
                ])
                ->columns(5)
                ->hidden(fn ($record) => $record === null),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->url(fn (EventSeatingLayout $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('baseLayout.name')
                    ->label('Layout')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'archived' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('seats.total')
                    ->label('Total Seats')
                    ->formatStateUsing(fn (EventSeatingLayout $record) => $record->getSeatStatusCounts()['total'] ?? 0)
                    ->sortable(),

                Tables\Columns\TextColumn::make('seats.available')
                    ->label('Available')
                    ->formatStateUsing(fn (EventSeatingLayout $record) => $record->getSeatStatusCounts()['available'] ?? 0)
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('seats.sold')
                    ->label('Sold')
                    ->formatStateUsing(fn (EventSeatingLayout $record) => $record->getSeatStatusCounts()['sold'] ?? 0)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\SelectFilter::make('event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEventSeatingLayouts::route('/'),
            'create' => Pages\CreateEventSeatingLayout::route('/create'),
            'edit'   => Pages\EditEventSeatingLayout::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['event', 'baseLayout']);
    }
}
