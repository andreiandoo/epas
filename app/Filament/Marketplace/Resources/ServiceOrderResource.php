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
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

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

        return (string) static::getEloquentQuery()->count();
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
                Section::make(__('Order Information'))
                    ->schema([
                        Forms\Components\Placeholder::make('order_number_display')
                            ->label(__('Order Number'))
                            ->content(fn (?ServiceOrder $record): string => $record?->order_number ?? '-'),

                        Forms\Components\Placeholder::make('organizer_display')
                            ->label(__('Organizer'))
                            ->content(fn (?ServiceOrder $record): string => $record?->organizer?->name ?? '-'),

                        Forms\Components\Placeholder::make('event_display')
                            ->label(__('Event'))
                            ->content(fn (?ServiceOrder $record): string => $record?->event?->name ?? '-'),

                        Forms\Components\Placeholder::make('type_display')
                            ->label(__('Service Type'))
                            ->content(fn (?ServiceOrder $record): string => $record?->service_type_label ?? '-'),

                        Forms\Components\Placeholder::make('locations_display')
                            ->label(__('Plasamente / Detalii'))
                            ->columnSpanFull()
                            ->content(function (?ServiceOrder $record): string {
                                if (! $record) return '-';
                                $config = $record->config ?? [];
                                // For "category"/"city" promotions, the actual category/city is inherited
                                // from the event itself — show it inline so the admin doesn't have to
                                // open the event to know which category page the ad runs on.
                                $eventCategory = null;
                                $eventCity = null;
                                if ($record->event) {
                                    $cat = $record->event->marketplaceEventCategory;
                                    if ($cat) {
                                        $name = is_array($cat->name) ? ($cat->name['ro'] ?? $cat->name['en'] ?? reset($cat->name)) : $cat->name;
                                        $eventCategory = trim(($name ?: '') . ($cat->slug ? " ({$cat->slug})" : ''));
                                    }
                                    $eventCity = $record->event->venue?->city;
                                }
                                $locationLabels = [
                                    'home_hero'            => 'Prima pagina - Hero',
                                    'home_recommendations' => 'Prima pagina - Recomandari',
                                    'category'             => 'Pagina categorie' . ($eventCategory ? ": {$eventCategory}" : ' (categoria evenimentului)'),
                                    'city'                 => 'Pagina oras' . ($eventCity ? ": {$eventCity}" : ' (orasul evenimentului)'),
                                ];
                                return match ($record->service_type) {
                                    // location_featuring: bilete.online only, same placement list.
                                    'featuring', 'location_featuring' => implode(', ', array_map(
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

                Section::make(__('Pricing'))
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label(__('Subtotal (fără TVA)'))
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? number_format($record->subtotal, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('tax_display')
                            ->label(fn (?ServiceOrder $record): string => ($rate = (float) ($record?->config['vat_rate'] ?? 0)) > 0
                                ? 'TVA (' . rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%)'
                                : 'TVA')
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? number_format($record->tax, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('total_display')
                            ->label(__('Total'))
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? number_format($record->total, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('payment_status_display')
                            ->label(__('Payment Status'))
                            ->content(fn (?ServiceOrder $record): string => $record?->payment_status_label ?? '-'),
                    ])
                    ->columns(4),

                Section::make(__('Status & Dates'))
                    ->schema([
                        Forms\Components\Placeholder::make('status_display')
                            ->label(__('Status'))
                            ->content(fn (?ServiceOrder $record): string => $record?->status_label ?? '-'),

                        Forms\Components\Placeholder::make('service_start_display')
                            ->label(__('Service Start'))
                            ->content(function (?ServiceOrder $record): string {
                                if (!$record) return '-';
                                // For email: show scheduled_at (when emails should start sending)
                                if ($record->service_type === 'email' && $record->scheduled_at) {
                                    return $record->scheduled_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') . ' (RO)';
                                }
                                return $record->service_start_date?->format('Y-m-d') ?? '-';
                            }),

                        Forms\Components\Placeholder::make('service_end_display')
                            ->label(__('Service End'))
                            ->content(function (?ServiceOrder $record): string {
                                if (!$record) return '-';
                                // For email: show executed_at (when sending completed)
                                if ($record->service_type === 'email' && $record->executed_at) {
                                    return $record->executed_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') . ' (RO)';
                                }
                                return $record->service_end_date?->format('Y-m-d') ?? '-';
                            }),

                        Forms\Components\Placeholder::make('created_display')
                            ->label(__('Created At'))
                            ->content(fn (?ServiceOrder $record): string =>
                                \App\Support\MarketplaceTz::fmt($record?->created_at, 'Y-m-d H:i', fallback: '-')),
                    ])
                    ->columns(4),

                Section::make(__('Email Campaign Stats'))
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

                Section::make(__('Status pixel-uri Ad Tracking'))
                    ->visible(fn (?ServiceOrder $record): bool => $record?->service_type === ServiceOrder::TYPE_TRACKING)
                    ->schema([
                        Forms\Components\Placeholder::make('tracking_pixels_status')
                            ->label('')
                            ->columnSpanFull()
                            ->content(function (?ServiceOrder $record): HtmlString {
                                if (!$record || $record->service_type !== ServiceOrder::TYPE_TRACKING) {
                                    return new HtmlString('<p class="text-sm text-gray-500">-</p>');
                                }
                                $platforms = $record->config['platforms'] ?? [];
                                if (empty($platforms)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Nicio platformă selectată în config-ul comenzii.</p>');
                                }
                                $integrations = \App\Models\TrackingIntegration::where('marketplace_organizer_id', $record->marketplace_organizer_id)
                                    ->get()->keyBy('provider');
                                $platformLabels = ['facebook' => 'Facebook Pixel', 'google' => 'Google Ads', 'tiktok' => 'TikTok Pixel'];
                                $rows = '';
                                foreach ($platforms as $platform) {
                                    $provider = ServiceOrder::TRACKING_PLATFORM_PROVIDER_MAP[$platform] ?? null;
                                    $row = $provider ? $integrations->get($provider) : null;
                                    $pixelId = $row?->getProviderId() ?? '';
                                    $enabled = (bool) ($row?->enabled ?? false);
                                    $toggle = (bool) (($row?->settings['toggle_enabled'] ?? false));
                                    $label = htmlspecialchars($platformLabels[$platform] ?? ucfirst($platform), ENT_QUOTES);
                                    $providerLbl = htmlspecialchars($provider ?? '?', ENT_QUOTES);
                                    if ($pixelId !== '') {
                                        $statusBadge = '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;">✓ Activ</span>';
                                    } elseif ($toggle) {
                                        $statusBadge = '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;">⚠ Necesită Pixel ID</span>';
                                    } else {
                                        $statusBadge = '<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;">✕ Toggle off</span>';
                                    }
                                    $pixelDisplay = $pixelId !== '' ? htmlspecialchars($pixelId, ENT_QUOTES) : '<span style="color:#9ca3af;">— gol —</span>';
                                    $rows .= "<tr style='border-bottom:1px solid #e5e7eb;'>"
                                        . "<td style='padding:10px 12px;font-size:13px;font-weight:600;color:#1f2937;'>{$label}</td>"
                                        . "<td style='padding:10px 12px;font-size:12px;color:#6b7280;'>{$providerLbl}</td>"
                                        . "<td style='padding:10px 12px;font-size:13px;font-family:monospace;'>{$pixelDisplay}</td>"
                                        . "<td style='padding:10px 12px;text-align:right;'>{$statusBadge}</td>"
                                        . '</tr>';
                                }
                                return new HtmlString(
                                    '<table style="width:100%;border-collapse:collapse;background:white;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">'
                                    . '<thead><tr style="background:#f9fafb;">'
                                    . '<th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">Platforma</th>'
                                    . '<th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">Provider</th>'
                                    . '<th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">Pixel ID</th>'
                                    . '<th style="padding:10px 12px;text-align:right;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">Status</th>'
                                    . '</tr></thead>'
                                    . "<tbody>{$rows}</tbody>"
                                    . '</table>'
                                );
                            }),
                    ]),

                Section::make(__('Service Configuration'))
                    ->schema([
                        Forms\Components\Placeholder::make('config_display')
                            ->label(__('Configuration'))
                            ->content(fn (?ServiceOrder $record): string =>
                                $record ? json_encode($record->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make(__('Admin Notes'))
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
            // Organizer + event are read in the rows: load them once instead of per row.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['organizer:id,name', 'event']))
            ->columns([
                // Order number with the order date as the muted line (created_at stays in "Coloane").
                Tables\Columns\TextColumn::make('order_number')
                    ->label(__('Order #'))
                    ->description(fn (ServiceOrder $record): ?string => $record->created_at?->format('d.m.Y H:i'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),

                // Organizer with the event under it (the Event column stays in "Coloane").
                Tables\Columns\TextColumn::make('organizer.name')
                    ->label(__('Organizer'))
                    ->description(fn (ServiceOrder $record): ?string => $record->event?->name ?: null)
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event.name')
                    ->label(__('Event'))
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('service_type')
                    ->label(__('Serviciu'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'featuring' => 'primary',
                        'location_featuring' => 'primary',
                        'email' => 'success',
                        'tracking' => 'info',
                        'campaign' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'featuring' => __('Promovare eveniment'),
                        'location_featuring' => __('Promovare locație'),
                        'email' => __('Email marketing'),
                        'tracking' => __('Tracking reclame'),
                        'campaign' => __('Campanie'),
                        'extended_artist' => __('Artist extins'),
                        default => ucfirst($state),
                    })
                    ->toggleable(),

                // Start date with the end date under it (End Date stays in "Coloane").
                Tables\Columns\TextColumn::make('service_start_date')
                    ->label(__('Perioadă'))
                    ->date('d.m.Y')
                    ->description(fn (ServiceOrder $record): ?string => $record->service_end_date
                        ? __('până la') . ' ' . $record->service_end_date->format('d.m.Y')
                        : null)
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_end_date')
                    ->label(__('End Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label(__('Total (incl. TVA)'))
                    ->money('RON')
                    ->sortable()
                    ->toggleable(),

                // Order state in Romanian + payment state, as pills.
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Stare'))
                    ->html()
                    ->state(function (ServiceOrder $record): HtmlString {
                        [$label, $tone] = match ($record->status) {
                            'draft' => [__('Ciornă'), 'mute'],
                            'pending_payment' => [__('Așteaptă plata'), 'warn'],
                            'processing' => [__('În procesare'), 'warn'],
                            'active' => [__('Activă'), 'ok'],
                            'completed' => [__('Finalizată'), 'ok'],
                            'cancelled' => [__('Anulată'), 'bad'],
                            'refunded' => [__('Rambursată'), 'bad'],
                            default => [ucfirst((string) $record->status), 'mute'],
                        };
                        $html = '<span class="ep-pill ep-pill--' . $tone . '"><span class="ep-dot"></span>' . e($label) . '</span>';

                        // An unpaid payment on an unpaid order says the same as the order state: skip it.
                        $redundant = ($record->payment_status === 'pending' && in_array($record->status, ['draft', 'pending_payment'], true))
                            || ($record->payment_status === 'refunded' && $record->status === 'refunded');
                        if (! $redundant) {
                            [$pLabel, $pTone] = match ($record->payment_status) {
                                'paid' => [__('Plătită'), 'ok'],
                                'pending' => [__('Plată în așteptare'), 'warn'],
                                'failed' => [__('Plată eșuată'), 'bad'],
                                'refunded' => [__('Plată rambursată'), 'mute'],
                                default => [ucfirst((string) $record->payment_status), 'mute'],
                            };
                            $html .= ' <span class="ep-pill ep-pill--' . $pTone . '">' . e($pLabel) . '</span>';
                        }

                        return new HtmlString('<div class="ep-stare">' . $html . '</div>');
                    })
                    ->sortable()
                    ->toggleable(),

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

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => __('Draft'),
                        'pending_payment' => __('Pending Payment'),
                        'processing' => __('Processing'),
                        'active' => __('Active'),
                        'completed' => __('Completed'),
                        'cancelled' => __('Cancelled'),
                        'refunded' => __('Refunded'),
                    ]),

                Tables\Filters\SelectFilter::make('service_type')
                    ->label(__('Service Type'))
                    ->options([
                        'featuring' => __('Featuring'),
                        // bilete.online only (activities module).
                        'location_featuring' => __('Promovare locatie'),
                        'email' => __('Email Marketing'),
                        'tracking' => __('Ad Tracking'),
                        'campaign' => __('Campaign Creation'),
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => __('Pending'),
                        'paid' => __('Paid'),
                        'failed' => __('Failed'),
                        'refunded' => __('Refunded'),
                    ]),

                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->recordActions([
                // Next step as an icon button; Cancel + View in the menu.
                Action::make('mark_paid')
                    ->label(__('Mark as Paid'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('Mark as Paid'))
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool =>
                        $record->payment_status === ServiceOrder::PAYMENT_PENDING &&
                        in_array($record->status, [ServiceOrder::STATUS_DRAFT, ServiceOrder::STATUS_PENDING_PAYMENT])
                    )
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label(__('Payment Reference'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        $record->payment_method = 'transfer'; // card payments confirm themselves
                        $record->markAsPaid($data['payment_reference']);
                    }),

                Action::make('activate')
                    ->label(__('Activate'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('Activate'))
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool =>
                        $record->payment_status === ServiceOrder::PAYMENT_PAID &&
                        $record->status === ServiceOrder::STATUS_PROCESSING
                    )
                    ->action(function (ServiceOrder $record): void {
                        $record->activate();
                    }),

                Action::make('complete')
                    ->label(__('Complete'))
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip(__('Complete'))
                    ->requiresConfirmation()
                    ->visible(fn (ServiceOrder $record): bool =>
                        $record->status === ServiceOrder::STATUS_ACTIVE
                    )
                    ->action(function (ServiceOrder $record): void {
                        $record->complete();
                    }),

                \Filament\Actions\ActionGroup::make([
                    Action::make('cancel')
                        ->label(__('Cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (ServiceOrder $record): bool => $record->canBeCancelled())
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label(__('Reason'))
                                ->rows(2),
                        ])
                        ->action(function (ServiceOrder $record, array $data): void {
                            $record->admin_notes = ($record->admin_notes ? $record->admin_notes . "\n\n" : '') .
                                'Cancelled: ' . ($data['cancellation_reason'] ?? 'No reason provided');
                            $record->cancel();
                        }),

                    ViewAction::make(),
                ])
                    ->label(__('Mai multe'))
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->iconButton(),
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
