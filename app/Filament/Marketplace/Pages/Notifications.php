<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceNotification;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Notifications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notificari';
    protected static ?string $title = 'Notificari';
    protected static ?string $slug = 'notifications';
    protected static ?int $navigationSort = 100;

    // Hide from navigation since we access it through the dropdown
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.marketplace.pages.notifications';

    protected function getTableQuery(): Builder
    {
        $marketplaceClientId = $this->getMarketplaceClientId();

        return MarketplaceNotification::query()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->orderByDesc('created_at');
    }

    protected function getTableColumns(): array
    {
        return [
            IconColumn::make('read_status')
                ->label('')
                ->state(fn (MarketplaceNotification $record) => $record->isRead() ? 'read' : 'unread')
                ->icon(fn (string $state) => $state === 'unread' ? 'heroicon-s-circle' : 'heroicon-o-circle')
                ->color(fn (string $state) => $state === 'unread' ? 'primary' : 'gray')
                ->size('sm'),

            TextColumn::make('title')
                ->label('Titlu')
                ->description(fn (MarketplaceNotification $record) => $record->message)
                ->searchable()
                ->weight(fn (MarketplaceNotification $record) => $record->isRead() ? 'normal' : 'bold'),

            TextColumn::make('type_label')
                ->label('Tip')
                ->badge()
                ->color(fn (MarketplaceNotification $record) => match($record->color ?? $record->default_color) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'danger' => 'danger',
                    'info' => 'info',
                    default => 'primary',
                }),

            TextColumn::make('created_at')
                ->label('Data')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->description(fn (MarketplaceNotification $record) => $record->time_ago),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('type')
                ->label('Tip notificare')
                ->options(MarketplaceNotification::getTypeLabels()),

            SelectFilter::make('read_status')
                ->label('Status')
                ->options([
                    'unread' => 'Necitite',
                    'read' => 'Citite',
                ])
                ->query(function (Builder $query, array $data) {
                    if ($data['value'] === 'unread') {
                        $query->unread();
                    } elseif ($data['value'] === 'read') {
                        $query->read();
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('mark_read')
                ->label('Marcheaza citit')
                ->icon('heroicon-o-check')
                ->visible(fn (MarketplaceNotification $record) => !$record->isRead())
                ->action(fn (MarketplaceNotification $record) => $record->markAsRead()),

            Action::make('mark_unread')
                ->label('Marcheaza necitit')
                ->icon('heroicon-o-x-mark')
                ->visible(fn (MarketplaceNotification $record) => $record->isRead())
                ->action(fn (MarketplaceNotification $record) => $record->markAsUnread()),

            Action::make('view')
                ->label('Vezi')
                ->icon('heroicon-o-eye')
                ->url(fn (MarketplaceNotification $record) => $record->action_url)
                ->visible(fn (MarketplaceNotification $record) => $record->action_url !== null)
                ->openUrlInNewTab(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('mark_read')
                ->label('Marcheaza citite')
                ->icon('heroicon-o-check')
                ->action(fn (Collection $records) => $records->each->markAsRead())
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('mark_unread')
                ->label('Marcheaza necitite')
                ->icon('heroicon-o-x-mark')
                ->action(fn (Collection $records) => $records->each->markAsUnread())
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('delete')
                ->label('Sterge')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn (Collection $records) => $records->each->delete())
                ->deselectRecordsAfterCompletion(),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('mark_all_read')
                ->label('Marcheaza toate citite')
                ->icon('heroicon-o-check-circle')
                ->action(function () {
                    MarketplaceNotification::where('marketplace_client_id', $this->getMarketplaceClientId())
                        ->unread()
                        ->update(['read_at' => now()]);
                }),
        ];
    }

    protected function getMarketplaceClientId(): ?int
    {
        $user = filament()->auth()->user() ?? auth('marketplace_admin')->user();

        if ($user && isset($user->marketplace_client_id)) {
            return $user->marketplace_client_id;
        }

        if (session('super_admin_marketplace_client_id')) {
            return session('super_admin_marketplace_client_id');
        }

        return null;
    }

    public function getTableRecordKey($record): string
    {
        return (string) $record->id;
    }
}
