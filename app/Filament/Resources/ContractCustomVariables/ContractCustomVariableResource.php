<?php

namespace App\Filament\Resources\ContractCustomVariables;

use App\Models\ContractCustomVariable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractCustomVariableResource extends Resource
{
    protected static ?string $model = ContractCustomVariable::class;

    protected static ?string $navigationIcon = 'heroicon-o-variable';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Contract Variables';

    protected static ?int $navigationSort = 32;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Variable Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Variable Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in templates as {{variable_name}}')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('key', '{{' . $state . '}}');
                            }),

                        Forms\Components\TextInput::make('key')
                            ->label('Template Placeholder')
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('Copy this into your contract template'),

                        Forms\Components\TextInput::make('label')
                            ->label('Display Label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Shown to admins when editing tenant'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->helperText('Help text shown to admins'),

                        Forms\Components\Select::make('type')
                            ->label('Input Type')
                            ->options([
                                'text' => 'Text',
                                'number' => 'Number',
                                'date' => 'Date',
                                'select' => 'Select/Dropdown',
                                'textarea' => 'Textarea',
                            ])
                            ->default('text')
                            ->required()
                            ->live(),

                        Forms\Components\TagsInput::make('options')
                            ->label('Select Options')
                            ->visible(fn ($get) => $get('type') === 'select')
                            ->helperText('Add options for the dropdown'),

                        Forms\Components\TextInput::make('default_value')
                            ->label('Default Value'),

                        Forms\Components\Toggle::make('is_required')
                            ->label('Required')
                            ->helperText('Must be filled before generating contract'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Variable')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->label('Placeholder')
                    ->copyable()
                    ->copyMessage('Copied!'),

                Tables\Columns\TextColumn::make('label')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractCustomVariables::route('/'),
            'create' => Pages\CreateContractCustomVariable::route('/create'),
            'edit' => Pages\EditContractCustomVariable::route('/{record}/edit'),
        ];
    }
}
