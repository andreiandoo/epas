<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrderResource\Pages;
use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\TicketResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Order::class;
    protected static ?string $navigationLabel = 'Comenzi';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        $query = parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);

        // Filter by customer from URL query param
        if ($customerId = request()->query('customer')) {
            $query->where('marketplace_customer_id', $customerId);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    // Hero Stats Card
                    Forms\Components\Placeholder::make('order_hero')
                        ->hiddenLabel()
                        ->content(fn ($record) => self::renderOrderHero($record)),

                    // Refund Details (visible only if refunded)
                    SC\Section::make('Detalii rambursare')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->visible(fn ($record) => in_array($record->refund_status ?? 'none', ['partial', 'full']) || in_array($record->status, ['refunded', 'partially_refunded']))
                        ->schema([
                            Forms\Components\Placeholder::make('refund_details')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderRefundDetails($record)),
                        ]),

                    // Customer Section
                    SC\Section::make('Client')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Placeholder::make('customer_card')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderCustomerCard($record)),
                        ]),

                    // Beneficiaries (right after Customer)
                    SC\Section::make('Beneficiari')
                        ->icon('heroicon-o-users')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->visible(fn ($record) => !empty($record->meta['beneficiaries']) || $record->tickets->whereNotNull('beneficiary_name')->isNotEmpty())
                        ->schema([
                            Forms\Components\Placeholder::make('beneficiaries_top')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderBeneficiaries($record)),
                        ]),

                    // Event Section
                    SC\Section::make('Eveniment')
                        ->icon('heroicon-o-calendar')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Placeholder::make('event_card')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderEventCard($record)),
                        ]),

                    // Tickets Section
                    SC\Section::make('Bilete comandate')
                        ->icon('heroicon-o-ticket')
                        ->headerActions([
                            Action::make('download_all')
                                ->label('Download toate')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->size('sm')
                                ->action(fn ($record) => static::downloadAllTicketsPdf($record)),
                        ])
                        ->schema([
                            Forms\Components\Placeholder::make('tickets_list')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderTicketsList($record)),
                        ]),

                ]),
                SC\Group::make()->columnSpan(1)->schema([
                    // Combined Order Details
                    SC\Section::make('Detalii comandă')
                        ->icon('heroicon-o-calculator')
                        ->compact()
                        ->schema([
                            Forms\Components\Placeholder::make('order_details_combined')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderCombinedOrderDetails($record)),
                        ]),

                    // Quick Actions - stacked vertically with fullWidth
                    SC\Section::make('Acțiuni rapide')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->schema([
                            SC\Actions::make([
                                Action::make('resend_confirmation')
                                    ->label('Retrimite confirmare')
                                    ->icon('heroicon-o-envelope')
                                    ->color('gray')
                                    ->visible(fn ($record) => $record->source !== 'external_import' && $record->status !== 'expired')
                                    ->action(fn ($record) => self::resendConfirmation($record)),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('download_tickets')
                                    ->label('Download bilete')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray')
                                    ->visible(fn ($record) => $record->status !== 'expired')
                                    ->action(fn ($record) => static::downloadAllTicketsPdf($record)),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('print_invoice')
                                    ->label('Printează factura')
                                    ->icon('heroicon-o-printer')
                                    ->color('gray')
                                    ->visible(fn ($record) => $record->source !== 'external_import' && $record->status !== 'expired'),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('change_status')
                                    ->label('Schimbă status')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('warning')
                                    ->visible(fn ($record) => !in_array($record->status, ['refunded', 'partially_refunded']) && $record->source !== 'external_import')
                                    ->form([
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'pending' => 'În așteptare',
                                                'confirmed' => 'Confirmată',
                                                'completed' => 'Finalizată',
                                                'cancelled' => 'Anulată',
                                                'refunded' => 'Rambursată',
                                                'partially_refunded' => 'Rambursată parțial',
                                            ])
                                            ->required(),
                                    ])
                                    ->action(fn ($record, array $data) => $record->update(['status' => $data['status']])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('request_refund')
                                    ->label('Solicită rambursare')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->visible(fn ($record) => in_array($record->status, ['confirmed', 'paid'])),
                            ])->fullWidth(),
                        ]),

                    // Order Timeline
                    SC\Section::make('Istoric comandă')
                        ->icon('heroicon-o-clock')
                        ->compact()
                        ->collapsible()
                        ->schema([
                            Forms\Components\Placeholder::make('timeline')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderTimeline($record)),
                        ]),

                    // Payment Details
                    SC\Section::make('Detalii plată')
                        ->icon('heroicon-o-credit-card')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Placeholder::make('payment_details')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderPaymentDetails($record)),
                        ]),
                ]),
            ]),
        ])->columns(1);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->disabled(),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('total')
                            ->numeric()
                            ->prefix(fn ($record) => $record?->currency ?? 'RON')
                            ->disabled(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Nr. Comandă')
                    ->formatStateUsing(fn ($state, $record) =>
                        '#' . str_pad($state, 6, '0', STR_PAD_LEFT) .
                        ($record->order_number ? " ({$record->order_number})" : '') .
                        ($record->source === 'test_order' ? ' ⚗️ TEST' : '') .
                        ($record->source === 'external_import' ? ' 🌐 ' . ($record->meta['external_platform'] ?? $record->meta['imported_from'] ?? 'Extern') : '')
                    )
                    ->searchable(query: function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('id', 'like', "%{$search}%")
                              ->orWhere('order_number', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Nume')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('event_names')
                    ->label('Eveniment')
                    ->searchable(query: function ($query, $search) {
                        $term = '%' . mb_strtolower($search) . '%';
                        $isPgsql = \DB::getDriverName() === 'pgsql';
                        $query->whereHas('tickets.event', function ($q) use ($term, $isPgsql) {
                            $q->whereRaw($isPgsql ? "LOWER(title::jsonb->>'ro') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))) LIKE ?", [$term])
                              ->orWhereRaw($isPgsql ? "LOWER(title::jsonb->>'en') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?", [$term]);
                        });
                    })
                    ->getStateUsing(function ($record) {
                        // Get unique events from tickets with names and dates
                        $events = $record->tickets
                            ->pluck('event')
                            ->filter()
                            ->unique('id')
                            ->take(2)
                            ->map(function ($event) {
                                $name = $event->getTranslation('title', app()->getLocale()) ?? $event->title;

                                // Format date based on duration mode
                                $dateStr = '';
                                if ($event->duration_mode === 'range' && $event->range_start_date) {
                                    $start = $event->range_start_date;
                                    $end = $event->range_end_date;
                                    if ($start && $end) {
                                        if ($start->format('m Y') === $end->format('m Y')) {
                                            $dateStr = $start->format('d') . '-' . $end->format('d M');
                                        } else {
                                            $dateStr = $start->format('d M') . ' - ' . $end->format('d M');
                                        }
                                    } else {
                                        $dateStr = $start->format('d M');
                                    }
                                } elseif ($event->event_date) {
                                    $dateStr = $event->event_date->format('d M');
                                } elseif ($event->range_start_date) {
                                    $dateStr = $event->range_start_date->format('d M');
                                }

                                return $name . ($dateStr ? " ({$dateStr})" : '');
                            })
                            ->filter()
                            ->implode(', ');

                        $totalEvents = $record->tickets->pluck('event_id')->unique()->count();
                        if ($totalEvents > 2) {
                            $events .= ' +' . ($totalEvents - 2);
                        }

                        return $events ?: '-';
                    })
                    ->wrap()
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Bilete')
                    ->getStateUsing(fn ($record) => $record->tickets->count())
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => number_format($state ?? ($record->total_cents / 100), 2) . ' ' . ($record->currency ?? 'RON'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('promo_code')
                    ->label('Cod discount')
                    ->placeholder('-')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['completed', 'paid', 'confirmed']),
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'failed']),
                        'gray' => fn ($state) => in_array($state, ['refunded', 'expired']),
                        'info' => 'partially_refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'confirmed' => 'Confirmată',
                        'completed' => 'Finalizată',
                        'cancelled' => 'Anulată',
                        'refunded' => 'Rambursată',
                        'partially_refunded' => 'Rambursată parțial',
                        'failed' => 'Eșuată',
                        'expired' => 'Expirată',
                        default => ucfirst($state),
                    })
                    ->tooltip(function ($record) {
                        if ($record->status !== 'pending' || !$record->expires_at) return null;
                        $expiresAt = $record->expires_at;
                        if ($expiresAt->isPast()) return 'Expirat — se va actualiza automat';
                        $diff = now()->diff($expiresAt);
                        if ($diff->h > 0) return "Expiră în {$diff->h}h {$diff->i}min";
                        if ($diff->i > 0) return "Expiră în {$diff->i} min {$diff->s}s";
                        return "Expiră în {$diff->s}s";
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'confirmed' => 'Confirmată',
                        'completed' => 'Finalizată',
                        'cancelled' => 'Anulată',
                        'refunded' => 'Rambursată',
                        'partially_refunded' => 'Rambursată parțial',
                        'failed' => 'Eșuată',
                        'expired' => 'Expirată',
                    ]),
                Tables\Filters\Filter::make('event_id')
                    ->query(fn ($query, array $data) => $query->when(
                        $data['event_id'] ?? null,
                        fn ($q, $eventId) => $q->whereHas('tickets', fn ($tq) => $tq->where('event_id', $eventId))
                    ))
                    ->form([
                        \Filament\Forms\Components\Select::make('event_id')
                            ->label('Eveniment')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $marketplace = static::getMarketplaceClient();
                                $term = '%' . mb_strtolower($search) . '%';
                                $isPgsql = \DB::getDriverName() === 'pgsql';
                                return \App\Models\Event::where('marketplace_client_id', $marketplace?->id)
                                    ->where(function ($q) use ($term, $isPgsql) {
                                        $q->whereRaw($isPgsql ? "LOWER(title::jsonb->>'ro') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))) LIKE ?", [$term])
                                          ->orWhereRaw($isPgsql ? "LOWER(title::jsonb->>'en') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?", [$term]);
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [$e->id => $e->getTranslation('title', 'ro') ?: $e->name]);
                            })
                            ->getOptionLabelUsing(fn ($value) => \App\Models\Event::find($value)?->getTranslation('title', 'ro') ?? $value),
                    ]),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->iconButton(),
                \Filament\Actions\Action::make('quick_refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Rambursează comanda')
                    ->visible(fn ($record) => in_array($record->status, ['completed', 'paid', 'confirmed']) && !in_array($record->status, ['refunded', 'partially_refunded']) && $record->source !== 'external_import')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => 'Rambursare ' . ($record->order_number ?? '#' . $record->id))
                    ->modalDescription(fn ($record) => 'Rambursare completă pentru comanda ' . ($record->order_number ?? '#' . $record->id) . '. Total: ' . number_format($record->total ?? 0, 2) . ' ' . ($record->currency ?? 'RON'))
                    ->form([
                        \Filament\Forms\Components\Toggle::make('refund_commission')
                            ->label('Include comisionul în rambursare')
                            ->helperText(fn ($record) => ($record->commission_rate ?? 0) > 0
                                ? 'Comision: ' . number_format($record->commission_rate, 1) . '%. Dacă dezactivat, comisionul va fi reținut.'
                                : 'Fără comision configurat.')
                            ->default(false),
                        \Filament\Forms\Components\Select::make('reason_category')
                            ->label('Motiv')
                            ->options([
                                'event_cancelled' => 'Eveniment anulat',
                                'event_postponed' => 'Eveniment amânat',
                                'personal_reason' => 'Motiv personal client',
                                'duplicate_purchase' => 'Achiziție duplicat',
                                'technical_issue' => 'Problemă tehnică',
                                'other' => 'Alt motiv',
                            ])
                            ->nullable(),
                    ])
                    ->action(function ($record, array $data) {
                        $refundService = app(\App\Services\PaymentRefundService::class);
                        $refundCommission = (bool) ($data['refund_commission'] ?? false);
                        $reasonLabels = [
                            'event_cancelled' => 'Eveniment anulat',
                            'event_postponed' => 'Eveniment amânat',
                            'personal_reason' => 'Motiv personal client',
                            'duplicate_purchase' => 'Achiziție duplicat',
                            'technical_issue' => 'Problemă tehnică',
                        ];
                        $reason = $reasonLabels[$data['reason_category'] ?? ''] ?? 'Rambursare';
                        $result = $refundService->processOrderLevelRefund($record, $refundCommission, $reason, $data['reason_category'] ?? null);

                        if ($result->success) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Rambursare procesată')
                                ->body(($record->order_number ?? '#' . $record->id) . ' — ' . ($result->refundId ?? ''))
                                ->send();
                        } elseif ($result->requiresManual) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Procesare manuală necesară')
                                ->body($result->error ?? '')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Eroare rambursare')
                                ->body($result->error ?? '')
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('change_status')
                        ->label('Schimbă status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status nou')
                                ->options([
                                    'pending' => 'În așteptare',
                                    'paid' => 'Plătită',
                                    'confirmed' => 'Confirmată',
                                    'completed' => 'Finalizată',
                                    'cancelled' => 'Anulată',
                                    'failed' => 'Eșuată',
                                    'expired' => 'Expirată',
                                ])
                                ->helperText('Rambursarea nu poate fi setată manual — folosește acțiunea dedicată.')
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $skipped = $records->filter(fn ($r) => in_array($r->status, ['refunded', 'partially_refunded']))->count();
                            $updatable = $records->filter(fn ($r) => !in_array($r->status, ['refunded', 'partially_refunded']));
                            $updatable->each(fn ($record) => $record->update(['status' => $data['status']]));
                            $msg = $updatable->count() . ' comenzi actualizate.';
                            if ($skipped > 0) $msg .= " {$skipped} comenzi rambursate au fost ignorate.";
                            \Filament\Notifications\Notification::make()->title($msg)->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulk_refund')
                        ->label('Rambursează')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rambursare comenzi selectate')
                        ->modalDescription('Se va procesa rambursarea completă (fără comision) pentru toate comenzile selectate care au status plătit/completat. Comenzile deja rambursate vor fi ignorate.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $refundService = app(\App\Services\PaymentRefundService::class);
                            $success = 0;
                            $failed = 0;
                            $skipped = 0;

                            foreach ($records as $order) {
                                if (in_array($order->status, ['refunded', 'partially_refunded'])) {
                                    $skipped++;
                                    continue;
                                }
                                if (!in_array($order->status, ['completed', 'paid', 'confirmed'])) {
                                    $skipped++;
                                    continue;
                                }
                                $result = $refundService->processOrderLevelRefund($order, false, 'Rambursare bulk');
                                if ($result->success || $result->requiresManual) {
                                    $success++;
                                } else {
                                    $failed++;
                                }
                            }

                            $msg = "{$success} comenzi rambursate.";
                            if ($failed > 0) $msg .= " {$failed} eșuate.";
                            if ($skipped > 0) $msg .= " {$skipped} ignorate (deja rambursate sau neplătite).";
                            \Filament\Notifications\Notification::make()
                                ->title($msg)
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulk_delete')
                        ->label('Șterge')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Șterge comenzile selectate')
                        ->modalDescription('Comenzile finalizate, plătite sau rambursate nu pot fi șterse.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $protected = ['completed', 'paid', 'refunded', 'partially_refunded'];
                            $deletable = $records->filter(fn ($r) => !in_array($r->status, $protected));
                            $skipped = $records->count() - $deletable->count();
                            $deletable->each(fn ($r) => $r->delete());
                            $msg = $deletable->count() . ' comenzi șterse.';
                            if ($skipped > 0) $msg .= " {$skipped} comenzi protejate au fost ignorate.";
                            \Filament\Notifications\Notification::make()->title($msg)->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    protected static function renderOrderHero(Order $record): HtmlString
    {
        $incrementalId = '#' . str_pad($record->id, 6, '0', STR_PAD_LEFT);
        $customerRef = $record->order_number; // Customer-facing reference like MKT-xxx
        $date = $record->created_at->format('d M Y, H:i');
        $currency = $record->currency ?? 'RON';
        $ticketCount = $record->tickets->count();

        // POS/mobile app orders: display total = subtotal (face value), not DB total which may include commission
        $isPosOrder = $record->source === 'pos_app';
        $isTestOrder = $record->source === 'test_order';
        $displayTotal = $isPosOrder
            ? number_format($record->subtotal ?? $record->tickets->sum('price'), 2)
            : number_format($record->total ?? ($record->total_cents / 100), 2);
        $total = $displayTotal;

        // Payment method display - show processor name properly, with fallbacks
        $paymentProcessor = $record->payment_processor ?? $record->meta['payment_processor'] ?? null;
        $paymentMethod = match($paymentProcessor) {
            'netopia', 'payment-netopia' => 'Netopia',
            'stripe', 'payment-stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'cash' => 'Cash',
            'bank_transfer' => 'Transfer',
            default => null,
        };

        // If no processor found, check meta for payment method
        if (!$paymentMethod) {
            $metaMethod = $record->meta['payment_method'] ?? $record->meta['method'] ?? null;
            if ($metaMethod) {
                $paymentMethod = match(strtolower($metaMethod)) {
                    'card', 'credit_card', 'card bancar' => 'Card',
                    'cash', 'numerar' => 'Cash',
                    'transfer', 'bank_transfer' => 'Transfer',
                    default => ucfirst($metaMethod),
                };
            } elseif ($paymentProcessor) {
                $paymentMethod = ucfirst(str_replace(['_', '-', 'payment-'], [' ', ' ', ''], $paymentProcessor));
            } elseif (in_array($record->status, ['pending'])) {
                $paymentMethod = 'În așteptare';
            } else {
                $paymentMethod = '-';
            }
        }
        $updatedAt = $record->updated_at->format('d M H:i');

        // Calculate savings (discount + target price savings)
        $savings = (float) ($record->discount_amount ?? 0);

        // Add target price savings
        $targetPrice = 0;
        if ($record->event) {
            $targetPrice = (float) ($record->event->target_price ?? 0);
        } elseif ($record->marketplaceEvent) {
            $targetPrice = (float) ($record->marketplaceEvent->target_price ?? 0);
        }

        if ($targetPrice > 0) {
            foreach ($record->tickets as $ticket) {
                $ticketPrice = (float) ($ticket->price ?? 0);
                if ($targetPrice > $ticketPrice && $ticketPrice > 0) {
                    $savings += ($targetPrice - $ticketPrice);
                }
            }
        }

        $savingsHtml = $savings > 0
            ? '<div style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; font-size: 13px; color: #10B981;">
                🏷️ Economii: -' . number_format($savings, 2) . ' ' . $currency . '
            </div>'
            : '';

        // Customer-facing reference display
        $customerRefHtml = $customerRef
            ? "<div style='font-size: 14px; font-weight: 500; color: #94A3B8; margin-top: 2px;'>{$customerRef}</div>"
            : '';

        return new HtmlString("
            <div style='background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; border-radius: 16px; padding: 24px; margin-bottom: 20px; position: relative; overflow: hidden;'>
                <div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;'>
                    <div>
                        <div style='font-size: 32px; font-weight: 800; color: white;'>{$incrementalId}</div>
                        {$customerRefHtml}
                        <div style='font-size: 13px; color: #64748B; margin-top: 4px;'>{$date}</div>
                    </div>
                    {$savingsHtml}
                </div>
                <div style='display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;'>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;'>
                        <div style='font-size: 24px; font-weight: 700; color: white;'>{$total}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Total {$currency}</div>
                    </div>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;'>
                        <div style='font-size: 24px; font-weight: 700; color: white;'>{$ticketCount}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Bilete</div>
                    </div>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;'>
                        <div style='font-size: 24px; font-weight: 700; color: #10B981;'>{$paymentMethod}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Plată</div>
                    </div>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;' class='flex flex-col items-center justify-center'>
                        <div style='font-size: 14px; font-weight: 700; color: white;'>{$updatedAt}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Ultima actualizare</div>
                    </div>
                </div>
                " . ($isTestOrder ? "
                <div style='display: flex; align-items: center; gap: 10px; margin-top: 16px; padding: 10px 14px; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 10px; font-size: 13px; color: #F59E0B;'>
                    <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width:16px;height:16px;flex-shrink:0;'>
                        <path stroke-linecap='round' stroke-linejoin='round' d='M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5' />
                    </svg>
                    <span><strong>COMANDĂ DE TEST</strong> &nbsp;·&nbsp; Gratuită, fără plată &nbsp;·&nbsp; Nu afectează stocul sau statisticile</span>
                </div>
                " : "") . "
                " . ($isPosOrder ? "
                <div style='display: flex; align-items: center; gap: 10px; margin-top: 16px; padding: 10px 14px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 10px; font-size: 13px; color: #A5B4FC;'>
                    <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width:16px;height:16px;flex-shrink:0;'>
                        <path stroke-linecap='round' stroke-linejoin='round' d='M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3' />
                    </svg>
                    <span>Înregistrată prin <strong>aplicația mobilă Tixello</strong> &nbsp;·&nbsp; Plată în <strong>numerar</strong></span>
                </div>
                " : "") . "
            </div>
        ");
    }

    protected static function renderCustomerCard(Order $record): HtmlString
    {
        $name = $record->customer_name ?? 'N/A';
        $email = $record->customer_email ?? '';
        $phone = $record->customer_phone ?? $record->meta['customer_phone'] ?? '';
        $initials = collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');

        $phoneHtml = $phone ? "
            <div style='display: flex; align-items: center; gap: 6px;'>
                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                    <path stroke-linecap='round' stroke-linejoin='round' d='M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3' />
                </svg>
                {$phone}
            </div>
        " : '';

        return new HtmlString("
            <div style='display: flex; gap: 16px; align-items: center;'>
                <div style='width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: white;'>{$initials}</div>
                <div style='flex: 1;'>
                    <div style='font-size: 16px; font-weight: 700; color: white; margin-bottom: 4px;'>" . e($name) . "</div>
                    <div style='display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #94A3B8;'>
                        <div style='display: flex; align-items: center; gap: 6px;'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' />
                            </svg>
                            <a href='mailto:{$email}' style='color: #60A5FA; text-decoration: none;'>{$email}</a>
                        </div>
                        {$phoneHtml}
                    </div>
                </div>
                <div class='flex gap-8 pr-2'>
                    " . ($record->marketplace_customer_id
                        ? "<a href='" . MarketplaceCustomerResource::getUrl('edit', ['record' => $record->marketplace_customer_id]) . "' class='fi-btn fi-size-sm  fi-ac-btn-action no-underline'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' />
                            </svg>
                             Vezi profil
                        </a>"
                        : "<span class='fi-btn fi-size-sm fi-ac-btn-action opacity-40 cursor-not-allowed'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' />
                            </svg>
                             Vezi profil
                        </span>"
                    ) . "
                    <a href='mailto:{$email}' class='fi-btn fi-size-sm fi-ac-btn-action no-underline'>
                        <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                            <path stroke-linecap='round' stroke-linejoin='round' d='M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' />
                        </svg>
                        Trimite email
                    </a>
                </div>
            </div>
        ");
    }

    protected static function renderTicketsList(Order $record): HtmlString
    {
        $html = '';

        foreach ($record->tickets as $ticket) {
            $typeNameRaw = $ticket->marketplaceTicketType?->name ?? $ticket->ticketType?->name;
            $typeName = is_array($typeNameRaw) ? ($typeNameRaw['ro'] ?? $typeNameRaw['en'] ?? reset($typeNameRaw) ?: 'Bilet') : ($typeNameRaw ?? 'Bilet');
            $code = $ticket->code ?? $ticket->unique_code ?? 'N/A';
            $barcode = $ticket->barcode ?? $code;
            $priceValue = $ticket->price ?? (($ticket->ticketType?->price_cents ?? 0) / 100);
            $price = number_format($priceValue, 2);
            $currency = $ticket->marketplaceTicketType?->currency ?? $ticket->ticketType?->currency ?? 'RON';

            // Get beneficiary from meta or order
            $meta = $ticket->meta ?? [];
            $beneficiary = $meta['beneficiary']['name'] ?? $meta['beneficiary_name'] ?? $ticket->attendee_name ?? $record->customer_name ?? '';
            $beneficiaryEmail = $meta['beneficiary']['email'] ?? $meta['beneficiary_email'] ?? $ticket->attendee_email ?? $record->customer_email ?? '';

            $statusBadge = match($ticket->status ?? 'valid') {
                'valid' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Valid</span>',
                'used' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60A5FA;">✓ Folosit</span>',
                'cancelled' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Anulat</span>',
                default => '',
            };

            // Insurance badge
            $insuranceBadge = '';
            if (!empty($meta['has_insurance'])) {
                $insAmt = isset($meta['insurance_amount']) ? number_format((float) $meta['insurance_amount'], 2) . ' ' . $currency : '';
                $insuranceBadge = '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(167, 139, 250, 0.15); color: #A78BFA;">✓ Asigurat' . ($insAmt ? " ({$insAmt})" : '') . '</span>';
            }

            // Seat info — resolve from meta → EventSeat → seat_uid parsing
            $seatDetails = $ticket->getSeatDetails();
            $seatSection = $seatDetails['section_name'] ?? '';
            $seatRow = $seatDetails['row_label'] ?? '';
            $seatNumber = $seatDetails['seat_number'] ?? '';
            $seatDisplay = '';
            if ($seatSection || $seatRow || $seatNumber) {
                $parts = array_filter([$seatSection, $seatRow ? "Rând {$seatRow}" : '', $seatNumber ? "Loc {$seatNumber}" : '']);
                $seatDisplay = implode(', ', $parts);
            } elseif ($ticket->seat_label) {
                $seatDisplay = $ticket->seat_label;
            }

            // Generate QR code URL using verification URL
            $qrData = urlencode($ticket->getVerifyUrl());
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={$qrData}";

            // View URL for ticket
            $viewUrl = TicketResource::getUrl('view', ['record' => $ticket->id]);

            $seatHtml = $seatDisplay ? "
                        <div style='display: flex; align-items: center; gap: 8px; margin-top: 4px;'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 14px; height: 14px; color: #64748B;'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' />
                                <path stroke-linecap='round' stroke-linejoin='round' d='M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z' />
                            </svg>
                            <span style='font-size: 11px; color: #A78BFA; font-weight: 500;'>" . e($seatDisplay) . "</span>
                        </div>" : '';

            $html .= "
                <div style='display: flex; align-items: stretch; gap: 16px; padding: 16px; background: #0F172A; border-radius: 12px; margin-bottom: 12px; border: 1px solid #334155;'>
                    <!-- QR Code -->
                    <div style='display: flex; flex-direction: column; align-items: center; gap: 4px; flex-shrink: 0;'>
                        <img src='{$qrCodeUrl}' alt='QR Code' style='width: 60px; height: 60px; border-radius: 4px; background: white; padding: 2px;'>
                        <span style='font-size: 9px; color: #64748B;'>QR Code</span>
                    </div>

                    <!-- Ticket details -->
                    <div style='flex: 1;'>
                        <div style='display: flex; align-items: center; gap: 8px; margin-bottom: 4px;'>
                            <span style='font-size: 14px; font-weight: 600; color: white;'>" . e($typeName) . "</span>
                            {$statusBadge}
                            {$insuranceBadge}
                        </div>
                        <div style='font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 4px; margin-bottom: 4px;'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 16px; height: 16px;'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' />
                            </svg>
                            " . e($beneficiary) . " (" . e($beneficiaryEmail) . ")
                        </div>
                        <!-- Barcode display -->
                        <div style='display: flex; align-items: center; gap: 8px;'>
                            <span style='font-size: 11px; color: #64748B;'>Cod:</span>
                            <span style='padding: 2px 8px; background: #334155; border-radius: 4px; font-size: 11px; font-family: monospace; color: #94A3B8; letter-spacing: 1px;'>" . e($code) . "</span>
                        </div>
                        {$seatHtml}
                    </div>

                    <!-- Price and actions -->
                    <div style='display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; min-width: 120px;'>
                        <div style='font-size: 16px; font-weight: 700; color: white;'>{$price} {$currency}</div>
                        <div style='display: flex; gap: 8px;'>
                            <a href='{$viewUrl}' style='width: 32px; height: 32px; border-radius: 6px; background: #334155; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94A3B8; text-decoration: none;' title='Vezi bilet'>
                                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 16px; height: 16px;'>
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z' />
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' />
                                </svg>
                            </a>
                            <a href='" . route('marketplace.ticket.download-pdf', $ticket->id) . "' target='_blank' style='width: 32px; height: 32px; border-radius: 6px; background: #334155; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94A3B8; text-decoration: none;' title='Download bilet'>
                                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 16px; height: 16px;'>
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z' />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderEventCard(Order $record): HtmlString
    {
        $events = $record->tickets
            ->pluck('event')
            ->filter()
            ->unique('id');

        if ($events->isEmpty()) {
            return new HtmlString('<p style="color: #64748B;">Nu există evenimente asociate.</p>');
        }

        $html = '';
        
        foreach ($events as $event) {
            $title = $event->getTranslation('title', app()->getLocale()) ?? $event->title ?? 'Eveniment';
            
            // Format date based on duration mode
            $dateStr = '';
            if ($event->duration_mode === 'range' && $event->range_start_date) {
                $start = $event->range_start_date;
                $end = $event->range_end_date;
                if ($start && $end) {
                    if ($start->format('m Y') === $end->format('m Y')) {
                        $dateStr = $start->format('d') . '-' . $end->format('d M Y');
                    } else {
                        $dateStr = $start->format('d M') . ' - ' . $end->format('d M Y');
                    }
                } else {
                    $dateStr = $start->format('d M Y');
                }
            } elseif ($event->event_date) {
                $dateStr = $event->event_date->format('d M Y');
            }
            
            // Time
            $timeStr = '';
            if ($event->event_time) {
                $timeStr = is_string($event->event_time) 
                    ? $event->event_time 
                    : $event->event_time->format('H:i');
            } elseif ($event->start_time) {
                $timeStr = is_string($event->start_time) 
                    ? $event->start_time 
                    : $event->start_time->format('H:i');
            }
            
            // Venue
            $venue = $event->venue;
            $venueName = '';
            $venueCity = '';
            if ($venue) {
                $venueName = $venue->getTranslation('name', app()->getLocale()) ?? $venue->name ?? '';
                $venueCity = $venue->city ?? '';
            }
            $locationStr = $venueName . ($venueCity ? ', ' . $venueCity : '');
            
            // Poster/Image - use Storage::url() for correct path
            $posterPath = $event->poster_url ?? $event->hero_image_url ?? null;
            $posterUrl = $posterPath ? Storage::disk('public')->url($posterPath) : null;
            $posterHtml = $posterUrl
                ? "<img src='{$posterUrl}' alt='" . e($title) . "' style='width: 100%; height: 100%; object-fit: cover;'>"
                : "<span style='font-size: 32px;'>🎸</span>";

            $html .= "
                <div style='display: flex; gap: 16px;align-items:center;padding-right:3px;'>
                    <div style='width: 100px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #374151, #1F2937); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;'>
                        {$posterHtml}
                    </div>
                    <div style='flex: 1;'>
                        <div style='font-size: 16px; font-weight: 700; color: white; margin-bottom: 8px;'>" . e($title) . "</div>
                        <div style='display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #94A3B8;'>";
            
            if ($dateStr) {
                $html .= "
                            <div style='display: flex; align-items: center; gap: 6px;'>
                                <svg style='width: 14px; height: 14px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'/></svg>
                                {$dateStr}
                            </div>";
            }
            
            if ($locationStr) {
                $html .= "
                            <div style='display: flex; align-items: center; gap: 6px;'>
                                <svg style='width: 14px; height: 14px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'/></svg>
                                " . e($locationStr) . "
                            </div>";
            }
            
            if ($timeStr) {
                $html .= "
                            <div style='display: flex; align-items: center; gap: 6px;'>
                                <svg style='width: 14px; height: 14px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>
                                {$timeStr}
                            </div>";
            }
            
            $html .= "
                        </div>
                    </div>
                    <div>
                        <a href='" . EventResource::getUrl('edit', ['record' => $event->id]) . "' 
                        class='fi-btn fi-size-sm fi-ac-btn-action no-underline'>
                            <svg style='width: 14px; height: 14px;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'/></svg>
                            Vezi eveniment
                        </a>
                    </div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderCombinedOrderDetails(Order $record): HtmlString
    {
        $currency = $record->currency ?? 'RON';
        $commissionDetails = $record->meta['commission_details'] ?? [];
        $orderTotal = (float) ($record->total ?? 0);
        $orderSubtotal = (float) ($record->subtotal ?? 0);
        $orderDiscount = (float) ($record->discount_amount ?? 0);
        $orderCommission = (float) ($record->commission_amount ?? 0);
        $insuranceAmount = (float) ($record->meta['insurance_amount'] ?? 0);
        $ticketInsurance = (bool) ($record->meta['ticket_insurance'] ?? false);
        $isPosOrder = $record->source === 'pos_app';
        $isExternalImport = $record->source === 'external_import';

        $rowStyle = "display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.3);";
        $labelStyle = "font-size:12px;color:#94A3B8;";
        $valueStyle = "font-size:12px;font-weight:600;color:#E2E8F0;";
        $subStyle = "font-size:11px;color:#64748B;padding:2px 0 4px 12px;border-bottom:1px solid rgba(51,65,85,0.2);";
        $headStyle = "font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:0.5px;padding:10px 0 4px;";

        $html = '<div>';

        // === EXTERNAL IMPORT BADGE ===
        if ($isExternalImport) {
            $extPlatform = e($record->meta['external_platform'] ?? $record->meta['imported_from'] ?? 'Extern');
            $html .= "<div style='padding:8px 12px;margin-bottom:8px;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:8px;font-size:12px;color:#818CF8;display:flex;align-items:center;gap:6px;'>🌐 Import extern: <strong>{$extPlatform}</strong> — fără comision Tixello</div>";
        }

        // === TICKETS ===
        // tickets.price is NULL on imported legacy tickets, so fall back to
        // ticketType.price_cents / 100 (same pattern used on ticket detail view).
        $ticketsValue = $record->tickets->reduce(
            fn ($carry, $t) => $carry + ($t->price ?? (($t->ticketType?->price_cents ?? 0) / 100)),
            0.0
        );
        $html .= "<div style='{$headStyle}'>Bilete</div>";
        $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>Valoare bilete</span><span style='{$valueStyle}'>" . number_format($ticketsValue, 2) . " {$currency}</span></div>";

        // Per-ticket breakdown
        foreach ($commissionDetails as $cd) {
            $name = $cd['ticket_type'] ?? 'Bilet';
            if (is_array($name)) $name = $name['ro'] ?? reset($name) ?? 'Bilet';
            $qty = (int) ($cd['quantity'] ?? 1);
            $unitPrice = (float) ($cd['unit_price'] ?? 0);
            $total = (float) ($cd['total'] ?? 0);
            $html .= "<div style='{$subStyle}'>" . e($name) . " x{$qty} — " . number_format($unitPrice, 2) . " {$currency}/buc = " . number_format($total, 2) . " {$currency}</div>";
        }

        // === COMMISSION ===
        if ($isExternalImport) {
            $html .= "<div style='{$headStyle}'>Comision</div>";
            $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>Fără comision (import extern)</span><span style='font-size:12px;color:#64748B;'>0.00 {$currency}</span></div>";
        } elseif ($orderCommission > 0 && !$isPosOrder) {
            // Determine commission type label
            $modes = collect($commissionDetails)->pluck('commission_mode')->unique();
            $hasFixed = collect($commissionDetails)->contains(fn ($cd) => ($cd['commission_rate'] ?? 0) == 0 && ($cd['commission_amount'] ?? 0) > 0);
            $hasPercent = collect($commissionDetails)->contains(fn ($cd) => ($cd['commission_rate'] ?? 0) > 0);
            $isOnTop = $modes->contains(fn ($m) => in_array($m, ['on_top', 'add_on_top', 'added_on_top']));
            $modeLabel = $isOnTop ? 'peste' : 'inclus';

            if ($hasFixed && $hasPercent) {
                $commLabel = "Comision mixt ({$modeLabel})";
            } elseif ($hasFixed) {
                $commLabel = "Comision fix ({$modeLabel})";
            } else {
                $rates = collect($commissionDetails)->pluck('commission_rate')->unique()->filter(fn ($r) => $r > 0);
                $rateStr = $rates->count() === 1 ? number_format($rates->first(), 1) . '%' : 'variabil';
                $commLabel = "Comision {$rateStr} ({$modeLabel})";
            }

            $html .= "<div style='{$headStyle}'>Comision</div>";
            $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>{$commLabel}</span><span style='{$valueStyle}'>" . number_format($orderCommission, 2) . " {$currency}</span></div>";

            // Per-type commission breakdown
            foreach ($commissionDetails as $cd) {
                $name = $cd['ticket_type'] ?? 'Bilet';
                if (is_array($name)) $name = $name['ro'] ?? reset($name) ?? 'Bilet';
                $commission = (float) ($cd['commission_amount'] ?? 0);
                $rate = (float) ($cd['commission_rate'] ?? 0);
                $qty = (int) ($cd['quantity'] ?? 1);
                $cdMode = in_array($cd['commission_mode'] ?? '', ['on_top', 'add_on_top', 'added_on_top']) ? 'peste' : 'inclus';

                if ($commission <= 0) continue;

                $rateLabel = ($rate > 0)
                    ? number_format($rate, 1) . '%, ' . $cdMode
                    : number_format($commission / max(1, $qty), 2) . " lei fix, {$cdMode}";

                $html .= "<div style='{$subStyle}'>" . e($name) . " x{$qty} ({$rateLabel}) — " . number_format($commission, 2) . " {$currency}</div>";
            }

            // Organizer receives
            $organizerRevenue = $ticketsValue;
            if (!$isOnTop) $organizerRevenue -= $orderCommission;
            $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>Organizator primește</span><span style='font-size:12px;font-weight:600;color:#10B981;'>" . number_format($organizerRevenue, 2) . " {$currency}</span></div>";
        } elseif (!$isPosOrder && !$isExternalImport) {
            // Legacy orders (WP import) don't have commission_amount stored; infer it
            // from the difference between total paid and tickets face value. Only
            // show when it is a clearly positive number to avoid misleading rounding noise.
            $derivedCommission = $orderTotal - $ticketsValue - $insuranceAmount + $orderDiscount;
            if ($derivedCommission > 0.01) {
                $html .= "<div style='{$headStyle}'>Comision</div>";
                $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>Comision (dedus din total)</span><span style='{$valueStyle}'>" . number_format($derivedCommission, 2) . " {$currency}</span></div>";
            }
        }

        // === DISCOUNT ===
        if ($orderDiscount > 0) {
            $promoData = $record->meta['promo_code'] ?? null;
            $promoCode = $promoData['code'] ?? '';
            $promoLabel = $promoCode ? " (cod: {$promoCode})" : '';
            $html .= "<div style='{$headStyle}'>Reducere</div>";
            $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>Reducere{$promoLabel}</span><span style='font-size:12px;font-weight:600;color:#10B981;'>-" . number_format($orderDiscount, 2) . " {$currency}</span></div>";

            if ($promoData) {
                $promoType = match($promoData['type'] ?? '') {
                    'percentage' => $promoData['value'] . '%',
                    'fixed' => number_format($promoData['value'] ?? 0, 2) . ' ' . $currency,
                    default => $promoData['type'] ?? '',
                };
                $promoSource = match($promoData['source'] ?? '') {
                    'coupon' => 'Cod promoțional',
                    'organizer' => 'Cod organizator',
                    'affiliate' => 'Cod afiliat',
                    default => $promoData['source'] ?? '',
                };
                $html .= "<div style='{$subStyle}'>{$promoSource} · {$promoType}</div>";
            }
        }

        // === INSURANCE ===
        if ($insuranceAmount > 0 || $ticketInsurance) {
            $html .= "<div style='{$headStyle}'>Asigurare retur</div>";
            $html .= "<div style='{$rowStyle}'><span style='{$labelStyle}'>Taxa de retur bilete</span><span style='font-size:12px;font-weight:600;color:#A78BFA;'>" . number_format($insuranceAmount, 2) . " {$currency}</span></div>";

            // Show which tickets have insurance
            $insuredTickets = $record->tickets->filter(fn ($t) => !empty($t->meta['has_insurance']));
            if ($insuredTickets->isNotEmpty()) {
                foreach ($insuredTickets as $t) {
                    $html .= "<div style='{$subStyle}'>🛡 " . e($t->ticketType?->name ?? 'Bilet') . " #" . e($t->code ?? '') . "</div>";
                }
            } elseif ($ticketInsurance) {
                $html .= "<div style='{$subStyle}'>🛡 Toate biletele din comandă</div>";
            }
        }

        // === TOTAL ===
        $html .= "<div style='display:flex;justify-content:space-between;align-items:center;padding:12px 0 0;margin-top:6px;border-top:2px solid rgba(51,65,85,0.5);'>
            <span style='font-size:13px;font-weight:600;color:white;'>Total plătit</span>
            <span style='font-size:18px;font-weight:700;color:white;'>" . number_format($orderTotal, 2) . " {$currency}</span>
        </div>";

        $html .= '</div>';
        return new HtmlString($html);
    }

    protected static function renderPriceBreakdown(Order $record): HtmlString
    {
        $currency = $record->currency ?? 'RON';
        $ticketsValue = $record->tickets->sum('price');
        $discount = $record->discount_amount ?? $record->promo_discount ?? 0;
        $total = $record->total ?? ($record->total_cents / 100);

        // POS/mobile app orders: commission is never added on top of customer price
        $isPosOrder = $record->source === 'pos_app';

        // Get commission info — prefer per-ticket-type details over order-level meta
        $commissionRate = (float) ($record->commission_rate ?? 0);
        $commissionAmount = (float) ($record->commission_amount ?? 0);
        $commissionDetails = $record->meta['commission_details'] ?? [];

        // Determine commission mode from commission_details (per ticket type) — more accurate
        $commissionMode = 'included';
        if (!empty($commissionDetails)) {
            $firstDetail = $commissionDetails[0] ?? [];
            $commissionMode = $firstDetail['commission_mode'] ?? $record->meta['commission_mode'] ?? 'included';
        } else {
            $commissionMode = $record->meta['commission_mode']
                ?? $record->event?->commission_mode
                ?? $record->event?->marketplaceOrganizer?->default_commission_mode
                ?? $record->marketplaceClient?->commission_mode
                ?? 'included';
        }

        $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);
        $commission = $commissionAmount > 0 ? $commissionAmount : $ticketsValue * ($commissionRate / 100);

        $html = '<div>';

        // Tickets value
        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                <span style='font-size: 13px; color: #94A3B8;'>Valoare bilete</span>
                <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . number_format($ticketsValue, 2) . " {$currency}</span>
            </div>
        ";

        // Commission (if any)
        if ($commission > 0) {
            $modeLabel = $isOnTop ? '(peste preț)' : '(inclus)';
            if ($isPosOrder) {
                // Commission not applied to POS orders — show as dash
                $html .= "
                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                        <span style='font-size: 13px; color: #94A3B8;'>Comision " . number_format($commissionRate, 1) . "% {$modeLabel}</span>
                        <span style='font-size: 13px; color: #475569;'>—</span>
                    </div>
                ";
            } else {
                $html .= "
                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                        <span style='font-size: 13px; color: #94A3B8;'>Comision " . number_format($commissionRate, 1) . "% {$modeLabel}</span>
                        <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . number_format($commission, 2) . " {$currency}</span>
                    </div>
                ";
            }
        }

        // Discount (if any)
        if ($discount > 0) {
            $promoData = $record->meta['promo_code'] ?? null;
            $promoCode = $promoData['code'] ?? $record->promo_code ?? $record->meta['coupon_code'] ?? '';
            $promoLabel = $promoCode ? " (cod: {$promoCode})" : '';
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Reducere{$promoLabel}</span>
                    <span style='font-size: 13px; font-weight: 600; color: #10B981;'>-" . number_format($discount, 2) . " {$currency}</span>
                </div>
            ";

            // Promo code details
            if ($promoData) {
                $promoType = match($promoData['type'] ?? '') {
                    'percentage' => $promoData['value'] . '%',
                    'fixed' => number_format($promoData['value'] ?? 0, 2) . ' ' . $currency,
                    default => $promoData['type'] ?? '',
                };
                $promoSource = match($promoData['source'] ?? '') {
                    'coupon' => 'Cod promoțional',
                    'organizer' => 'Cod organizator',
                    'affiliate' => 'Cod afiliat',
                    default => $promoData['source'] ?? '',
                };

                // Try to get coupon details (organizer, event, ticket type restrictions)
                $couponDetails = '';
                if (!empty($promoData['id'])) {
                    $coupon = \App\Models\Coupon\CouponCode::find($promoData['id']);
                    if ($coupon) {
                        $parts = [];
                        if ($coupon->marketplace_organizer_id) {
                            $orgName = \App\Models\MarketplaceOrganizer::where('id', $coupon->marketplace_organizer_id)->value('name');
                            if ($orgName) $parts[] = "Org: " . e($orgName);
                        }
                        $applicableEvents = $coupon->applicable_events ?? [];
                        if (!empty($applicableEvents)) {
                            $eventNames = \App\Models\Event::whereIn('id', $applicableEvents)->get()->map(fn($e) => $e->getTranslation('title', 'ro') ?: 'Event #'.$e->id)->implode(', ');
                            if ($eventNames) $parts[] = "Ev: " . e($eventNames);
                        }
                        $applicableTT = $coupon->applicable_ticket_types ?? [];
                        if (!empty($applicableTT)) {
                            $ttNames = \App\Models\TicketType::whereIn('id', $applicableTT)->pluck('name')->implode(', ');
                            if ($ttNames) $parts[] = "Tip: " . e($ttNames);
                        }
                        if (!empty($parts)) {
                            $couponDetails = implode(' · ', $parts);
                        }
                    }
                }

                $detailsHtml = $couponDetails
                    ? "<div style='font-size: 11px; color: #64748B; margin-top: 2px;'>{$couponDetails}</div>"
                    : '';

                $html .= "
                    <div style='padding: 4px 0 8px 12px; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                        <div style='font-size: 11px; color: #64748B;'>{$promoSource} · {$promoType}</div>
                        {$detailsHtml}
                    </div>
                ";
            }
        }

        // Insurance / Taxa de retur (if any)
        $insuranceAmount = (float) ($record->meta['insurance_amount'] ?? 0);
        if ($insuranceAmount > 0) {
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Taxa de retur</span>
                    <span style='font-size: 13px; font-weight: 600; color: #A78BFA;'>" . number_format($insuranceAmount, 2) . " {$currency}</span>
                </div>
            ";
        }

        // Calculate final total
        // POS orders: customer pays the ticket face value only (no commission added on top)
        $finalTotal = $isPosOrder
            ? ($ticketsValue - $discount + $insuranceAmount)
            : ($isOnTop
                ? ($ticketsValue + $commission - $discount + $insuranceAmount)
                : $total);

        // Total
        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0; margin-top: 4px;'>
                <span style='font-size: 13px; font-weight: 600; color: white;'>Total plătit</span>
                <span style='font-size: 18px; font-weight: 700; color: white;'>" . number_format($finalTotal, 2) . " {$currency}</span>
            </div>
        ";

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderCommissionDetails(Order $record): HtmlString
    {
        $currency = $record->currency ?? 'RON';
        $meta = $record->meta ?? [];

        // Use stored commission details if available (new orders)
        $commissionDetails = $meta['commission_details'] ?? [];

        if (!empty($commissionDetails)) {
            return static::renderPerItemCommission($commissionDetails, $record, $currency);
        }

        // Legacy fallback: calculate from event/organizer rates
        $event = $record->event;
        $commissionRate = $event?->commission_rate
            ?? $event?->marketplaceOrganizer?->commission_rate
            ?? 0;
        $commissionMode = $meta['commission_mode']
            ?? $event?->commission_mode
            ?? $event?->marketplaceOrganizer?->default_commission_mode
            ?? $record->marketplaceClient?->commission_mode
            ?? 'included';

        // Try to use per-ticket-type commission from TicketType model
        $tickets = $record->tickets;
        $totalCommission = 0;
        $totalOnTop = 0;
        $totalValue = 0;
        $hasPerTicketCommission = false;
        $itemBreakdown = [];

        foreach ($tickets->groupBy(fn ($t) => $t->ticket_type_id ?? ('mkt-' . $t->marketplace_ticket_type_id)) as $groupKey => $groupTickets) {
            $firstTicket = $groupTickets->first();
            $ticketType = $firstTicket->ticketType;
            $groupTotal = $groupTickets->sum('price');
            $totalValue += $groupTotal;
            $qty = $groupTickets->count();

            if ($ticketType && $ticketType->commission_type) {
                $hasPerTicketCommission = true;
                $effective = $ticketType->getEffectiveCommission($commissionRate, $commissionMode);
                $itemCommission = $ticketType->calculateCommission((float) $firstTicket->price, $commissionRate, $commissionMode) * $qty;
                $itemMode = $effective['mode'];
                $itemRate = $effective['rate'];
                $itemFixed = $effective['fixed'];
                $itemType = $effective['type'];
            } else {
                $itemCommission = round($groupTotal * ($commissionRate / 100), 2);
                $itemMode = $commissionMode;
                $itemRate = $commissionRate;
                $itemFixed = 0;
                $itemType = 'percentage';
            }

            $isOnTop = in_array($itemMode, ['on_top', 'add_on_top', 'added_on_top']);
            if ($isOnTop) {
                $totalOnTop += $itemCommission;
            }
            $totalCommission += $itemCommission;

            $ttName = $firstTicket->marketplaceTicketType?->name ?? $ticketType?->name ?? 'Bilet';
            if (is_array($ttName)) {
                $ttName = $ttName['ro'] ?? $ttName['en'] ?? reset($ttName) ?: 'Bilet';
            }

            $rateLabel = match ($itemType) {
                'fixed' => number_format($itemFixed, 2) . ' ' . $currency . ' fix',
                'both' => number_format($itemRate, 2) . '% + ' . number_format($itemFixed, 2) . ' ' . $currency,
                default => number_format($itemRate, 2) . '%',
            };

            $itemBreakdown[] = [
                'name' => $ttName,
                'qty' => $qty,
                'commission' => $itemCommission,
                'rate_label' => $rateLabel,
                'mode' => $isOnTop ? 'peste' : 'inclus',
            ];
        }

        if ($totalCommission <= 0 && $commissionRate <= 0 && !$hasPerTicketCommission) {
            return new HtmlString('<p style="color: #64748B; text-align: center;">Fără comision</p>');
        }

        $organizerRevenue = $totalValue - $totalCommission + $totalOnTop;

        return static::buildCommissionHtml($itemBreakdown, $totalCommission, $organizerRevenue, $currency);
    }

    protected static function renderPerItemCommission(array $commissionDetails, Order $record, string $currency): HtmlString
    {
        $totalCommission = 0;
        $totalOnTop = 0;
        $totalValue = 0;
        $itemBreakdown = [];

        foreach ($commissionDetails as $detail) {
            $name = $detail['ticket_type'] ?? 'Bilet';
            if (is_array($name)) {
                $name = $name['ro'] ?? $name['en'] ?? reset($name) ?: 'Bilet';
            }
            $total = (float) ($detail['total'] ?? 0);
            $commission = (float) ($detail['commission_amount'] ?? 0);
            $rate = (float) ($detail['commission_rate'] ?? 0);
            $mode = $detail['commission_mode'] ?? 'included';
            $qty = (int) ($detail['quantity'] ?? 1);

            $totalValue += $total;
            $totalCommission += $commission;

            $isOnTop = in_array($mode, ['on_top', 'add_on_top', 'added_on_top']);
            if ($isOnTop) {
                $totalOnTop += $commission;
            }

            $rateLabel = ($rate > 0)
                ? number_format($rate, 2) . '%'
                : number_format($commission / max(1, $qty), 2) . ' lei fix';

            $itemBreakdown[] = [
                'name' => $name,
                'qty' => $qty,
                'commission' => $commission,
                'rate_label' => $rateLabel,
                'mode' => $isOnTop ? 'peste' : 'inclus',
            ];
        }

        if ($totalCommission <= 0) {
            return new HtmlString('<p style="color: #64748B; text-align: center;">Fără comision</p>');
        }

        $organizerRevenue = $totalValue - $totalCommission + $totalOnTop;

        return static::buildCommissionHtml($itemBreakdown, $totalCommission, $organizerRevenue, $currency);
    }

    protected static function buildCommissionHtml(array $itemBreakdown, float $totalCommission, float $organizerRevenue, string $currency): HtmlString
    {
        $html = '<div>';

        // Per-item breakdown
        if (count($itemBreakdown) > 0) {
            foreach ($itemBreakdown as $item) {
                $html .= "
                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid rgba(148,163,184,0.1);'>
                        <div>
                            <span style='font-size: 13px; color: #E2E8F0;'>" . e($item['name']) . "</span>
                            <span style='font-size: 11px; color: #64748B;'> x{$item['qty']}</span>
                            <span style='font-size: 11px; color: #94A3B8; margin-left: 8px;'>({$item['rate_label']}, {$item['mode']})</span>
                        </div>
                        <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . number_format($item['commission'], 2) . " {$currency}</span>
                    </div>";
            }
        }

        // Total commission
        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-top: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2);'>
                <span style='font-size: 13px; color: #94A3B8;'>Total comision</span>
                <span style='font-size: 16px; font-weight: 700; color: #EF4444;'>" . number_format($totalCommission, 2) . " {$currency}</span>
            </div>";

        // Organizer revenue
        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-top: 8px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);'>
                <span style='font-size: 13px; color: #94A3B8;'>Organizator primește</span>
                <span style='font-size: 16px; font-weight: 700; color: #10B981;'>" . number_format($organizerRevenue, 2) . " {$currency}</span>
            </div>";

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderBeneficiaries(Order $record): HtmlString
    {
        // Collect beneficiaries from tickets
        $beneficiaries = $record->tickets
            ->filter(fn ($ticket) => $ticket->beneficiary_name || $ticket->beneficiary_email)
            ->map(fn ($ticket) => [
                'name' => $ticket->beneficiary_name ?? $record->customer_name ?? 'N/A',
                'email' => $ticket->beneficiary_email ?? $record->customer_email ?? '',
                'ticket_type' => (function() use ($ticket) {
                    $name = $ticket->ticketType?->name;
                    return is_array($name) ? ($name['ro'] ?? $name['en'] ?? reset($name) ?: 'Bilet') : ($name ?? 'Bilet');
                })(),
                'ticket_code' => $ticket->code ?? $ticket->unique_code ?? '',
            ]);

        // Also check meta for beneficiaries
        if (!empty($record->meta['beneficiaries'])) {
            foreach ($record->meta['beneficiaries'] as $index => $beneficiary) {
                $beneficiaries->push([
                    'name' => $beneficiary['name'] ?? $beneficiary['first_name'] . ' ' . ($beneficiary['last_name'] ?? '') ?? 'N/A',
                    'email' => $beneficiary['email'] ?? '',
                    'ticket_type' => $beneficiary['ticket_type'] ?? 'Bilet',
                    'ticket_code' => $beneficiary['ticket_code'] ?? '',
                ]);
            }
        }

        // Fallback to customer if no beneficiaries
        if ($beneficiaries->isEmpty()) {
            $beneficiaries->push([
                'name' => $record->customer_name ?? 'N/A',
                'email' => $record->customer_email ?? '',
                'ticket_type' => 'Toate biletele',
                'ticket_code' => '',
            ]);
        }

        $html = '';

        foreach ($beneficiaries->unique('email') as $beneficiary) {
            $name = $beneficiary['name'];
            $email = $beneficiary['email'];
            $ticketType = $beneficiary['ticket_type'];
            $initials = collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');

            $html .= "
                <div style='display: flex; align-items: center; gap: 12px; padding: 12px; background: #0F172A; border-radius: 8px; margin-bottom: 8px;'>
                    <div style='width: 36px; height: 36px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #E2E8F0;'>{$initials}</div>
                    <div style='flex: 1;'>
                        <div style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . e($name) . "</div>
                        <div style='font-size: 11px; color: #64748B;'>" . e($email) . "</div>
                    </div>
                    <div style='font-size: 11px; color: #94A3B8; padding: 4px 8px; background: #334155; border-radius: 4px;'>" . e($ticketType) . "</div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderPaymentDetails(Order $record): HtmlString
    {
        // Processor name (Netopia, Stripe, etc.)
        $processorRaw = $record->payment_processor;
        $processor = match($processorRaw) {
            'netopia', 'payment-netopia' => 'Netopia',
            'stripe', 'payment-stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'cash' => 'Numerar',
            'bank_transfer' => 'Transfer bancar',
            default => $processorRaw ? ucfirst(str_replace(['_', '-', 'payment-'], [' ', ' ', ''], $processorRaw)) : null,
        };

        // Payment method (Card, Bank transfer, etc.) - from meta or derived from processor
        $paymentMethod = $record->meta['payment_method'] ?? $record->meta['method'] ?? null;
        if (!$paymentMethod && $processorRaw) {
            // Derive method from processor if not explicitly set
            $paymentMethod = match($processorRaw) {
                'netopia', 'payment-netopia', 'stripe', 'payment-stripe' => 'Card bancar',
                'paypal' => 'PayPal',
                'cash' => 'Numerar',
                'bank_transfer' => 'Transfer bancar',
                default => null,
            };
        }

        $transactionId = $record->payment_reference ?? $record->meta['payment_intent_id'] ?? $record->meta['transaction_id'] ?? '';
        $cardLast4 = $record->meta['card_last4'] ?? $record->meta['card_last_four'] ?? '';
        $cardBrand = ucfirst($record->meta['card_brand'] ?? '');
        $paidAt = $record->paid_at ?? $record->meta['paid_at'] ?? null;

        $html = '<div>';

        // Payment Processor
        if ($processor) {
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Procesor</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$processor}</span>
                </div>
            ";
        }

        // Payment Method
        if ($paymentMethod) {
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Metodă plată</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$paymentMethod}</span>
                </div>
            ";
        }

        // Transaction ID
        if ($transactionId) {
            // Truncate long transaction IDs
            $displayId = strlen($transactionId) > 20
                ? substr($transactionId, 0, 10) . '...' . substr($transactionId, -6)
                : $transactionId;

            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>ID Tranzacție</span>
                    <span style='font-size: 11px; font-weight: 600; color: #E2E8F0; font-family: monospace; cursor: pointer;' title='" . e($transactionId) . "'>{$displayId}</span>
                </div>
            ";
        }

        // Card Info
        if ($cardLast4) {
            $cardDisplay = $cardBrand ? "{$cardBrand} •••• {$cardLast4}" : "•••• {$cardLast4}";
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Card</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$cardDisplay}</span>
                </div>
            ";
        }

        // Payment Date
        if ($paidAt) {
            $paidAtFormatted = $paidAt instanceof \Carbon\Carbon 
                ? $paidAt->format('d M Y, H:i') 
                : \Carbon\Carbon::parse($paidAt)->format('d M Y, H:i');
            
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Data plății</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$paidAtFormatted}</span>
                </div>
            ";
        }

        // Payment Status
        $statusBadge = match($record->status) {
            'paid', 'confirmed', 'completed' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Plătit</span>',
            'pending' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">⏳ În așteptare</span>',
            'refunded' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60A5FA;">↩ Rambursat</span>',
            'partially_refunded' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(99, 102, 241, 0.15); color: #818CF8;">↩ Rambursat parțial</span>',
            'cancelled' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Anulat</span>',
            'failed' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Eșuat</span>',
            'expired' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(107, 114, 128, 0.15); color: #9CA3AF;">⏱ Expirat</span>',
            default => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(107, 114, 128, 0.15); color: #9CA3AF;">' . ucfirst($record->status) . '</span>',
        };

        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0;'>
                <span style='font-size: 13px; color: #94A3B8;'>Status plată</span>
                {$statusBadge}
            </div>
        ";

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderRefundDetails(Order $record): HtmlString
    {
        $currency = $record->currency ?? 'RON';
        $refundedAmount = (float) ($record->refunded_amount ?? $record->refund_amount ?? 0);
        $orderTotal = (float) ($record->total ?? 0);
        $remaining = max(0, $orderTotal - $refundedAmount);
        $refundStatus = $record->refund_status ?? 'none';
        $refundedAt = $record->refunded_at;

        // Get refund requests for this order
        $refundRequests = $record->refundRequests()
            ->with(['refundItems.ticket.ticketType', 'refundItems.ticket'])
            ->orderByDesc('created_at')
            ->get();

        $isFullRefund = $refundStatus === 'full';
        $typeLabel = $isFullRefund ? 'Completă' : 'Parțială';
        $typeColor = $isFullRefund ? '#60A5FA' : '#818CF8';

        $html = '<div>';

        // Summary row
        $html .= "
            <div style='display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;'>
                <div style='padding:12px;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);border-radius:8px;'>
                    <div style='font-size:11px;color:#94A3B8;margin-bottom:4px;'>Tip rambursare</div>
                    <div style='font-size:14px;font-weight:600;color:{$typeColor};'>{$typeLabel}</div>
                </div>
                <div style='padding:12px;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);border-radius:8px;'>
                    <div style='font-size:11px;color:#94A3B8;margin-bottom:4px;'>Data rambursării</div>
                    <div style='font-size:14px;font-weight:600;color:#E2E8F0;'>" . ($refundedAt ? $refundedAt->format('d.m.Y H:i') : '—') . "</div>
                </div>
            </div>
        ";

        // Amounts
        $html .= "
            <div style='display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                <span style='font-size:13px;color:#94A3B8;'>Sumă rambursată</span>
                <span style='font-size:14px;font-weight:700;color:#60A5FA;'>" . number_format($refundedAmount, 2) . " {$currency}</span>
            </div>
            <div style='display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                <span style='font-size:13px;color:#94A3B8;'>Sumă rămasă</span>
                <span style='font-size:14px;font-weight:600;color:" . ($remaining > 0 ? '#F59E0B' : '#10B981') . ";'>" . number_format($remaining, 2) . " {$currency}</span>
            </div>
        ";

        // Motiv
        $refundReason = $record->refund_reason ?? '';
        if ($refundReason) {
            $html .= "
                <div style='display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                    <span style='font-size:13px;color:#94A3B8;'>Motiv</span>
                    <span style='font-size:13px;color:#E2E8F0;'>" . e($refundReason) . "</span>
                </div>
            ";
        }

        // Per-refund request details
        foreach ($refundRequests as $refReq) {
            $statusBadge = match($refReq->status) {
                'refunded' => '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(16,185,129,0.15);color:#10B981;">Procesată</span>',
                'partially_refunded' => '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(99,102,241,0.15);color:#818CF8;">Parțial</span>',
                'processing' => '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(245,158,11,0.15);color:#F59E0B;">Se procesează</span>',
                'approved' => '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(59,130,246,0.15);color:#60A5FA;">Aprobată (manual)</span>',
                'failed' => '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(239,68,68,0.15);color:#EF4444;">Eșuată</span>',
                default => '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(107,114,128,0.15);color:#9CA3AF;">' . ucfirst($refReq->status) . '</span>',
            };

            $refId = $refReq->payment_refund_id ? "<span style='font-size:11px;color:#64748B;font-family:monospace;'>{$refReq->payment_refund_id}</span>" : '';

            $html .= "
                <div style='margin-top:12px;padding:10px;background:rgba(15,23,42,0.5);border:1px solid rgba(51,65,85,0.5);border-radius:8px;'>
                    <div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;'>
                        <span style='font-size:12px;font-weight:600;color:#E2E8F0;'>{$refReq->reference}</span>
                        {$statusBadge}
                    </div>
                    <div style='font-size:11px;color:#64748B;margin-bottom:6px;'>
                        " . number_format($refReq->approved_amount ?? $refReq->requested_amount ?? 0, 2) . " {$currency}
                        · " . ($refReq->created_at?->format('d.m.Y H:i') ?? '') . "
                        {$refId}
                    </div>
            ";

            // Refunded tickets
            $refundItems = $refReq->refundItems ?? collect();
            if ($refundItems->isNotEmpty()) {
                $html .= "<div style='margin-top:6px;'>";
                foreach ($refundItems as $item) {
                    $ticket = $item->ticket;
                    if (!$ticket) continue;

                    $event = $ticket->resolveEvent();
                    $eventTitle = '';
                    if ($event) {
                        $eventTitle = is_array($event->title) ? ($event->title['ro'] ?? reset($event->title) ?? '') : ($event->title ?? '');
                    }
                    $eventDate = $event?->event_date?->format('d.m.Y') ?? '';
                    $venueName = $event?->venue ? (is_array($event->venue->name) ? ($event->venue->name['ro'] ?? '') : ($event->venue->name ?? '')) : '';
                    $venueCity = $event?->venue?->city ?? '';

                    $eventInfo = array_filter([$eventDate, $venueName, $venueCity]);
                    $eventLine = $eventTitle ? e($eventTitle) . ($eventInfo ? ' <span style="color:#64748B;">(' . e(implode(', ', $eventInfo)) . ')</span>' : '') : '';

                    $html .= "
                        <div style='display:flex;align-items:center;gap:8px;padding:4px 0;border-top:1px solid rgba(51,65,85,0.3);'>
                            <span style='font-size:11px;font-family:monospace;color:#94A3B8;background:rgba(51,65,85,0.5);padding:1px 6px;border-radius:4px;'>" . e($ticket->code ?? '—') . "</span>
                            <span style='font-size:11px;color:#E2E8F0;flex:1;'>{$eventLine}</span>
                            <span style='font-size:11px;font-weight:600;color:#60A5FA;'>" . number_format($item->refund_amount, 2) . "</span>
                        </div>
                    ";
                }
                $html .= "</div>";
            }

            // Email notification log
            $emailLog = \App\Models\MarketplaceEmailLog::where('marketplace_client_id', $record->marketplace_client_id)
                ->where('template_slug', 'refund_processed')
                ->where('to_email', $record->customer_email ?? $record->marketplaceCustomer?->email ?? '')
                ->where('created_at', '>=', $refReq->created_at ?? now()->subDay())
                ->orderByDesc('id')
                ->first();

            if ($emailLog) {
                $emailUrl = \App\Filament\Marketplace\Resources\EmailLogResource::getUrl('view', ['record' => $emailLog->id]);
                $emailStatusBadge = match($emailLog->status) {
                    'sent' => '<span style="color:#10B981;">✓ Trimis</span>',
                    'delivered' => '<span style="color:#10B981;">✓ Livrat</span>',
                    'opened' => '<span style="color:#3B82F6;">👁 Deschis</span>',
                    'clicked' => '<span style="color:#8B5CF6;">🔗 Click</span>',
                    'bounced' => '<span style="color:#EF4444;">✕ Bounced</span>',
                    'failed' => '<span style="color:#EF4444;">✕ Eșuat</span>',
                    default => '<span style="color:#94A3B8;">' . $emailLog->status . '</span>',
                };
                $html .= "
                    <div style='margin-top:8px;padding:6px 8px;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:6px;display:flex;justify-content:space-between;align-items:center;'>
                        <span style='font-size:11px;color:#94A3B8;'>📧 Email rambursare: {$emailStatusBadge}</span>
                        <a href='{$emailUrl}' style='font-size:11px;color:#60A5FA;text-decoration:none;'>Vezi email →</a>
                    </div>
                ";
            }

            $html .= "</div>";
        }

        $html .= '</div>';
        return new HtmlString($html);
    }

    protected static function renderTimeline(Order $record): HtmlString
    {
        // Build timeline events from order history
        $events = collect();

        // Current status
        $statusText = match($record->status) {
            'completed' => 'Comandă finalizată',
            'confirmed' => 'Comandă confirmată',
            'paid' => 'Comandă plătită',
            'pending' => 'Comandă în așteptare',
            'cancelled' => 'Comandă anulată',
            'refunded' => 'Comandă rambursată',
            'partially_refunded' => 'Comandă rambursată parțial',
            'failed' => 'Comandă eșuată',
            'expired' => 'Comandă expirată',
            default => 'Status: ' . $record->status,
        };

        $statusColor = match($record->status) {
            'completed', 'confirmed', 'paid' => 'success',
            'pending' => 'warning',
            'cancelled', 'failed', 'expired' => 'danger',
            'refunded', 'partially_refunded' => 'info',
            default => 'gray',
        };

        // Don't add current status as event if refunded (refund events cover it)
        if (!in_array($record->status, ['refunded', 'partially_refunded'])) {
            $events->push([
                'status' => $statusColor,
                'text' => $statusText,
                'time' => $record->updated_at,
            ]);
        }

        // Payment processed (if paid/completed/refunded — was paid before refund)
        if (in_array($record->status, ['paid', 'confirmed', 'completed', 'refunded', 'partially_refunded'])) {
            $paidAt = $record->paid_at ?? $record->meta['paid_at'] ?? $record->created_at->addMinutes(2);
            $events->push([
                'status' => 'success',
                'text' => 'Plată procesată cu succes',
                'time' => $paidAt instanceof \Carbon\Carbon ? $paidAt : \Carbon\Carbon::parse($paidAt),
            ]);
        }

        // Email sent (assume sent after creation)
        if ($record->meta['confirmation_sent'] ?? true) {
            $events->push([
                'status' => 'info',
                'text' => 'Email confirmare trimis',
                'time' => $record->created_at->addMinutes(1),
            ]);
        }

        // Refund events
        if (in_array($record->status, ['refunded', 'partially_refunded'])) {
            $refundRequests = $record->refundRequests()->orderByDesc('created_at')->get();
            foreach ($refundRequests as $refReq) {
                $amount = number_format($refReq->approved_amount ?? $refReq->requested_amount ?? 0, 2);

                // Refund initiated
                $events->push([
                    'status' => 'warning',
                    'text' => "Rambursare inițializată — {$amount} {$record->currency}",
                    'time' => $refReq->created_at,
                ]);

                // Refund processed
                if (in_array($refReq->status, ['refunded', 'partially_refunded'])) {
                    $events->push([
                        'status' => 'info',
                        'text' => "Rambursare procesată — {$amount} {$record->currency} ({$refReq->reference})",
                        'time' => $refReq->completed_at ?? $refReq->processed_at ?? $refReq->updated_at,
                    ]);
                }
            }
        }

        // Refund email tracking — one email per refund request (latest)
        $refundEmail = \App\Models\MarketplaceEmailLog::where('marketplace_client_id', $record->marketplace_client_id)
            ->where('template_slug', 'refund_processed')
            ->where('subject', 'like', '%' . ($record->order_number ?? $record->id) . '%')
            ->orderByDesc('id')
            ->first();

        $refundEmails = $refundEmail ? collect([$refundEmail]) : collect();

        foreach ($refundEmails as $emailLog) {
            $events->push([
                'status' => 'info',
                'text' => '📧 Email rambursare trimis',
                'time' => $emailLog->sent_at ?? $emailLog->created_at,
            ]);

            if ($emailLog->delivered_at) {
                $events->push([
                    'status' => 'success',
                    'text' => '📧 Email rambursare livrat',
                    'time' => $emailLog->delivered_at,
                ]);
            }

            if ($emailLog->opened_at) {
                $events->push([
                    'status' => 'success',
                    'text' => '👁 Email rambursare deschis de client',
                    'time' => $emailLog->opened_at,
                ]);
            }

            if ($emailLog->clicked_at) {
                $events->push([
                    'status' => 'success',
                    'text' => '🔗 Client a dat click în email',
                    'time' => $emailLog->clicked_at,
                ]);
            }

            if ($emailLog->bounced_at) {
                $events->push([
                    'status' => 'danger',
                    'text' => '⚠ Email rambursare bounce',
                    'time' => $emailLog->bounced_at,
                ]);
            }
        }

        // Order created
        $events->push([
            'status' => 'warning',
            'text' => 'Comandă plasată',
            'time' => $record->created_at,
        ]);

        // Sort by time descending
        $events = $events->sortByDesc('time')->values();

        // Render timeline
        $html = '<div style="position: relative; padding-left: 24px;">';
        $html .= '<div style="position: absolute; left: 7px; top: 8px; bottom: 8px; width: 2px; background: #334155;"></div>';

        foreach ($events as $index => $event) {
            $dotColor = match($event['status']) {
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'danger' => '#EF4444',
                'info' => '#60A5FA',
                default => '#334155',
            };

            $time = $event['time'] instanceof \Carbon\Carbon 
                ? $event['time']->format('d M Y, H:i') 
                : $event['time'];

            $isLast = $index === $events->count() - 1;
            $paddingBottom = $isLast ? '0' : '16px';

            $html .= "
                <div style='position: relative; padding-bottom: {$paddingBottom};'>
                    <div style='position: absolute; left: -24px; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: {$dotColor}; border: 3px solid #1E293B;'></div>
                    <div style='font-size: 13px; color: #E2E8F0;'>{$event['text']}</div>
                    <div style='font-size: 11px; color: #64748B; margin-top: 2px;'>{$time}</div>
                </div>
            ";
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function resendConfirmation(Order $record): void
    {
        try {
            // Use the same email logic as PaymentController
            $controller = new \App\Http\Controllers\Api\MarketplaceClient\PaymentController();
            $controller->sendOrderConfirmationEmail($record);

            // Update meta
            $record->update([
                'meta' => array_merge($record->meta ?? [], [
                    'confirmation_resent_at' => now()->toISOString(),
                    'confirmation_resent_count' => ($record->meta['confirmation_resent_count'] ?? 0) + 1,
                ]),
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Email trimis')
                ->body('Confirmarea a fost retrimisă către ' . $record->customer_email)
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Eroare')
                ->body('Nu s-a putut trimite emailul: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function downloadAllTickets(Order $record)
    {
        return static::downloadAllTicketsPdf($record);
    }

    protected static function downloadAllTicketsPdf(Order $record)
    {
        $tickets = $record->tickets()->with(['order.marketplaceClient', 'marketplaceEvent', 'marketplaceTicketType'])->get();
        if ($tickets->isEmpty()) return null;

        $variableService = app(\App\Services\TicketCustomizer\TicketVariableService::class);
        $generator = app(\App\Services\TicketCustomizer\TicketPreviewGenerator::class);

        // Resolve template once (all tickets in an order usually share the same event)
        $firstTicket = $tickets->first();
        $event = $firstTicket->resolveEvent();
        $template = static::resolveTicketTemplate($firstTicket, $event);

        if ($template && !empty($template->template_data)) {
            // Generate combined PDF using custom template for each ticket
            $size = $template->getSize();
            $widthPt = round($size['width'] * 2.8346, 2);
            $heightPt = round($size['height'] * 2.8346, 2);
            $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

            $pages = [];
            foreach ($tickets as $ticket) {
                $data = $variableService->resolveTicketData($ticket);
                $content = $generator->renderToHtml($template->template_data, $data);
                if (!empty(trim($content))) {
                    $pages[] = $content;
                }
            }

            if (!empty($pages)) {
                $wrappedPages = array_map(fn ($content) =>
                    "<div style=\"position: relative; width: {$widthPt}pt; height: {$heightPt}pt; overflow: hidden; background-color: {$bgColor};\">" .
                    str_replace('position: fixed;', 'position: absolute;', $content) .
                    "</div>",
                    $pages
                );
                $pagesHtml = implode('<div style="page-break-after: always;"></div>', $wrappedPages);
                $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>@page{margin:0;size:{$widthPt}pt {$heightPt}pt;}*{margin:0;padding:0;}body{margin:0;padding:0;font-family:'DejaVu Sans',sans-serif;}</style></head><body>{$pagesHtml}</body></html>";

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                    ->setPaper([0, 0, $widthPt, $heightPt])
                    ->setOption('isRemoteEnabled', true)
                    ->setOption('isHtml5ParserEnabled', true);

                $template->markAsUsed();
                $orderNumber = $record->order_number ?? $record->id;

                return response()->streamDownload(
                    fn () => print($pdf->output()),
                    "bilete-{$orderNumber}.pdf"
                );
            }
        }

        // Fallback: generic combined template
        $client = $record->marketplaceClient;
        $eventName = $firstTicket->marketplaceEvent?->name ?? 'Eveniment';
        $marketplaceName = $client?->public_name ?? $client?->name ?? 'Marketplace';
        $primaryColor = $client?->settings['theme']['primary_color'] ?? '#1a1a2e';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('marketplace-tickets-pdf', [
            'order' => $record,
            'tickets' => $tickets,
            'eventName' => $eventName,
            'marketplaceName' => $marketplaceName,
            'primaryColor' => $primaryColor,
        ])
            ->setOption('isRemoteEnabled', true)
            ->setPaper([0, 0, 396, 700], 'portrait');

        $orderNumber = $record->order_number ?? $record->id;

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "bilete-{$orderNumber}.pdf"
        );
    }

    protected static function resolveTicketTemplate(\App\Models\Ticket $ticket, ?\App\Models\Event $event): ?\App\Models\TicketTemplate
    {
        // 1. Event's assigned template
        if ($event?->ticketTemplate && $event->ticketTemplate->status === 'active' && !empty($event->ticketTemplate->template_data)) {
            $layers = $event->ticketTemplate->template_data['layers'] ?? [];
            if (!empty(array_filter($layers, fn($l) => !isset($l['visible']) || $l['visible'] !== false))) {
                return $event->ticketTemplate;
            }
        }

        // 2. Marketplace client default template
        $clientId = $ticket->marketplace_client_id ?? $event?->marketplace_client_id ?? $ticket->order?->marketplace_client_id;
        if ($clientId) {
            $template = \App\Models\TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->get()
                ->first(fn ($t) => !empty($t->template_data['layers'] ?? []));

            if ($template) return $template;
        }

        return null;
    }

    protected static function printInvoice(Order $record)
    {
        // Generate invoice PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.order', [
            'order' => $record,
        ]);
        
        return response()->streamDownload(
            fn () => print($pdf->output()),
            "invoice-{$record->id}.pdf"
        );
    }
}
