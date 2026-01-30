<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Services\Tax\TaxReportService;

class EventTaxReport extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Event Tax Report';
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.marketplace.pages.event-tax-report';

    public ?Event $event = null;
    public array $taxReport = [];

    public function mount(int $event): void
    {
        $marketplace = static::getMarketplaceClient();

        $this->event = Event::where('id', $event)
            ->where('marketplace_client_id', $marketplace?->id)
            ->firstOrFail();

        $service = app(TaxReportService::class);
        $this->taxReport = $service->calculateEventTaxes($this->event, $marketplace);
    }

    public function getTitle(): string
    {
        return 'Tax Report: ' . ($this->event?->getTranslation('title', 'ro') ?: $this->event?->getTranslation('title', 'en') ?: 'Event');
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'event-tax-report/{event}';
    }

    public static function getRouteName(?Panel $panel = null): string
    {
        return 'filament.marketplace.pages.event-tax-report';
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.marketplace.pages.tax-reports') => 'Tax Reports',
            '#' => $this->getTitle(),
        ];
    }
}
