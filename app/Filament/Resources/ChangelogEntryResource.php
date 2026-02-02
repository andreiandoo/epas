<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChangelogEntryResource\Pages;
use App\Models\ChangelogEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use UnitEnum;

class ChangelogEntryResource extends Resource
{
    protected static ?string $model = ChangelogEntry::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'Setări';

    protected static ?string $navigationLabel = 'Changelog';

    protected static ?string $modelLabel = 'Changelog Entry';

    protected static ?string $pluralModelLabel = 'Changelog';

    protected static ?int $navigationSort = 99;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('short_hash')
                    ->label('Commit')
                    ->copyable()
                    ->copyMessage('Commit hash copiat!')
                    ->fontFamily('mono')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'feat' => 'success',
                        'fix' => 'danger',
                        'refactor' => 'warning',
                        'docs' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ChangelogEntry::TYPE_LABELS[$state] ?? $state),

                Tables\Columns\TextColumn::make('module')
                    ->label('Modul')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => ChangelogEntry::MODULE_MAPPINGS[$state] ?? ucfirst($state ?? 'General')),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descriere')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->message)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_breaking')
                    ->label('Breaking')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Vizibil'),

                Tables\Columns\TextColumn::make('committed_at')
                    ->label('Data')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options(ChangelogEntry::TYPE_LABELS),

                SelectFilter::make('module')
                    ->label('Modul')
                    ->options(ChangelogEntry::MODULE_MAPPINGS),

                Filter::make('is_visible')
                    ->label('Doar vizibile')
                    ->query(fn (Builder $query) => $query->where('is_visible', true))
                    ->default(),

                Filter::make('is_breaking')
                    ->label('Breaking changes')
                    ->query(fn (Builder $query) => $query->where('is_breaking', true)),

                Filter::make('last_7_days')
                    ->label('Ultimele 7 zile')
                    ->query(fn (Builder $query) => $query->where('committed_at', '>=', now()->subDays(7))),

                Filter::make('last_30_days')
                    ->label('Ultimele 30 zile')
                    ->query(fn (Builder $query) => $query->where('committed_at', '>=', now()->subDays(30))),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('committed_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangelogEntries::route('/'),
        ];
    }
}
