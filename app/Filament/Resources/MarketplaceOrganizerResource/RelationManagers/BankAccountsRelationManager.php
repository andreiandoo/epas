<?php

namespace App\Filament\Resources\MarketplaceOrganizerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Schemas\Schema;

class BankAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'bankAccounts';
    protected static ?string $title = 'Bank Accounts';
    protected static ?string $recordTitleAttribute = 'iban';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('bank_name')
                    ->label('Bank Name')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('iban')
                    ->label('IBAN')
                    ->required()
                    ->maxLength(34),

                Forms\Components\TextInput::make('account_holder')
                    ->label('Account Holder')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Account'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable(),

                Tables\Columns\TextColumn::make('iban')
                    ->label('IBAN')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('account_holder')
                    ->label('Holder')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->recordActions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('is_primary', 'desc');
    }
}
