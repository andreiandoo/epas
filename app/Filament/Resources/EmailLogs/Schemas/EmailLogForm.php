<?php

namespace App\Filament\Resources\EmailLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EmailLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email_template_id')
                    ->email()
                    ->numeric(),
                TextInput::make('recipient_email')
                    ->email()
                    ->required(),
                TextInput::make('recipient_name'),
                TextInput::make('subject')
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('sent_at'),
                DateTimePicker::make('failed_at'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                TextInput::make('metadata'),
                Select::make('tenant_id')
                    ->relationship('tenant', 'name'),
            ]);
    }
}
