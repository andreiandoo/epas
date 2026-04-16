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
    public int $organizerCount = 0;
    public ?object $customerCampaign = null;
    public ?object $organizerCampaign = null;

    public function mount(): void
    {
        $clientId = static::getMarketplaceClientId();

        $this->customerCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
            ->whereNotNull('password')
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

        $this->customerCampaign = DB::table('bulk_password_reset_campaigns')
            ->where('marketplace_client_id', $clientId)
            ->where('type', 'customer')
            ->orderByDesc('id')
            ->first();

        $this->organizerCampaign = DB::table('bulk_password_reset_campaigns')
            ->where('marketplace_client_id', $clientId)
            ->where('type', 'organizer')
            ->orderByDesc('id')
            ->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_customer_campaign')
                ->label('Pornește campanie clienți')
                ->icon('heroicon-o-users')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Trimite email de resetare parolă la toți clienții?')
                ->modalDescription("Se vor trimite {$this->customerCount} emailuri în batch-uri de 200 cu 10s delay.")
                ->visible(fn () => !$this->customerCampaign || in_array($this->customerCampaign->status, ['completed', 'failed', 'draft']))
                ->action(fn () => $this->startCampaign('customer')),

            Action::make('start_organizer_campaign')
                ->label('Pornește campanie organizatori')
                ->icon('heroicon-o-building-office')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Trimite email de resetare parolă la toți organizatorii?')
                ->modalDescription("Se vor trimite {$this->organizerCount} emailuri.")
                ->visible(fn () => !$this->organizerCampaign || in_array($this->organizerCampaign->status, ['completed', 'failed', 'draft']))
                ->action(fn () => $this->startCampaign('organizer')),

            Action::make('pause_customer')
                ->label('Pauză clienți')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->customerCampaign?->status === 'sending')
                ->action(fn () => $this->pauseCampaign('customer')),

            Action::make('resume_customer')
                ->label('Continuă clienți')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->customerCampaign?->status === 'paused')
                ->action(fn () => $this->resumeCampaign('customer')),

            Action::make('pause_organizer')
                ->label('Pauză organizatori')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->organizerCampaign?->status === 'sending')
                ->action(fn () => $this->pauseCampaign('organizer')),

            Action::make('resume_organizer')
                ->label('Continuă organizatori')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->organizerCampaign?->status === 'paused')
                ->action(fn () => $this->resumeCampaign('organizer')),
        ];
    }

    protected function startCampaign(string $type): void
    {
        $clientId = static::getMarketplaceClientId();
        $total = $type === 'customer' ? $this->customerCount : $this->organizerCount;
        $templateSlug = $type === 'customer' ? 'bulk_password_reset_customer' : 'bulk_password_reset_organizer';

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

    protected function pauseCampaign(string $type): void
    {
        $campaign = $type === 'customer' ? $this->customerCampaign : $this->organizerCampaign;
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
        $campaign = $type === 'customer' ? $this->customerCampaign : $this->organizerCampaign;
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
