<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Artist;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerArtists extends Page implements HasForms, HasTable
{
    use HasMarketplaceContext;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Artiști Parteneri';
    protected static ?string $title = 'Artiști Parteneri';
    protected static ?string $navigationParentItem = 'Artiști';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.marketplace.pages.partner-artists';

    /**
     * Normalize search string - lowercase and remove diacritics
     */
    protected static function normalizeSearch(string $search): string
    {
        $search = mb_strtolower($search);
        // Replace Romanian diacritics with base letters
        $diacritics = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
                       'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ț' => 't'];
        return strtr($search, $diacritics);
    }

    /**
     * Get artist display name
     */
    protected static function getArtistName(Artist $artist): string
    {
        return $artist->name ?? 'Artist';
    }

    public function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();

        return $table
            ->query(
                Artist::query()
                    ->where(function (Builder $query) use ($marketplace) {
                        // Show artists that are NOT already associated with any marketplace
                        $query->whereNull('marketplace_client_id')
                            // Or show artists that belong to this marketplace as partners
                            ->orWhere(function (Builder $q) use ($marketplace) {
                                $q->where('marketplace_client_id', $marketplace?->id)
                                    ->where('is_partner', true);
                            });
                    })
            )
            ->searchable()
            ->searchPlaceholder('Caută artiști...')
            ->columns([
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('Imagine')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=A&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->sortable(),
                Tables\Columns\TextColumn::make('artistTypes.name')
                    ->label('Tip')
                    ->badge()
                    ->separator(','),
                Tables\Columns\IconColumn::make('is_partner_status')
                    ->label('Status')
                    ->state(fn ($record) => $record->marketplace_client_id === $marketplace?->id)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('partner_status')
                    ->label('Status')
                    ->placeholder('Toți')
                    ->trueLabel('Parteneri')
                    ->falseLabel('Disponibili')
                    ->queries(
                        true: fn (Builder $query) => $query->where('marketplace_client_id', $marketplace?->id),
                        false: fn (Builder $query) => $query->whereNull('marketplace_client_id'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('city')
                    ->label('Oraș')
                    ->options(fn () => Artist::whereNull('marketplace_client_id')
                        ->orWhere('marketplace_client_id', $marketplace?->id)
                        ->whereNotNull('city')
                        ->distinct()
                        ->pluck('city', 'city')
                        ->toArray()
                    )
                    ->searchable(),
            ])
            ->actions([
                Actions\Action::make('add_partner')
                    ->label('Adaugă partener')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (Artist $record): bool => $record->marketplace_client_id !== $marketplace?->id)
                    ->modalHeading('Adaugă artist ca partener')
                    ->modalDescription('Confirmă adăugarea acestui artist ca partener.')
                    ->form([
                        Forms\Components\TextInput::make('artist_name')
                            ->label('Artist')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Note parteneriat (opțional)')
                            ->placeholder('Note interne despre acest parteneriat...')
                            ->rows(3),
                    ])
                    ->fillForm(fn (Artist $record): array => [
                        'artist_name' => static::getArtistName($record) . ($record->city ? ' - ' . $record->city : ''),
                    ])
                    ->action(function (Artist $record, array $data) use ($marketplace) {
                        $artistName = static::getArtistName($record);
                        $record->update([
                            'marketplace_client_id' => $marketplace?->id,
                            'is_partner' => true,
                            'partner_notes' => $data['partner_notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Artist adăugat')
                            ->body('"' . $artistName . '" a fost adăugat ca partener.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('remove_partner')
                    ->label('Elimină')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Artist $record): bool => $record->marketplace_client_id === $marketplace?->id && $record->is_partner)
                    ->requiresConfirmation()
                    ->modalHeading('Elimină artistul din parteneri')
                    ->modalDescription('Această acțiune va elimina artistul din lista de parteneri.')
                    ->action(function (Artist $record) {
                        $artistName = static::getArtistName($record);
                        $record->update([
                            'marketplace_client_id' => null,
                            'is_partner' => false,
                            'partner_notes' => null,
                        ]);

                        Notification::make()
                            ->title('Artist eliminat')
                            ->body('"' . $artistName . '" a fost eliminat din lista de parteneri.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('edit_notes')
                    ->label('Note')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Artist $record): bool => $record->marketplace_client_id === $marketplace?->id)
                    ->modalHeading('Editează notele')
                    ->form([
                        Forms\Components\TextInput::make('artist_name')
                            ->label('Artist')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Note parteneriat')
                            ->rows(3),
                    ])
                    ->fillForm(fn (Artist $record): array => [
                        'artist_name' => static::getArtistName($record) . ($record->city ? ' - ' . $record->city : ''),
                        'partner_notes' => $record->partner_notes,
                    ])
                    ->action(function (Artist $record, array $data) {
                        $record->update([
                            'partner_notes' => $data['partner_notes'],
                        ]);

                        Notification::make()
                            ->title('Note actualizate')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkAction::make('bulk_add_partners')
                    ->label('Adaugă ca parteneri')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) use ($marketplace) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->marketplace_client_id !== $marketplace?->id) {
                                $record->update([
                                    'marketplace_client_id' => $marketplace?->id,
                                    'is_partner' => true,
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title($count . ' artiști adăugați')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nu există artiști disponibili')
            ->emptyStateDescription('Nu am găsit artiști disponibili pentru parteneriat.')
            ->defaultSort('name');
    }

    /**
     * Global table search - searches in name and city
     */
    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $search = $this->getTableSearch();

        if (blank($search)) {
            return $query;
        }

        $normalizedSearch = static::normalizeSearch($search);

        return $query->where(function (Builder $q) use ($normalizedSearch, $search) {
            // Search in name
            $q->whereRaw("LOWER(name) LIKE ?", ["%{$normalizedSearch}%"])
              // Also search in city
              ->orWhereRaw("LOWER(city) LIKE ?", ["%{$normalizedSearch}%"])
              // Fallback: also search with original (non-normalized) for exact matches
              ->orWhereRaw("LOWER(name) LIKE ?", ["%" . mb_strtolower($search) . "%"])
              ->orWhereRaw("LOWER(city) LIKE ?", ["%" . mb_strtolower($search) . "%"]);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_artist')
                ->label('Adaugă artist nou')
                ->icon('heroicon-o-plus')
                ->url(fn () => \App\Filament\Marketplace\Resources\ArtistResource::getUrl('create')),
        ];
    }
}
