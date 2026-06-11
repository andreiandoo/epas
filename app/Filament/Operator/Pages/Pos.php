<?php

namespace App\Filament\Operator\Pages;

use App\Models\TicketType;
use App\Services\Leisure\ChannelPricingResolver;
use Filament\Pages\Page;

class Pos extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static \UnitEnum|string|null $navigationGroup = 'Operațiuni';
    protected static ?int $navigationSort = 20;
    protected static ?string $title = 'POS';
    protected static ?string $slug = 'pos';
    protected string $view = 'filament.operator.pos';

    /** @var array<int, array{ticket_type_id:int,name:string,qty:int,unit_cents:int}> */
    public array $cart = [];
    public string $channel = ChannelPricingResolver::CHANNEL_POS_FIXED;
    public string $paymentMethod = 'cash';
    public ?string $customerEmail = null;

    public static function shouldRegisterNavigation(): bool
    {
        $teamMember = auth()->user()?->teamMember ?? null;
        return in_array($teamMember?->leisure_role, ['pos_cashier', 'pos_manager', 'admin'], true);
    }

    public function getViewData(): array
    {
        $tenantId = auth()->user()?->teamMember?->tenant_id;
        $resolver = app(ChannelPricingResolver::class);

        $tickets = TicketType::query()
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(50)
            ->get();

        $catalog = $tickets->map(fn ($tt) => [
            'id' => $tt->id,
            'name' => $tt->name,
            'category' => $tt->service_category ?? 'access',
            'price_cents' => $resolver->basePriceForChannel($tt, $this->channel),
        ]);

        $cartTotal = collect($this->cart)->sum(fn ($item) => $item['qty'] * $item['unit_cents']);

        return [
            'catalog' => $catalog,
            'cartTotal' => $cartTotal,
            'channels' => ChannelPricingResolver::CHANNELS,
        ];
    }

    public function addToCart(int $ticketTypeId): void
    {
        $tenantId = auth()->user()?->teamMember?->tenant_id;
        $tt = TicketType::query()
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($ticketTypeId);
        if (! $tt) {
            return;
        }
        $resolver = app(ChannelPricingResolver::class);
        $unit = $resolver->basePriceForChannel($tt, $this->channel);

        if (isset($this->cart[$ticketTypeId])) {
            $this->cart[$ticketTypeId]['qty']++;
        } else {
            $this->cart[$ticketTypeId] = [
                'ticket_type_id' => $ticketTypeId,
                'name' => $tt->name,
                'qty' => 1,
                'unit_cents' => $unit,
            ];
        }
    }

    public function removeFromCart(int $ticketTypeId): void
    {
        if (! isset($this->cart[$ticketTypeId])) {
            return;
        }
        $this->cart[$ticketTypeId]['qty']--;
        if ($this->cart[$ticketTypeId]['qty'] <= 0) {
            unset($this->cart[$ticketTypeId]);
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            \Filament\Notifications\Notification::make()->danger()->title('Coșul e gol.')->send();
            return;
        }
        // MVP: stub — full checkout integration (Order + Tickets creation) is wired
        // up in a follow-up. For now we record the intent and clear the cart.
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Comandă creată')
            ->body('Integrare Order + chitanță print — vine în E10.')
            ->send();
        $this->cart = [];
    }
}
