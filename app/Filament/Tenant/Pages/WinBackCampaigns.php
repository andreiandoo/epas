<?php

namespace App\Filament\Tenant\Pages;

use App\Services\Tracking\WinBackCampaignService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class WinBackCampaigns extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Win-Back Campaigns';
    protected static \UnitEnum|string|null $navigationGroup = 'Analytics & Tracking';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'winback-campaigns';
    protected string $view = 'filament.tenant.pages.winback-campaigns';

    public array $candidates = [];
    public array $stats = [];
    public string $selectedTier = 'all';

    public function mount(): void
    {
        $this->loadCandidates();
    }

    protected function loadCandidates(): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        $service = WinBackCampaignService::forTenant($tenant->id);
        $result = $service->identifyWinBackCandidates();

        $this->candidates = $result['candidates'] ?? [];
        $this->stats = $result['summary'] ?? [];
    }

    public function filterByTier(string $tier): void
    {
        $this->selectedTier = $tier;
    }

    public function getFilteredCandidates(): array
    {
        if ($this->selectedTier === 'all') {
            $all = [];
            foreach ($this->candidates as $tier => $items) {
                foreach ($items as $item) {
                    $item['tier'] = $tier;
                    $all[] = $item;
                }
            }
            // Sort by LTV descending
            usort($all, fn($a, $b) => ($b['ltv'] ?? 0) <=> ($a['ltv'] ?? 0));
            return array_slice($all, 0, 50);
        }

        return $this->candidates[$this->selectedTier] ?? [];
    }

    public function launchCampaign(string $tier): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        $candidates = $this->candidates[$tier] ?? [];
        if (empty($candidates)) {
            Notification::make()
                ->title('No candidates')
                ->body("No customers found in the {$tier} tier")
                ->warning()
                ->send();
            return;
        }

        $personIds = array_column($candidates, 'person_id');
        $campaignId = 'winback_' . $tier . '_' . now()->format('Ymd_His');

        $service = WinBackCampaignService::forTenant($tenant->id);
        $count = $service->markAsContacted($personIds, $tier, $campaignId);

        // In a real implementation, this would trigger email sending
        // dispatch(new SendWinBackEmailsJob($tenant->id, $personIds, $tier, $campaignId));

        Notification::make()
            ->title('Campaign Launched')
            ->body("Win-back campaign started for {$count} customers in the {$tier} tier. Campaign ID: {$campaignId}")
            ->success()
            ->send();

        $this->loadCandidates();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn() => $this->loadCandidates()),

            Action::make('launch_early_warning')
                ->label('Launch Early Warning')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Launch Early Warning Campaign')
                ->modalDescription('This will send reminder emails to customers who are showing early signs of churn (30-60 days inactive).')
                ->action(fn() => $this->launchCampaign('early_warning')),

            Action::make('launch_winback')
                ->label('Launch Win-Back')
                ->icon('heroicon-o-gift')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Launch Win-Back Campaign')
                ->modalDescription('This will send discount offers to customers who have been inactive for 91-180 days.')
                ->action(fn() => $this->launchCampaign('win_back')),
        ];
    }

    public function getHeading(): string
    {
        return 'Win-Back Campaigns';
    }

    public function getSubheading(): ?string
    {
        return 'Re-engage at-risk and lapsed customers with personalized offers';
    }
}
