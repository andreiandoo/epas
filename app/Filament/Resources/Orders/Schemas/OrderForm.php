<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('tenant_id')
                ->relationship('tenant', 'name')
                ->searchable()
                ->required(),

            TextInput::make('customer_email')
                ->email()
                ->required()
                ->maxLength(190),

            Select::make('customer_id')
                ->label('Customer')
                ->relationship('customer', 'email', fn ($query, $get) => $query->where('tenant_id', $get('tenant_id')))
                ->searchable()
                ->preload()
                ->afterStateUpdated(function ($state, callable $set) {
                    // dacă selectezi un customer, sync email-ul
                    if ($state) {
                        $email = \App\Models\Customer::find($state)?->email;
                        if ($email) {
                            $set('customer_email', $email);
                        }
                    }
                }),

            TextInput::make('customer_email')
                ->label('Customer email')
                ->email()
                ->required()
                ->helperText('Dacă schimbi email-ul, la salvare se va crea/asocia automat un Customer pe tenant.'),

            TextInput::make('total_cents')
                ->label('Total (cents)')
                ->numeric()
                ->disabled()
                ->helperText('Calculat automat din bilete; read-only.'),

            Select::make('status')
                ->options([
                    'pending'   => 'Pending',
                    'paid'      => 'Paid',
                    'cancelled' => 'Cancelled',
                    'refunded'  => 'Refunded',
                ])->required(),

            KeyValue::make('meta')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->columnSpanFull()
                ->addable()
                ->deletable()
                ->reorderable(),
        ])->columns(2);
    }
}
