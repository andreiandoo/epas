<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Jobs\SendBulkPasswordResetJob;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class BulkPasswordReset extends Page
{
    use HasMarketplaceContext;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Resetare parolă în masă';
    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 90;
    protected string $view = 'filament.marketplace.pages.bulk-password-reset';

    public int $customerCount = 0;
    public int $guestCount = 0;
    public int $wpUserCount = 0;
    public int $organizerCount = 0;
    public ?object $customerCampaign = null;
    public ?object $guestCampaign = null;
    public ?object $wpUserCampaign = null;
    public ?object $organizerCampaign = null;

    public function mount(): void
    {
        $clientId = static::getMarketplaceClientId();

        // Registered customers (have bcrypt password, no WP hash)
        $this->customerCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
            ->whereNotNull('password')
            ->whereNull('wp_password_hash')
            ->where('status', 'active')
            ->count();

        // WP imported users (have WP phpass hash, no bcrypt password)
        $this->wpUserCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
            ->whereNull('password')
            ->whereNotNull('wp_password_hash')
            ->where('status', 'active')
            ->count();

        // Pure guests (no password at all)
        $this->guestCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
            ->whereNull('password')
            ->whereNull('wp_password_hash')
            ->where('status', 'active')
            ->count();

        $this->organizerCount = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->whereNotNull('password')
            ->whereIn('status', ['active', 'pending'])
            ->count();

        $this->loadCampaigns();
    }

    protected function loadCampaigns(): void
    {
        $clientId = static::getMarketplaceClientId();

        foreach (['customer', 'guest', 'wp_user', 'organizer'] as $type) {
            $campaign = DB::table('bulk_password_reset_campaigns')
                ->where('marketplace_client_id', $clientId)
                ->where('type', $type)
                ->orderByDesc('id')
                ->first();
            $prop = lcfirst(str_replace('_', '', ucwords($type, '_'))) . 'Campaign';
            $this->$prop = $campaign;
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $segments = [
            ['type' => 'customer', 'label' => 'clienți', 'icon' => 'heroicon-o-users', 'color' => 'danger', 'count' => $this->customerCount, 'campaign' => $this->customerCampaign],
            ['type' => 'guest', 'label' => 'guests', 'icon' => 'heroicon-o-user', 'color' => 'gray', 'count' => $this->guestCount, 'campaign' => $this->guestCampaign],
            ['type' => 'wp_user', 'label' => 'useri WP', 'icon' => 'heroicon-o-arrow-path', 'color' => 'warning', 'count' => $this->wpUserCount, 'campaign' => $this->wpUserCampaign],
            ['type' => 'organizer', 'label' => 'organizatori', 'icon' => 'heroicon-o-building-office', 'color' => 'info', 'count' => $this->organizerCount, 'campaign' => $this->organizerCampaign],
        ];

        foreach ($segments as $seg) {
            $actions[] = Action::make("start_{$seg['type']}_campaign")
                ->label("Pornește campanie {$seg['label']}")
                ->icon($seg['icon'])
                ->color($seg['color'])
                ->requiresConfirmation()
                ->modalHeading("Trimite email de setare parolă la {$seg['label']}?")
                ->modalDescription("Se vor trimite {$seg['count']} emailuri în batch-uri de 200 cu 10s delay.")
                ->visible(fn () => $seg['count'] > 0 && (!$seg['campaign'] || in_array($seg['campaign']->status, ['completed', 'failed', 'draft'])))
                ->action(fn () => $this->startCampaign($seg['type']));

            $actions[] = Action::make("pause_{$seg['type']}")
                ->label("Pauză {$seg['label']}")
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $seg['campaign']?->status === 'sending')
                ->action(fn () => $this->pauseCampaign($seg['type']));

            $actions[] = Action::make("resume_{$seg['type']}")
                ->label("Continuă {$seg['label']}")
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $seg['campaign']?->status === 'paused')
                ->action(fn () => $this->resumeCampaign($seg['type']));
        }

        return $actions;
    }

    protected function startCampaign(string $type): void
    {
        $clientId = static::getMarketplaceClientId();
        $total = match ($type) {
            'customer' => $this->customerCount,
            'guest' => $this->guestCount,
            'wp_user' => $this->wpUserCount,
            'organizer' => $this->organizerCount,
            default => 0,
        };
        $templateSlug = match ($type) {
            'organizer' => 'bulk_password_reset_organizer',
            default => 'bulk_password_reset_customer',
        };

        $id = DB::table('bulk_password_reset_campaigns')->insertGetId([
            'marketplace_client_id' => $clientId,
            'type' => $type,
            'template_slug' => $templateSlug,
            'status' => 'sending',
            'total_recipients' => $total,
            'sent_count' => 0,
            'failed_count' => 0,
            'last_processed_id' => 0,
            'batch_size' => 200,
            'delay_seconds' => 10,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SendBulkPasswordResetJob::dispatch($id);

        Notification::make()->title('Campanie pornită!')->body("Trimitere email de resetare parolă către {$total} " . ($type === 'customer' ? 'clienți' : 'organizatori'))->success()->send();

        $this->loadCampaigns();
    }

    protected function getCampaignForType(string $type): ?object
    {
        return match ($type) {
            'customer' => $this->customerCampaign,
            'guest' => $this->guestCampaign,
            'wp_user' => $this->wpUserCampaign,
            'organizer' => $this->organizerCampaign,
            default => null,
        };
    }

    protected function pauseCampaign(string $type): void
    {
        $campaign = $this->getCampaignForType($type);
        if ($campaign) {
            DB::table('bulk_password_reset_campaigns')->where('id', $campaign->id)->update([
                'status' => 'paused', 'paused_at' => now(), 'updated_at' => now(),
            ]);
            Notification::make()->title('Campanie oprită')->warning()->send();
        }
        $this->loadCampaigns();
    }

    protected function resumeCampaign(string $type): void
    {
        $campaign = $this->getCampaignForType($type);
        if ($campaign) {
            DB::table('bulk_password_reset_campaigns')->where('id', $campaign->id)->update([
                'status' => 'sending', 'paused_at' => null, 'updated_at' => now(),
            ]);
            SendBulkPasswordResetJob::dispatch($campaign->id);
            Notification::make()->title('Campanie reluată')->success()->send();
        }
        $this->loadCampaigns();
    }

    public function getTitle(): string
    {
        return 'Resetare parolă în masă';
    }

    public function getPollingInterval(): ?string
    {
        // Auto-refresh every 10s when a campaign is active
        if (
            ($this->customerCampaign?->status === 'sending') ||
            ($this->organizerCampaign?->status === 'sending')
        ) {
            return '10s';
        }
        return null;
    }
}
