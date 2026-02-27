<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ServiceOrderResource\Pages;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use Illuminate\Support\HtmlString;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;

class ServiceOrderResource extends Resource
{
    protected static ?string $model = ServiceOrder::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Comenzi servicii';

    protected static ?string $modelLabel = 'Service Order';

    protected static ?string $pluralModelLabel = 'Comenzi servicii';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        $count = static::getEloquentQuery()
            ->where('status', ServiceOrder::STATUS_PENDING_PAYMENT)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Order Information')
                    ->schema([
                        Forms\Components\Placeholder::make('order_number_display')
                            ->label('Order Number')
                            ->content(fn (?ServiceOrder $record): string => $record?->order_number ?? '-'),

                        Forms\Components\Placeholder::make('organizer_display')
                            ->label('Organizer')
                            ->content(fn (?ServiceOrder $record): string => $record?->organizer?->name ?? '-'),

                        Forms\Components\Placeholder::make('event_display')
                            ->label('Event')
                            ->content(fn (?ServiceOrder $record): string => $record?->event?->name ?? '-'),

                        Forms\Components\Placeholder::make('type_display')
                            ->label('Service Type')
                            ->content(fn (?ServiceOrder $record): string => $record?->service_type_label ?? '-'),

                        Forms\Components\Placeholder::make('locations_display')
                            ->label('Plasamente / Detalii')
                            ->columnSpanFull()
                            ->content(function (?ServiceOrder $record): string {
                                if (! $record) return '-';
                                $config = $record->config ?? [];
                                $locationLabels = [
                                    'home_hero'            => 'Prima pagina - Hero',
                                    'home_recommendations' => 'Prima pagina - Recomandari',
                                    'category'             => 'Pagina categorie',
                                    'city'                 => 'Pagina oras',
                                ];
                                return match ($record->service_type) {
                                    'featuring' => implode(', ', array_map(
                                        fn ($loc) => $locationLabels[$loc] ?? $loc,
                                        $config['locations'] ?? []
                                    )) ?: '-',
                                    'email' => (($config['audience_type'] ?? '') === 'own' ? 'Clientii tai' : 'Baza marketplace')
                                               . ' - ' . number_format((int) ($config['recipient_count'] ?? 0)) . ' destinatari',
                                    'tracking' => implode(', ', $config['platforms'] ?? [])
                                                  . ' (' . ($config['duration_months'] ?? 1) . ' luni)',
                                    'campaign' => ($config['campaign_type'] ?? 'custom') . ' - ' . number_format((float) ($config['budget'] ?? 0)) . ' RON',
                                    default => '-',
                                };
                            }),
                    ])
                    ->columns(4),

                Section::make('Pricing')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label('Subtotal')
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? number_format($record->subtotal, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('tax_display')
                            ->label('Tax (TVA)')
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? number_format($record->tax, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('total_display')
                            ->label('Total')
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? number_format($record->total, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('payment_status_display')
                            ->label('Payment Status')
                            ->content(fn (?ServiceOrder $record): string => $record?->payment_status_label ?? '-'),
                    ])
                    ->columns(4),

                Section::make('Status & Dates')
                    ->schema([
                        Forms\Components\Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?ServiceOrder $record): string => $record?->status_label ?? '-'),

                        Forms\Components\Placeholder::make('service_start_display')
                            ->label('Service Start')
                            ->content(function (?ServiceOrder $record): string {
                                if (!$record) return '-';
                                // For email: show scheduled_at (when emails should start sending)
                                if ($record->service_type === 'email' && $record->scheduled_at) {
                                    return $record->scheduled_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') . ' (RO)';
                                }
                                return $record->service_start_date?->format('Y-m-d') ?? '-';
                            }),

                        Forms\Components\Placeholder::make('service_end_display')
                            ->label('Service End')
                            ->content(function (?ServiceOrder $record): string {
                                if (!$record) return '-';
                                // For email: show executed_at (when sending completed)
                                if ($record->service_type === 'email' && $record->executed_at) {
                                    return $record->executed_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') . ' (RO)';
                                }
                                return $record->service_end_date?->format('Y-m-d') ?? '-';
                            }),

                        Forms\Components\Placeholder::make('created_display')
                            ->label('Created At')
                            ->content(fn (?ServiceOrder $record): string =>
                                $record?->created_at?->format('Y-m-d H:i') ?? '-'),
                    ])
                    ->columns(4),

                Section::make('Email Campaign Stats')
                    ->schema([
                        Forms\Components\Placeholder::make('email_stats_display')
                            ->label('')
                            ->columnSpanFull()
                            ->content(function (?ServiceOrder $record): HtmlString {
                                if (!$record) return new HtmlString('-');

                                $newsletter = $record->getLinkedNewsletter();
                                if (!$newsletter) return new HtmlString('<p class="text-sm text-gray-500">No newsletter linked yet.</p>');

                                $nl = $newsletter;
                                $statusColor = match ($nl->status) {
                                    'sent' => 'green', 'sending' => 'blue', 'scheduled' => 'yellow',
                                    'failed' => 'red', 'cancelled' => 'gray', default => 'gray',
                                };
                                $statusLabel = ucfirst($nl->status);

                                $openRate = $nl->sent_count > 0
                                    ? round(($nl->opened_count / $nl->sent_count) * 100, 1) . '%'
                                    : '0%';
                                $clickRate = $nl->opened_count > 0
                                    ? round(($nl->clicked_count / $nl->opened_count) * 100, 1) . '%'
                                    : '0%';

                                return new HtmlString("
                                    <div class='grid grid-cols-2 md:grid-cols-4 gap-4 mb-4'>
                                        <div class='bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-center'>
                                            <p class='text-2xl font-bold text-gray-900 dark:text-white'>{$nl->total_recipients}</p>
                                            <p class='text-xs text-gray-500'>Total Recipients</p>
                                        </div>
                                        <div class='bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center'>
                                            <p class='text-2xl font-bold text-green-600'>{$nl->sent_count}</p>
                                            <p class='text-xs text-gray-500'>Sent</p>
                                        </div>
                                        <div class='bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center'>
                                            <p class='text-2xl font-bold text-blue-600'>{$nl->opened_count}</p>
                                            <p class='text-xs text-gray-500'>Opened ({$openRate})</p>
                                        </div>
                                        <div class='bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 text-center'>
                                            <p class='text-2xl font-bold text-purple-600'>{$nl->clicked_count}</p>
                                            <p class='text-xs text-gray-500'>Clicked ({$clickRate})</p>
                                        </div>
                                    </div>
                                    <div class='grid grid-cols-3 gap-4'>
                                        <div class='bg-red-50 dark:bg-red-900/20 rounded-lg p-3 text-center'>
                                            <p class='text-lg font-bold text-red-600'>{$nl->failed_count}</p>
                                            <p class='text-xs text-gray-500'>Failed</p>
                                        </div>
                                        <div class='bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 text-center'>
                                            <p class='text-lg font-bold text-orange-600'>{$nl->unsubscribed_count}</p>
                                            <p class='text-xs text-gray-500'>Unsubscribed</p>
                                        </div>
                                        <div class='rounded-lg p-3 text-center'>
                                            <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-800'>{$statusLabel}</span>
                                            <p class='text-xs text-gray-500 mt-1'>Newsletter Status</p>
                                        </div>
                                    </div>
                                ");
                            }),
                    ])
                    ->visible(fn (?ServiceOrder $record): bool =>
                        $record?->service_type === 'email'
                    ),

                Section::make('Service Configuration')
                    ->schema([
                        Forms\Components\Placeholder::make('config_display')
                            ->label('Configuration')
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? json_encode($record->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'featuring' => 'primary',
                        'email' => 'success',
                        'tracking' => 'info',
                        'campaign' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'featuring' => 'Featuring',
                        'email' => 'Email',
                        'tracking' => 'Tracking',
                        'campaign' => 'Campaign',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_payment' => 'warning',
                        'processing' => 'info',
                        'active' => 'success',
                        'completed' => 'primary',
                        'cancelled' => 'danger',
                        'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'pending_payment' => 'Pending Payment',
                        'processing' => 'Processing',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('service_start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_payment' => 'Pending Payment',
                        'processing' => 'Processing',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),

                Tables\Filters\SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->options([
                        'featuring' => 'Featuring',
                        'email' => 'Email Marketing',
                        'tracking' => 'Ad Tracking',
                        'campaign' => 'Campaign Creation',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),

                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->recordActions([
                Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool =>
                        $record->payment_status === ServiceOrder::PAYMENT_PENDING &&
                        in_array($record->status, [ServiceOrder::STATUS_DRAFT, ServiceOrder::STATUS_PENDING_PAYMENT])
                    )
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        $record->markAsPaid($data['payment_reference']);
                    }),

                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool =>
                        $record->payment_status === ServiceOrder::PAYMENT_PAID &&
                        $record->status === ServiceOrder::STATUS_PROCESSING
                    )
                    ->action(function (ServiceOrder $record): void {
                        $record->activate();
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool =>
                        $record->status === ServiceOrder::STATUS_ACTIVE
                    )
                    ->action(function (ServiceOrder $record): void {
                        $record->complete();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool => $record->canBeCancelled())
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Reason')
                            ->rows(2),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        $record->admin_notes = ($record->admin_notes ? $record->admin_notes . "\n\n" : '') .
                            'Cancelled: ' . ($data['cancellation_reason'] ?? 'No reason provided');
                        $record->cancel();
                    }),

                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceOrders::route('/'),
            'view' => Pages\ViewServiceOrder::route('/{record}'),
        ];
    }
}
