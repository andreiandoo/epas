<?php

namespace App\Filament\Resources\SystemErrors\Tables;

use App\Filament\Resources\SystemErrors\SystemErrorResource;
use App\Models\SystemError;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SystemErrorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100, 250])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('level_name')
                    ->label('Level')
                    ->badge()
                    ->color(fn (SystemError $r) => $r->severityColor()),
                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('subcategory')
                    ->label('Subcategory')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Source')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(120)
                    ->tooltip(fn (SystemError $r) => mb_substr((string) $r->message, 0, 600))
                    ->searchable(),
                TextColumn::make('exception_class')
                    ->label('Exception')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_url')
                    ->label('URL')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('fingerprint')
                    ->label('Fingerprint')
                    ->limit(8)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acknowledged_at')
                    ->label('Acked')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Allow ?fp=<fingerprint> URL param to pre-scope the list
                // (used by "Open similar" links on the View page and per-row).
                $fp = request()->query('fp');
                if (is_string($fp) && $fp !== '') {
                    $query->where('fingerprint', $fp);
                }
            })
            ->recordActions([
                ViewAction::make(),

                Action::make('open_similar')
                    ->label('Similar')
                    ->icon('heroicon-o-square-2-stack')
                    ->color('gray')
                    ->url(fn (SystemError $r) => SystemErrorResource::getUrl('index') . '?fp=' . $r->fingerprint),

                Action::make('delete_similar')
                    ->label('Delete similar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete all errors with this fingerprint?')
                    ->modalDescription(fn (SystemError $r) => sprintf(
                        'There are %s errors sharing this fingerprint. All will be permanently deleted.',
                        number_format(SystemError::query()->where('fingerprint', $r->fingerprint)->count())
                    ))
                    ->modalSubmitActionLabel('Delete all')
                    ->action(function (SystemError $r) {
                        $deleted = DB::table('system_errors')->where('fingerprint', $r->fingerprint)->delete();
                        Notification::make()->success()->title("Deleted {$deleted} errors")->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected errors?')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $r) {
                                DB::table('system_errors')->where('id', $r->id)->delete();
                                $count++;
                            }
                            Notification::make()->success()->title("Deleted {$count} errors")->send();
                        }),
                    BulkAction::make('bulk_delete_by_fingerprint')
                        ->label('Delete all similar (same fingerprint)')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete every error sharing the fingerprints of selected rows?')
                        ->modalDescription('For each selected row, every other row with the same fingerprint will also be deleted. Use this to clear bulk noise.')
                        ->action(function ($records) {
                            $fingerprints = collect($records)->pluck('fingerprint')->unique()->filter()->values();
                            if ($fingerprints->isEmpty()) {
                                Notification::make()->warning()->title('No fingerprints in selection')->send();
                                return;
                            }
                            $deleted = DB::table('system_errors')
                                ->whereIn('fingerprint', $fingerprints->all())
                                ->delete();
                            Notification::make()->success()
                                ->title("Deleted {$deleted} errors across " . $fingerprints->count() . ' fingerprint(s)')
                                ->send();
                        }),
                    BulkAction::make('bulk_acknowledge')
                        ->label('Acknowledge selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $r) {
                                if (!$r->isAcknowledged()) {
                                    $r->update([
                                        'acknowledged_at' => now(),
                                        'acknowledged_by' => auth()->id(),
                                    ]);
                                    $count++;
                                }
                            }
                            Notification::make()->success()->title("{$count} errors acknowledged")->send();
                        }),
                ]),
            ])
            ->recordUrl(fn (SystemError $r) => SystemErrorResource::getUrl('view', ['record' => $r]));
    }
}
