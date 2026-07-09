<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Services\Analytics\InvitationAbuseAnalyzer;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class InvitationAbuseReport extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Alerte invitații';
    protected static ?int $navigationSort = 999;
    protected static bool $shouldRegisterNavigation = false; // hidden — reached from dashboard header link
    protected string $view = 'filament.marketplace.pages.invitation-abuse-report';

    public ?MarketplaceClient $marketplace = null;

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;
    }

    public function getTitle(): string
    {
        return 'Raport comision pierdut prin invitații gratuite';
    }

    public function getHeading(): string|null
    {
        return null;
    }

    public function getViewData(): array
    {
        if (!$this->marketplace) {
            return ['marketplace' => null, 'invitationAbuse' => null];
        }

        $analyzer = app(InvitationAbuseAnalyzer::class);
        $invitationAbuse = $analyzer->analyze(
            $this->marketplace->id,
            request()->has('refresh_invite_abuse')
        );

        return [
            'marketplace' => $this->marketplace,
            'invitationAbuse' => $invitationAbuse,
        ];
    }
}
