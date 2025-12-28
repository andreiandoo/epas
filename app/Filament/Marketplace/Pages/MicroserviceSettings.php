<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Models\Microservice;
use BackedEnum;
use Filament\Actions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MicroserviceSettings extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'microservices/{slug}/settings';
    protected string $view = 'filament.marketplace.pages.microservice-settings';

    public ?string $microserviceSlug = null;
    public ?Microservice $microservice = null;
    public ?MarketplaceClient $marketplace = null;

    public function mount(string $slug): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;

        if (!$this->marketplace) {
            abort(404);
        }

        $this->microserviceSlug = $slug;
        $this->microservice = Microservice::where('slug', $slug)->firstOrFail();
    }

    public function getTitle(): string
    {
        return $this->microservice?->getTranslation('name', app()->getLocale()) . ' Settings';
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.marketplace.pages.microservices') => 'Microservices',
            '#' => $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Microservices')
                ->url(route('filament.marketplace.pages.microservices'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function getViewData(): array
    {
        return [
            'microservice' => $this->microservice,
            'marketplace' => $this->marketplace,
            'message' => 'Microservice settings are managed at the platform level. Contact support to configure this microservice for your marketplace.',
        ];
    }
}
