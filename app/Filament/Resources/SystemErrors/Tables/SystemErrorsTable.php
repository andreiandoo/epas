<?php

namespace App\Filament\Resources\SystemErrors\Tables;

use App\Models\SystemError;
use App\Support\SearchHelper;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SystemErrorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll(config('system_errors.polling.table', 15) . 's')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100, 250])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->size(\Filament\Support\Enums\TextSize::Small),
                TextColumn::make('level_name')
                    ->label('Level')
                    ->badge()
                    ->color(fn (SystemError $r) => $r->severityColor())
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subcategory')
                    ->label('Subcategory')
                    ->toggleable()
                    ->color('gray'),
                TextColumn::make('source')
                    ->label('Source')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(120)
                    ->tooltip(fn (SystemError $r) => mb_substr($r->message ?? '', 0, 600))
                    ->searchable(query: function (Builder $query, string $search) {
                        return SearchHelper::search($query, 'message', $search);
                    }),
                TextColumn::make('exception_class')
                    ->label('Exception')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
                TextColumn::make('request_url')
                    ->label('URL')
                    ->limit(40)
                    ->toggleable()
                    ->tooltip(fn (SystemError $r) => $r->request_url),
                TextColumn::make('request_user_type')
                    ->label('User')
                    ->formatStateUsing(fn (SystemError $r) => $r->request_user_type
                        ? $r->request_user_type . ' #' . ($r->request_user_id ?? '?')
                        : null)
                    ->toggleable(),
                TextColumn::make('fingerprint')
                    ->label('Fingerprint')
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                TextColumn::make('acknowledged_at')
                    ->label('Acked')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('time_range')
                    ->label('Time range')
                    ->options([
                        '1h' => 'Last hour',
                        '24h' => 'Last 24 hours',
                        '7d' => 'Last 7 days',
                        '30d' => 'Last 30 days',
                    ])
                    ->default('24h')
                    ->query(function (Builder $query, array $data) {
                        $threshold = match ($data['value'] ?? null) {
                            '1h' => now()->subHour(),
                            '24h' => now()->subDay(),
                            '7d' => now()->subDays(7),
                            '30d' => now()->subDays(30),
                            default => null,
                        };
                        if ($threshold) {
                            $query->where('created_at', '>=', $threshold);
                        }
                    }),
                SelectFilter::make('level')
                    ->label('Severity')
                    ->multiple()
                    ->options([
                        '600' => 'Emergency',
                        '550' => 'Alert',
                        '500' => 'Critical',
                        '400' => 'Error',
                        '300' => 'Warning',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereIn('level', $data['values']);
                        }
                    }),
                SelectFilter::make('category')
                    ->label('Category')
                    ->multiple()
                    ->options(self::categoryOptions())
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereIn('category', $data['values']);
                        }
                    }),
                SelectFilter::make('source')
                    ->label('Source')
                    ->multiple()
                    ->options([
                        'log' => 'Log',
                        'exception' => 'Exception',
                        'failed_job' => 'Failed Job',
                        'email_log' => 'Email Log',
                        'marketplace_email_log' => 'Marketplace Email',
                        'webhook_log' => 'Webhook',
                        'order_status' => 'Order Status',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereIn('source', $data['values']);
                        }
                    }),
                SelectFilter::make('acknowledged')
                    ->label('Acknowledged')
                    ->options([
                        'no' => 'Hide acknowledged',
                        'only' => 'Only acknowledged',
                        'all' => 'Show all',
                    ])
                    ->default('no')
                    ->query(function (Builder $query, array $data) {
                        match ($data['value'] ?? 'no') {
                            'no' => $query->whereNull('acknowledged_at'),
                            'only' => $query->whereNotNull('acknowledged_at'),
                            default => null,
                        };
                    }),
                Filter::make('fingerprint_filter')
                    ->form([
                        Forms\Components\TextInput::make('fingerprint')
                            ->label('Fingerprint (sha1)')
                            ->placeholder('a3f2…'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['fingerprint'])) {
                            $query->where('fingerprint', 'like', $data['fingerprint'] . '%');
                        }
                    }),
                Filter::make('custom_range')
                    ->form([
                        Forms\Components\DateTimePicker::make('from'),
                        Forms\Components\DateTimePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['from'])) {
                            $query->where('created_at', '>=', $data['from']);
                        }
                        if (!empty($data['until'])) {
                            $query->where('created_at', '<=', $data['until']);
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make()->slideOver(),
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SystemError $r) => !$r->isAcknowledged())
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Note (optional)')
                            ->rows(3),
                    ])
                    ->action(function (SystemError $record, array $data) {
                        $record->update([
                            'acknowledged_at' => now(),
                            'acknowledged_by' => auth()->id(),
                            'acknowledged_note' => $data['note'] ?? null,
                        ]);
                        Notification::make()->success()->title('Acknowledged')->send();
                    }),
                Action::make('similar')
                    ->label('Open similar')
                    ->icon('heroicon-o-square-2-stack')
                    ->color('gray')
                    ->url(fn (SystemError $r) => route('filament.admin.resources.system-errors.index', [
                        'tableFilters[fingerprint_filter][fingerprint]' => $r->fingerprint,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_acknowledge')
                        ->label('Acknowledge selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('note')
                                ->label('Note (optional)')
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->isAcknowledged()) {
                                    $record->update([
                                        'acknowledged_at' => now(),
                                        'acknowledged_by' => auth()->id(),
                                        'acknowledged_note' => $data['note'] ?? null,
                                    ]);
                                    $count++;
                                }
                            }
                            Notification::make()->success()->title("{$count} errors acknowledged")->send();
                        }),
                ]),
            ])
            ->recordUrl(fn (SystemError $r) => route('filament.admin.resources.system-errors.view', $r));
    }

    public static function categoryOptions(): array
    {
        return [
            'auth' => 'Auth',
            'payment' => 'Payment',
            'email' => 'Email',
            'database' => 'Database',
            'external_api' => 'External API',
            'queue' => 'Queue',
            'pdf' => 'PDF',
            'seating' => 'Seating',
            'security' => 'Security',
            'validation' => 'Validation',
            'storage' => 'Storage',
            'integration' => 'Integration',
            'cron' => 'Cron',
            'marketplace' => 'Marketplace',
            'app' => 'App',
            'unknown' => 'Unknown',
        ];
    }
}
