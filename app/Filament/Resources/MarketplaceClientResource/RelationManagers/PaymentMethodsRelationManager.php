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

class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'microservices';

    protected static ?string $title = 'Payment Methods';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Build dynamic form fields based on the microservice's settings_schema
     */
    protected function getSettingsFormFields(?Microservice $microservice = null): array
    {
        if (!$microservice) {
            return [];
        }

        $schema = $microservice->metadata['settings_schema'] ?? [];
        $fields = [];

        foreach ($schema as $field) {
            $component = match ($field['type'] ?? 'text') {
                'text' => Forms\Components\TextInput::make("settings.{$field['key']}")
                    ->label($field['label']),
                'password' => Forms\Components\TextInput::make("settings.{$field['key']}")
                    ->label($field['label'])
                    ->password()
                    ->revealable(),
                'textarea' => Forms\Components\Textarea::make("settings.{$field['key']}")
                    ->label($field['label'])
                    ->rows(3),
                'number' => Forms\Components\TextInput::make("settings.{$field['key']}")
                    ->label($field['label'])
                    ->numeric(),
                'boolean' => Forms\Components\Toggle::make("settings.{$field['key']}")
                    ->label($field['label'])
                    ->default($field['default'] ?? false),
                'select' => Forms\Components\Select::make("settings.{$field['key']}")
                    ->label($field['label'])
                    ->options(array_combine($field['options'] ?? [], $field['options'] ?? [])),
                default => Forms\Components\TextInput::make("settings.{$field['key']}")
                    ->label($field['label']),
            };

            if ($field['required'] ?? false) {
                $component->required();
            }

            $fields[] = $component;
        }

        return $fields;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Status & Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable this payment method for the marketplace'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Payment Method')
                            ->helperText('Set as the default payment option'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Expires At')
                            ->helperText('Leave empty for no expiration'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payment Gateway Settings')
                    ->description('Configure the payment gateway credentials and options')
                    ->schema(function ($record) {
                        if ($record) {
                            return $this->getSettingsFormFields($record);
                        }
                        return [
                            Forms\Components\Placeholder::make('info')
                                ->content('Settings will be available after selecting a payment method'),
                        ];
                    })
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->where('category', 'payment'))
            ->columns([
                Tables\Columns\ImageColumn::make('icon_image')
                    ->label('')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Payment Method')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? $state['ro'] ?? reset($state)) : $state)
                    ->description(fn ($record) => $record->getTranslation('short_description', app()->getLocale()) ?? ''),

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

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('configured')
                    ->label('Configured')
                    ->getStateUsing(function ($record) {
                        $schema = $record->metadata['settings_schema'] ?? [];
                        $settings = $record->pivot?->settings ?? [];

                        foreach ($schema as $field) {
                            if (($field['required'] ?? false) && empty($settings[$field['key']] ?? null)) {
                                return false;
                            }
                        }
                        return true;
                    })
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn ($state) => $state ? 'All required settings configured' : 'Missing required settings'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Payment Method')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('category', 'payment')->where('is_active', true))
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Payment Method')
                            ->helperText('Select a payment gateway to enable'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['activated_at'] = now();
                        $data['status'] = $data['is_active'] ? 'active' : 'inactive';
                        return $data;
                    })
                    ->after(function ($record, $data) {
                        // If this is set as default, unset others
                        if ($data['is_default'] ?? false) {
                            $this->getOwnerRecord()->microservices()
                                ->where('category', 'payment')
                                ->where('microservices.id', '!=', $record->id)
                                ->each(function ($ms) {
                                    $this->getOwnerRecord()->microservices()
                                        ->updateExistingPivot($ms->id, ['is_default' => false]);
                                });
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Configure')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->modalHeading(fn ($record) => 'Configure ' . ($record->getTranslation('name', 'en') ?? $record->name))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = ($data['is_active'] ?? false) ? 'active' : 'inactive';
                        return $data;
                    })
                    ->after(function ($record, $data) {
                        // If this is set as default, unset others
                        if ($data['is_default'] ?? false) {
                            $this->getOwnerRecord()->microservices()
                                ->where('category', 'payment')
                                ->where('microservices.id', '!=', $record->id)
                                ->each(function ($ms) {
                                    $this->getOwnerRecord()->microservices()
                                        ->updateExistingPivot($ms->id, ['is_default' => false]);
                                });
                        }
                    }),

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
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading('No payment methods enabled')
            ->emptyStateDescription('Add payment methods to allow customers to pay for tickets.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateActions([
                AttachAction::make()
                    ->label('Add First Payment Method')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('category', 'payment')->where('is_active', true)),
            ]);
    }
}
