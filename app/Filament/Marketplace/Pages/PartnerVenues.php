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

    public function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();

        return $table
            ->query(
                Venue::query()
                    // Show venues that are NOT already associated with this marketplace
                    ->whereNull('marketplace_client_id')
                    // Or show venues that are associated but as partners only (not created by marketplace)
                    ->orWhere(function (Builder $query) use ($marketplace) {
                        $query->where('marketplace_client_id', $marketplace?->id)
                            ->where('is_partner', true);
                    })
            )
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Imagine')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=V&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('capacity_total')
                    ->label('Capacitate')
                    ->numeric(),
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
                    ->placeholder('Toate')
                    ->trueLabel('Partenere')
                    ->falseLabel('Disponibile')
                    ->queries(
                        true: fn (Builder $query) => $query->where('marketplace_client_id', $marketplace?->id),
                        false: fn (Builder $query) => $query->whereNull('marketplace_client_id'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('city')
                    ->label('Oraș')
                    ->options(fn () => Venue::whereNull('marketplace_client_id')
                        ->orWhere('marketplace_client_id', $marketplace?->id)
                        ->whereNotNull('city')
                        ->distinct()
                        ->pluck('city', 'city')
                        ->toArray()
                    )
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('add_partner')
                    ->label('Adaugă partener')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->marketplace_client_id !== $marketplace?->id)
                    ->requiresConfirmation()
                    ->modalHeading('Adaugă locație ca partener')
                    ->modalDescription(fn ($record) => 'Ești sigur că vrei să adaugi "' . ($record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en')) . '" ca locație parteneră?')
                    ->form([
                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Note parteneriat (opțional)')
                            ->placeholder('Note interne despre acest parteneriat...')
                            ->rows(3),
                    ])
                    ->action(function (Venue $record, array $data) use ($marketplace) {
                        $record->update([
                            'marketplace_client_id' => $marketplace?->id,
                            'is_partner' => true,
                            'partner_notes' => $data['partner_notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Locație adăugată')
                            ->body('Locația a fost adăugată ca partener.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('remove_partner')
                    ->label('Elimină partener')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->marketplace_client_id === $marketplace?->id && $record->is_partner)
                    ->requiresConfirmation()
                    ->modalHeading('Elimină locația din parteneri')
                    ->modalDescription(fn ($record) => 'Ești sigur că vrei să elimini "' . ($record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en')) . '" din lista de parteneri?')
                    ->action(function (Venue $record) {
                        $record->update([
                            'marketplace_client_id' => null,
                            'is_partner' => false,
                            'partner_notes' => null,
                        ]);

                        Notification::make()
                            ->title('Locație eliminată')
                            ->body('Locația a fost eliminată din lista de parteneri.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('edit_notes')
                    ->label('Note')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn ($record) => $record->marketplace_client_id === $marketplace?->id)
                    ->form([
                        Forms\Components\Textarea::make('partner_notes')
                            ->label('Note parteneriat')
                            ->default(fn ($record) => $record->partner_notes)
                            ->rows(3),
                    ])
                    ->action(function (Venue $record, array $data) {
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
                Tables\Actions\BulkAction::make('bulk_add_partners')
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
                            ->title($count . ' locații adăugate')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nu există locații disponibile')
            ->emptyStateDescription('Nu am găsit locații disponibile pentru parteneriat.')
            ->defaultSort('city');
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
