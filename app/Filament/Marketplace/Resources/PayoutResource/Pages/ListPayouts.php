<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\PayoutResource;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Deconturi <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_payout')
                ->label('Crează Decont Manual')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('marketplace_organizer_id')
                        ->label('Organizator')
                        ->options(function () {
                            $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                            return MarketplaceOrganizer::where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                                ->whereNotNull('verified_at')
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set) {
                            $set('event_id', null);
                            $set('bank_account_id', null);
                        }),

                    Forms\Components\Select::make('event_id')
                        ->label('Eveniment')
                        ->options(function (Forms\Components\Utilities\Get $get) {
                            $organizerId = $get('marketplace_organizer_id');
                            if (!$organizerId) return [];

                            $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                            return Event::where('marketplace_organizer_id', $organizerId)
                                ->where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                                ->orderByDesc('created_at')
                                ->get()
                                ->mapWithKeys(function ($event) {
                                    $title = is_array($event->title)
                                        ? ($event->title['ro'] ?? $event->title['en'] ?? array_values($event->title)[0] ?? 'Untitled')
                                        : ($event->title ?? 'Untitled');
                                    return [$event->id => $title];
                                });
                        })
                        ->searchable()
                        ->placeholder('General (fără eveniment)')
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if ($state) {
                                $event = Event::find($state);
                                if ($event) {
                                    // Calculate available balance for this event
                                    $balance = self::calculateEventBalance($event);
                                    $set('available_balance_info', number_format($balance, 2) . ' RON disponibil');
                                    $set('gross_amount', $balance > 0 ? number_format($balance, 2, '.', '') : '0.00');
                                }
                            } else {
                                $set('available_balance_info', null);
                            }
                        }),

                    Forms\Components\Placeholder::make('available_balance_info')
                        ->label('Sold disponibil')
                        ->content(fn ($state) => $state ?? '-')
                        ->visible(fn (Forms\Components\Utilities\Get $get) => $get('event_id') !== null),

                    Forms\Components\Select::make('bank_account_id')
                        ->label('Cont bancar')
                        ->options(function (Forms\Components\Utilities\Get $get) {
                            $organizerId = $get('marketplace_organizer_id');
                            if (!$organizerId) return [];

                            return MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizerId)
                                ->get()
                                ->mapWithKeys(fn ($acc) => [
                                    $acc->id => $acc->bank_name . ' - ' . $acc->iban . ($acc->is_primary ? ' (primar)' : ''),
                                ]);
                        })
                        ->required()
                        ->visible(fn (Forms\Components\Utilities\Get $get) => $get('marketplace_organizer_id') !== null),

                    \Filament\Schemas\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('gross_amount')
                            ->label('Suma brută')
                            ->numeric()
                            ->required()
                            ->suffix('RON')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, Forms\Components\Utilities\Get $get) {
                                $gross = (float) $state;
                                $commission = (float) $get('commission_amount');
                                $fees = (float) $get('fees_amount');
                                $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('commission_amount')
                            ->label('Comision')
                            ->numeric()
                            ->default('0.00')
                            ->suffix('RON')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, Forms\Components\Utilities\Get $get) {
                                $gross = (float) $get('gross_amount');
                                $commission = (float) $state;
                                $fees = (float) $get('fees_amount');
                                $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('fees_amount')
                            ->label('Taxe')
                            ->numeric()
                            ->default('0.00')
                            ->suffix('RON')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, Forms\Components\Utilities\Get $get) {
                                $gross = (float) $get('gross_amount');
                                $commission = (float) $get('commission_amount');
                                $fees = (float) $state;
                                $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('net_amount')
                            ->label('Suma netă (de plată)')
                            ->numeric()
                            ->required()
                            ->suffix('RON')
                            ->readOnly(),
                    ]),

                    \Filament\Schemas\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('period_start')
                            ->label('Perioada de la')
                            ->default(now()->startOfMonth()->toDateString()),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Perioada până la')
                            ->default(now()->toDateString()),
                    ]),

                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Note admin')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                    $organizer = MarketplaceOrganizer::find($data['marketplace_organizer_id']);
                    $bankAccount = MarketplaceOrganizerBankAccount::find($data['bank_account_id']);

                    $payoutMethod = [
                        'type' => 'bank_transfer',
                        'bank_account_id' => $bankAccount->id,
                        'bank_name' => $bankAccount->bank_name,
                        'iban' => $bankAccount->iban,
                        'account_holder' => $bankAccount->account_holder,
                    ];

                    $payout = MarketplacePayout::create([
                        'marketplace_client_id' => $marketplaceAdmin->marketplace_client_id,
                        'marketplace_organizer_id' => $data['marketplace_organizer_id'],
                        'event_id' => $data['event_id'] ?? null,
                        'amount' => (float) $data['net_amount'],
                        'currency' => 'RON',
                        'period_start' => $data['period_start'],
                        'period_end' => $data['period_end'],
                        'gross_amount' => (float) $data['gross_amount'],
                        'commission_amount' => (float) ($data['commission_amount'] ?? 0),
                        'fees_amount' => (float) ($data['fees_amount'] ?? 0),
                        'adjustments_amount' => 0,
                        'status' => 'pending',
                        'source' => 'manual',
                        'payout_method' => $payoutMethod,
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Decont creat')
                        ->body("Decontul {$payout->reference} a fost creat cu succes.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Calculate available balance for an event
     */
    protected static function calculateEventBalance(Event $event): float
    {
        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) return 0;

        $completedOrders = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->get();

        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        $grossRevenue = (float) $completedOrders->sum('total');
        $subtotalRevenue = (float) $completedOrders->sum('subtotal');

        if ($commissionMode === 'added_on_top') {
            $netRevenue = $subtotalRevenue;
        } else {
            $commissionAmount = round($grossRevenue * ($commissionRate / 100), 2);
            $netRevenue = $grossRevenue - $commissionAmount;
        }

        $eventPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->sum('amount');

        $eventPendingPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        return max(0, $netRevenue - $eventPayouts - $eventPendingPayouts);
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => { const header = document.querySelector('.fi-header'); if (!header) return; const actions = header.querySelector('.fi-header-actions-ctn'); if (actions) header.insertBefore(\$el, actions); else header.appendChild(\$el); \$el.style.flex = '1'; \$el.style.minWidth = '0'; const nav = \$el.querySelector('.fi-tabs'); if (nav) { nav.style.marginInline = 'unset'; nav.style.marginLeft = 'auto'; nav.style.marginRight = '0'; } })",
            ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'processing' => Tab::make('Processing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing')),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
        ];
    }
}
