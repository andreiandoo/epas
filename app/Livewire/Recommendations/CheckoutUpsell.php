<?php

namespace App\Livewire\Recommendations;

use App\Services\Tracking\RecommendationService;
use Livewire\Component;

class CheckoutUpsell extends Component
{
    public int $tenantId;
    public int $eventId;
    public ?int $personId = null;

    public array $upsellData = [];
    public bool $showVipUpgrade = false;
    public bool $showMerchandise = false;
    public bool $showParking = false;
    public float $upgradeScore = 0;

    public function mount(int $tenantId, int $eventId, ?int $personId = null): void
    {
        $this->tenantId = $tenantId;
        $this->eventId = $eventId;
        $this->personId = $personId;

        $this->loadUpsellData();
    }

    protected function loadUpsellData(): void
    {
        if (!$this->personId) {
            // Show default upsells for anonymous users
            $this->showVipUpgrade = true;
            $this->showMerchandise = true;
            $this->showParking = true;
            $this->upgradeScore = 0.5;
            return;
        }

        try {
            $service = RecommendationService::for($this->tenantId, $this->personId);
            $this->upsellData = $service->getCrossSellRecommendations($this->eventId);

            $this->upgradeScore = $this->upsellData['upgrade_propensity'] ?? 0;

            foreach ($this->upsellData['recommendations'] ?? [] as $rec) {
                if ($rec['show'] ?? false) {
                    match ($rec['type']) {
                        'vip_upgrade' => $this->showVipUpgrade = true,
                        'merchandise' => $this->showMerchandise = true,
                        'parking' => $this->showParking = true,
                        default => null,
                    };
                }
            }
        } catch (\Exception $e) {
            // Fallback to showing all upsells
            $this->showVipUpgrade = true;
            $this->showMerchandise = true;
            $this->showParking = true;
        }
    }

    public function render()
    {
        return view('livewire.recommendations.checkout-upsell');
    }
}
