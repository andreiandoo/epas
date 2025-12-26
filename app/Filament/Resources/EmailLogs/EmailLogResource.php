<?php

namespace App\Filament\Resources\EmailLogs;

use App\Filament\Resources\EmailLogs\Pages\ListEmailLogs;
use App\Filament\Resources\EmailLogs\Pages\ViewEmailLog;
use App\Filament\Resources\EmailLogs\Schemas\EmailLogForm;
use App\Filament\Resources\EmailLogs\Tables\EmailLogsTable;
use App\Models\EmailLog;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';
    protected static UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 20;
    protected static BackedEnum|string|null $navigationLabel = 'Email History';
    protected static ?string $modelLabel = 'Email Log';
    protected static ?string $pluralModelLabel = 'Emails History';

    public static function form(Schema $schema): Schema
    {
        return EmailLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false; // Disable creating email logs manually
    }

    public static function canEdit($record): bool
    {
        return false; // Disable editing email logs
    }

    public static function canDelete($record): bool
    {
        return false; // Disable deleting email logs (for audit)
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailLogs::route('/'),
            'view' => ViewEmailLog::route('/{record}'),
        ];
    }
}
