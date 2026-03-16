<?php

namespace App\Filament\Resources\WebTemplates\RelationManagers;

use App\Models\WebTemplateCustomization;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;

class CustomizationsRelationManager extends RelationManager
{
    protected static string $relationship = 'customizations';
    protected static ?string $title = 'Personalizări';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('label')
                ->label('Denumire')
                ->maxLength(255),

            Forms\Components\Select::make('tenant_id')
                ->label('Tenant')
                ->relationship('tenant', 'public_name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'draft' => 'Draft',
                    'active' => 'Activ',
                    'expired' => 'Expirat',
                ])
                ->default('active')
                ->required(),

            Forms\Components\KeyValue::make('customization_data')
                ->label('Date Personalizate')
                ->keyLabel('Cheie')
                ->valueLabel('Valoare')
                ->columnSpanFull(),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Denumire')
                    ->placeholder('(fără denumire)'),

                Tables\Columns\TextColumn::make('unique_token')
                    ->label('Token')
                    ->badge()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('viewed_count')
                    ->label('Vizualizări'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat')
                    ->dateTime('d.m.Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('openPreview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(function (WebTemplateCustomization $record) {
                        return route('web-template.customized-preview', [
                            'templateSlug' => $record->template->slug,
                            'token' => $record->unique_token,
                        ]);
                    })
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adaugă Personalizare'),
            ]);
    }
}
