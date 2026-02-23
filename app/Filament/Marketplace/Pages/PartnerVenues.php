<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Venue;
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

class PartnerVenues extends Page implements HasForms, HasTable
{
    use HasMarketplaceContext;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Locații Partenere';
    protected static ?string $title = 'Locații Partenere';
    protected static ?string $navigationParentItem = 'Venues';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.marketplace.pages.partner-venues';

    /**
     * Normalize search string - lowercase and remove diacritics
     */
    protected static function normalizeSearch(string $search): string
    {
        $search = mb_strtolower($search);
        $diacritics = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
                       'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ț' => 't'];
        return strtr($search, $diacritics);
    }

    /**
     * Get venue display name
     */
    protected static function getVenueName(Venue $venue): string
    {
        return $venue->getTranslation('name', 'ro') ?? $venue->getTranslation('name', 'en') ?? 'Locație';
    }

    public function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();

        return $table
            ->query(
                // Show ALL venues — both those already partnered and those available
                Venue::query()
            )
            ->searchable()
            ->searchPlaceholder('Caută locații...')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Imagine')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=V&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->getStateUsing(fn ($record) => static::getVenueName($record))
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity_total')
                    ->label('Capacitate')
                    ->numeric(),
                Tables\Columns\IconColumn::make('is_partner_status')
                    ->label('Partener')
                    ->state(fn ($record) => $record->isInMarketplace($marketplace?->id ?? 0))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('partner_status')
                    ->label('Status')
                    ->placeholder('Toate')
                    ->trueLabel('Partenere')
                    ->falseLabel('Disponibile')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas(
                            'marketplaceClients',
                            fn (Builder $q) => $q->where('marketplace_client_id', $marketplace?->id)
                        ),
                        false: fn (Builder $query) => $query->whereDoesntHave(
                            'marketplaceClients',
                            fn (Builder $q) => $q->where('marketplace_client_id', $marketplace?->id)
                        ),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('city')
                    ->label('Oraș')
                    ->options(fn () => Venue::whereNotNull('city')
                        ->where('city', '!=', '')
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
                    ->visible(fn (Venue $record): bool => !$record->isInMarketplace($marketplace?->id ?? 0))
                    ->modalHeading('Adaugă locație ca partener')
                    ->modalDescription('Confirmă adăugarea acestei locații ca partener.')
                    ->form([
                        Forms\Components\TextInput::make('venue_name')
                            ->label('Locație')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Note parteneriat (opțional)')
                            ->placeholder('Note interne despre acest parteneriat...')
                            ->rows(3),
                    ])
                    ->fillForm(fn (Venue $record): array => [
                        'venue_name' => static::getVenueName($record) . ($record->city ? ' - ' . $record->city : ''),
                    ])
                    ->action(function (Venue $record, array $data) use ($marketplace) {
                        $venueName = static::getVenueName($record);
                        // Attach to this marketplace via pivot (other marketplace links are preserved)
                        $record->marketplaceClients()->syncWithoutDetaching([
                            $marketplace?->id => [
                                'is_partner'    => true,
                                'partner_notes' => $data['partner_notes'] ?? null,
                            ],
                        ]);

                        Notification::make()
                            ->title('Locație adăugată')
                            ->body('"' . $venueName . '" a fost adăugată ca partener.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('remove_partner')
                    ->label('Elimină')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Venue $record): bool => $record->isInMarketplace($marketplace?->id ?? 0))
                    ->requiresConfirmation()
                    ->modalHeading('Elimină locația din parteneri')
                    ->modalDescription('Această acțiune va elimina locația din lista de parteneri. Alte marketplace-uri nu sunt afectate.')
                    ->action(function (Venue $record) use ($marketplace) {
                        $venueName = static::getVenueName($record);
                        // Detach only from this marketplace; other marketplace links are preserved
                        $record->marketplaceClients()->detach($marketplace?->id);

                        Notification::make()
                            ->title('Locație eliminată')
                            ->body('"' . $venueName . '" a fost eliminată din lista de parteneri.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('edit_notes')
                    ->label('Note')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Venue $record): bool => $record->isInMarketplace($marketplace?->id ?? 0))
                    ->modalHeading('Editează notele')
                    ->form([
                        Forms\Components\TextInput::make('venue_name')
                            ->label('Locație')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Note parteneriat')
                            ->rows(3),
                    ])
                    ->fillForm(function (Venue $record) use ($marketplace): array {
                        $pivot = $record->marketplaceClients()
                            ->where('marketplace_client_id', $marketplace?->id)
                            ->first()?->pivot;
                        return [
                            'venue_name'    => static::getVenueName($record) . ($record->city ? ' - ' . $record->city : ''),
                            'partner_notes' => $pivot?->partner_notes,
                        ];
                    })
                    ->action(function (Venue $record, array $data) use ($marketplace) {
                        $record->marketplaceClients()->updateExistingPivot($marketplace?->id, [
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
                            if (!$record->isInMarketplace($marketplace?->id ?? 0)) {
                                $record->marketplaceClients()->syncWithoutDetaching([
                                    $marketplace?->id => ['is_partner' => true],
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title($count . ' locații adăugate')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nu există locații')
            ->emptyStateDescription('Nu am găsit locații în sistem.')
            ->defaultSort('city');
    }

    /**
     * Global table search - searches in name (JSON) and city
     */
    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $search = $this->getTableSearch();

        if (blank($search)) {
            return $query;
        }

        $normalizedSearch = static::normalizeSearch($search);

        return $query->where(function (Builder $q) use ($normalizedSearch, $search) {
            $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.ro'))) LIKE ?", ["%{$normalizedSearch}%"])
              ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", ["%{$normalizedSearch}%"])
              ->orWhereRaw("LOWER(city) LIKE ?", ["%{$normalizedSearch}%"])
              ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.ro'))) LIKE ?", ["%" . mb_strtolower($search) . "%"])
              ->orWhereRaw("LOWER(city) LIKE ?", ["%" . mb_strtolower($search) . "%"]);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_venue')
                ->label('Adaugă locație nouă')
                ->icon('heroicon-o-plus')
                ->url(fn () => \App\Filament\Marketplace\Resources\VenueResource::getUrl('create')),
        ];
    }
}
