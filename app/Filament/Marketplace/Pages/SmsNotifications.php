<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceClient;
use App\Models\Microservice;
use App\Models\SmsCredit;
use App\Models\SmsLog;
use Stripe\StripeClient;

class SmsNotifications extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Notificări SMS';
    protected static \UnitEnum|string|null $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 15;
    protected string $view = 'filament.marketplace.pages.sms-notifications';

    public bool $transactionalEnabled = false;
    public bool $promotionalEnabled = false;
    public int $transactionalQuantity = 100;
    public int $promotionalQuantity = 100;
    public ?string $successMessage = null;

    public function getTitle(): string
    {
        return 'Notificări SMS';
    }

    public function mount(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return;
        }

        $config = $marketplace->getMicroserviceConfig('sms-notifications');
        if ($config) {
            $this->transactionalEnabled = $config['transactional_enabled'] ?? false;
            $this->promotionalEnabled = $config['promotional_enabled'] ?? false;
        }

        // Check for Stripe success return
        $sessionId = request()->query('session_id');
        if ($sessionId) {
            $this->handleStripeReturn($sessionId, $marketplace);
        }
    }

    protected function handleStripeReturn(string $sessionId, MarketplaceClient $marketplace): void
    {
        // Check if credits already created for this session
        $existing = SmsCredit::where('stripe_session_id', $sessionId)->first();
        if ($existing) {
            $this->successMessage = "Creditele au fost deja adăugate!";
            return;
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $metadata = $session->metadata->toArray();
                $creditType = $metadata['credit_type'] ?? 'transactional';
                $quantity = (int) ($metadata['quantity'] ?? 0);
                $pricePerSms = (float) ($metadata['price_per_sms'] ?? 0);

                if ($quantity > 0 && $pricePerSms > 0) {
                    SmsCredit::purchaseCredits(
                        $marketplace,
                        $creditType,
                        $quantity,
                        $pricePerSms,
                        $session->payment_intent,
                        $sessionId
                    );
                    $this->successMessage = "✓ {$quantity} credite SMS ({$creditType}) au fost adăugate cu succes!";
                }
            }
        } catch (\Throwable $e) {
            \Log::channel('marketplace')->error('Stripe SMS credit return failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function toggleTransactional(): void
    {
        $this->transactionalEnabled = !$this->transactionalEnabled;
        $this->saveSettings();
    }

    public function togglePromotional(): void
    {
        $this->promotionalEnabled = !$this->promotionalEnabled;
        $this->saveSettings();
    }

    protected function saveSettings(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return;
        }

        // Ensure microservice is linked
        $microservice = Microservice::where('slug', 'sms-notifications')->first();
        if (!$microservice) {
            return;
        }

        $pivot = $marketplace->microservices()->where('microservice_id', $microservice->id)->first();
        if (!$pivot) {
            // Activate microservice for this marketplace
            $marketplace->microservices()->attach($microservice->id, [
                'status' => 'active',
                'activated_at' => now(),
                'settings' => json_encode([
                    'transactional_enabled' => $this->transactionalEnabled,
                    'promotional_enabled' => $this->promotionalEnabled,
                ]),
            ]);
        } else {
            $settings = $pivot->pivot->settings ?? [];
            if (is_string($settings)) {
                $settings = json_decode($settings, true) ?? [];
            }
            $settings['transactional_enabled'] = $this->transactionalEnabled;
            $settings['promotional_enabled'] = $this->promotionalEnabled;

            $marketplace->microservices()->updateExistingPivot($microservice->id, [
                'status' => 'active',
                'settings' => json_encode($settings),
            ]);
        }
    }

    public function purchaseCredits(string $type): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return;
        }

        $quantity = $type === 'transactional' ? $this->transactionalQuantity : $this->promotionalQuantity;
        if ($quantity < 1) {
            return;
        }

        $pricing = $this->getSmsPricing();
        $pricePerSms = $pricing[$type]['price'] ?? ($type === 'promotional' ? 0.50 : 0.40);
        $totalEur = round($quantity * $pricePerSms, 2);
        $amountCents = (int) round($totalEur * 100);

        $typeLabel = $type === 'transactional' ? 'Tranzacționale' : 'Promoționale';

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => "Credite SMS {$typeLabel} ({$quantity} buc)",
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/marketplace/sms-notifications') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/marketplace/sms-notifications'),
                'metadata' => [
                    'creditable_type' => get_class($marketplace),
                    'creditable_id' => $marketplace->id,
                    'credit_type' => $type,
                    'quantity' => $quantity,
                    'price_per_sms' => $pricePerSms,
                ],
            ]);

            $this->redirect($session->url);
        } catch (\Throwable $e) {
            \Log::channel('marketplace')->error('Stripe SMS checkout creation failed', [
                'error' => $e->getMessage(),
                'marketplace_client_id' => $marketplace->id,
            ]);
        }
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();
        $pricing = $this->getSmsPricing();

        $transactionalCredits = 0;
        $promotionalCredits = 0;
        $recentLogs = [];

        if ($marketplace) {
            $transactionalCredits = SmsCredit::getAvailableCredits($marketplace, 'transactional');
            $promotionalCredits = SmsCredit::getAvailableCredits($marketplace, 'promotional');
            $recentLogs = SmsLog::where('marketplace_client_id', $marketplace->id)
                ->with('event')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        // Currency conversion for RON clients
        $clientCurrency = $marketplace ? strtoupper($marketplace->currency ?? 'EUR') : 'EUR';
        $eurToRon = null;
        if ($clientCurrency === 'RON') {
            $eurToRon = \App\Models\ExchangeRate::getLatestRate('EUR', 'RON');
        }

        return [
            'pricing' => $pricing,
            'transactionalCredits' => $transactionalCredits,
            'promotionalCredits' => $promotionalCredits,
            'recentLogs' => $recentLogs,
            'transactionalCost' => round($this->transactionalQuantity * ($pricing['transactional']['price'] ?? 0.40), 2),
            'promotionalCost' => round($this->promotionalQuantity * ($pricing['promotional']['price'] ?? 0.50), 2),
            'clientCurrency' => $clientCurrency,
            'eurToRon' => $eurToRon,
        ];
    }

    protected function getSmsPricing(): array
    {
        $microservice = Microservice::where('slug', 'sms-notifications')->first();
        if ($microservice && isset($microservice->metadata['sms_pricing'])) {
            return $microservice->metadata['sms_pricing'];
        }

        return [
            'transactional' => ['price' => config('microservices.sms.pricing.transactional', 0.40), 'currency' => 'EUR'],
            'promotional' => ['price' => config('microservices.sms.pricing.promotional', 0.50), 'currency' => 'EUR'],
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
