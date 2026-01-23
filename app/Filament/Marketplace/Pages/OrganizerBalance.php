<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizerBalance extends Page
{
    use HasMarketplaceContext;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'organizers/{id}/balance';
    protected string $view = 'filament.marketplace.pages.organizer-balance';

    public ?int $organizerId = null;
    public ?MarketplaceOrganizer $organizer = null;

    public function mount(int $id): void
    {
        $marketplace = static::getMarketplaceClient();

        $this->organizer = MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
            ->findOrFail($id);
        $this->organizerId = $id;
    }

    public function getTitle(): string
    {
        return 'Balance: ' . ($this->organizer?->name ?? 'Organizer');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.marketplace.pages.balances') => 'Balances',
            '#' => $this->organizer?->name ?? 'Organizer',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Balances')
                ->url(route('filament.marketplace.pages.balances'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
            Actions\Action::make('create_payout')
                ->label('Create Payout')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->organizer && $this->organizer->available_balance > 0)
                ->modalHeading('Create Payout')
                ->modalDescription(fn () => 'Available balance: ' . number_format($this->organizer->available_balance, 2) . ' RON')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount (RON)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(fn () => (float) $this->organizer->available_balance)
                        ->default(fn () => (float) $this->organizer->available_balance)
                        ->suffix('RON')
                        ->helperText(fn () => 'Maximum: ' . number_format($this->organizer->available_balance, 2) . ' RON'),
                    Forms\Components\Placeholder::make('bank_info')
                        ->label('Bank Details')
                        ->content(fn () => new \Illuminate\Support\HtmlString(
                            '<div class="space-y-1">' .
                            '<div><span class="font-medium text-gray-500 dark:text-gray-400">Bank:</span> <span class="text-gray-900 dark:text-white">' . ($this->organizer->bank_name ?: 'Not provided') . '</span></div>' .
                            '<div><span class="font-medium text-gray-500 dark:text-gray-400">IBAN:</span> <span class="font-mono text-gray-900 dark:text-white">' . ($this->organizer->iban ?: 'Not provided') . '</span></div>' .
                            '</div>'
                        )),
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference')
                        ->required()
                        ->placeholder('Transfer number or reference')
                        ->helperText('Enter the bank transfer reference number'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Notes (optional)')
                        ->rows(2)
                        ->placeholder('Additional notes about this payment'),
                ])
                ->action(function (array $data) {
                    $amount = (float) $data['amount'];

                    if ($amount > (float) $this->organizer->available_balance) {
                        Notification::make()
                            ->danger()
                            ->title('Insufficient Balance')
                            ->body('The amount exceeds the available balance.')
                            ->send();
                        return;
                    }

                    $marketplace = static::getMarketplaceClient();

                    // Reserve the balance
                    $this->organizer->reserveBalanceForPayout($amount);

                    // Create payout record
                    $payout = MarketplacePayout::create([
                        'marketplace_client_id' => $marketplace->id,
                        'marketplace_organizer_id' => $this->organizer->id,
                        'amount' => $amount,
                        'currency' => 'RON',
                        'status' => 'processing',
                    ]);

                    // Complete the payout immediately (admin is recording a completed transfer)
                    $payout->complete($data['payment_reference'], $data['payment_notes'] ?? null);

                    // Refresh organizer data
                    $this->organizer->refresh();

                    Notification::make()
                        ->success()
                        ->title('Payout Created')
                        ->body("Payout of {$amount} RON completed with reference: {$data['payment_reference']}")
                        ->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        // Revenue per event
        $revenuePerEvent = Order::query()
            ->where('marketplace_organizer_id', $this->organizerId)
            ->where('status', 'completed')
            ->select(
                'marketplace_event_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(subtotal) as gross_revenue'),
                DB::raw('SUM(commission_amount) as total_commission'),
                DB::raw('SUM(subtotal) - SUM(commission_amount) as net_revenue')
            )
            ->groupBy('marketplace_event_id')
            ->with('marketplaceEvent')
            ->get();

        // Payout history
        $payouts = MarketplacePayout::query()
            ->where('marketplace_organizer_id', $this->organizerId)
            ->orderByDesc('created_at')
            ->get();

        return [
            'organizer' => $this->organizer,
            'revenuePerEvent' => $revenuePerEvent,
            'payouts' => $payouts,
        ];
    }
}
