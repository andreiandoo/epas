<?php

namespace App\Filament\Resources\SystemErrors;

use App\Filament\Resources\SystemErrors\Pages\ListSystemErrors;
use App\Filament\Resources\SystemErrors\Pages\ViewSystemError;
use App\Filament\Resources\SystemErrors\Tables\SystemErrorsTable;
use App\Models\SystemError;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

class SystemErrorResource extends Resource
{
    protected static ?string $model = SystemError::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static UnitEnum|string|null $navigationGroup = 'Operational';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'System Errors';
    protected static ?string $modelLabel = 'System Error';
    protected static ?string $pluralModelLabel = 'System Errors';
    protected static ?string $recordTitleAttribute = 'message';

    public static function table(Table $table): Table
    {
        return SystemErrorsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return SystemError::query();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemErrors::route('/'),
            'view' => ViewSystemError::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        // Soft-deleting individual rows isn't useful — pruning is automatic.
        return false;
    }

    /**
     * Red navigation badge with the count of unacknowledged critical+
     * errors over the past 24 hours.
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = SystemError::query()
                ->whereNull('acknowledged_at')
                ->where('level', '>=', 500)
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Throwable $e) {
            return null; // table may not yet exist before migration
        }
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
