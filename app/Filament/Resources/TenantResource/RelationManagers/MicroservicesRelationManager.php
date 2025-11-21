<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\Microservice;
use App\Models\TenantMicroservice;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class MicroservicesRelationManager extends RelationManager
{
    protected static string $relationship = 'microservices';

    protected static ?string $title = 'Microservices';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('microservice_id')
                    ->label('Microservice')
                    ->options(Microservice::active()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Forms\Components\KeyValue::make('settings')
                    ->label('Settings')
                    ->keyLabel('Setting')
                    ->valueLabel('Value'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('microservice.name')
            ->columns([
                Tables\Columns\TextColumn::make('microservice.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('microservice.category')
                    ->label('Category')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\IconColumn::make('microservice.is_premium')
                    ->boolean()
                    ->label('Premium')
                    ->toggleable(),

                // Usage stats columns
                Tables\Columns\TextColumn::make('usage_stats.api_calls')
                    ->label('API Calls')
                    ->default(0)
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderByRaw("(usage_stats->>'api_calls')::int {$direction}");
                    }),
                Tables\Columns\TextColumn::make('usage_stats.events_processed')
                    ->label('Events')
                    ->default(0),
                Tables\Columns\TextColumn::make('usage_stats.last_used')
                    ->label('Last Used')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->diffForHumans() : 'Never')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('activated_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Activated'),
                Tables\Columns\TextColumn::make('deactivated_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Deactivated')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('microservice.category')
                    ->label('Category')
                    ->relationship('microservice', 'category'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Microservice')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['activated_at'] = now();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // Toggle active status
                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (TenantMicroservice $record) {
                            $record->activate();

                            Notification::make()
                                ->title('Microservice activated')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (TenantMicroservice $record) => !$record->is_active),

                    Tables\Actions\Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-pause')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Microservice')
                        ->modalDescription('The tenant will lose access to this microservice. Their data will be preserved.')
                        ->action(function (TenantMicroservice $record) {
                            $record->deactivate();

                            Notification::make()
                                ->title('Microservice deactivated')
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (TenantMicroservice $record) => $record->is_active),

                    // View detailed stats
                    Tables\Actions\Action::make('viewStats')
                        ->label('View Stats')
                        ->icon('heroicon-o-chart-bar')
                        ->color('info')
                        ->modalHeading('Usage Statistics')
                        ->modalContent(fn (TenantMicroservice $record) => view('filament.modals.microservice-stats', [
                            'microservice' => $record,
                        ]))
                        ->modalSubmitAction(false),

                    // Reset stats
                    Tables\Actions\Action::make('resetStats')
                        ->label('Reset Stats')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reset Usage Statistics')
                        ->modalDescription('This will reset all usage statistics for this microservice. This action cannot be undone.')
                        ->action(function (TenantMicroservice $record) {
                            $record->update(['usage_stats' => []]);

                            Notification::make()
                                ->title('Statistics reset')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkActivate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->activate()),

                    Tables\Actions\BulkAction::make('bulkDeactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->deactivate()),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
