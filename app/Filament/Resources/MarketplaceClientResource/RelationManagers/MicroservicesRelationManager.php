<?php

namespace App\Filament\Resources\MarketplaceClientResource\RelationManagers;

use App\Models\Microservice;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MicroservicesRelationManager extends RelationManager
{
    protected static string $relationship = 'microservices';

    protected static ?string $title = 'Microservices';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Enable or disable this microservice for the marketplace'),

                Forms\Components\DatePicker::make('expires_at')
                    ->label('Expires At')
                    ->helperText('Leave empty for no expiration'),

                Forms\Components\KeyValue::make('configuration')
                    ->label('Configuration')
                    ->helperText('Custom configuration for this microservice')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\ImageColumn::make('icon_image')
                    ->label('')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Microservice')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->short_description),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activated')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Microservice')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('is_active', true))
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Expires At'),
                        Forms\Components\KeyValue::make('configuration')
                            ->label('Configuration'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['activated_at'] = now();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Edit Microservice Settings'),
                DetachAction::make()
                    ->label('Remove'),
            ])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ])
            ->emptyStateHeading('No microservices enabled')
            ->emptyStateDescription('Add microservices to enable additional features for this marketplace.')
            ->emptyStateActions([
                AttachAction::make()
                    ->label('Add First Microservice')
                    ->preloadRecordSelect(),
            ]);
    }
}
