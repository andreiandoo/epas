<?php

namespace App\Livewire\Recommendations;

use App\Services\Tracking\RecommendationService;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;

class RecommendedEvents extends Component
{
    public int $tenantId;
    public ?int $personId = null;
    public int $limit = 4;
    public string $title = 'Recommended For You';
    public string $emptyMessage = 'Check back soon for personalized recommendations';
    public bool $showReasons = true;

    public array $recommendations = [];
    public bool $loading = true;

    public function mount(int $tenantId, ?int $personId = null, int $limit = 4): void
    {
        $this->tenantId = $tenantId;
        $this->personId = $personId;
        $this->limit = $limit;
    }

    public function loadRecommendations(): void
    {
        $this->loading = true;

        if (!$this->personId) {
            $this->recommendations = [];
            $this->loading = false;
            return;
        }

        try {
            $cacheKey = "livewire:recs:{$this->tenantId}:{$this->personId}:{$this->limit}";

            $this->recommendations = Cache::remember($cacheKey, 1800, function () {
                $service = RecommendationService::for($this->tenantId, $this->personId);
                $result = $service->getEventRecommendations($this->limit);
                return $result['recommendations'] ?? [];
            });
        } catch (\Exception $e) {
            $this->recommendations = [];
        }

        $this->loading = false;
    }

    public function render()
    {
        // Load recommendations on first render
        if ($this->loading && $this->personId) {
            $this->loadRecommendations();
        }

        return view('livewire.recommendations.recommended-events');
    }
}
