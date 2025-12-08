<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomerEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class LiveEventStream extends Widget
{
    protected static string $view = 'filament.widgets.live-event-stream';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public int $maxEvents = 20;
    public ?int $tenantId = null;
    public Collection $events;
    public int $newEventsCount = 0;
    public ?int $lastEventId = null;
    public bool $isPaused = false;

    public function mount(): void
    {
        $this->events = collect();
        $this->loadInitialEvents();
    }

    public function loadInitialEvents(): void
    {
        $this->events = CoreCustomerEvent::query()
            ->with('coreCustomer')
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->orderByDesc('created_at')
            ->limit($this->maxEvents)
            ->get()
            ->map(fn($event) => $this->formatEvent($event));

        $this->lastEventId = $this->events->first()['id'] ?? null;
    }

    public function pollForNewEvents(): void
    {
        if ($this->isPaused) {
            return;
        }

        $query = CoreCustomerEvent::query()
            ->with('coreCustomer')
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->orderByDesc('created_at');

        if ($this->lastEventId) {
            $query->where('id', '>', $this->lastEventId);
        }

        $newEvents = $query->limit(50)->get();

        if ($newEvents->isNotEmpty()) {
            $formattedNewEvents = $newEvents->map(fn($event) => $this->formatEvent($event));

            // Prepend new events to the list
            $this->events = $formattedNewEvents->concat($this->events)->take($this->maxEvents);
            $this->lastEventId = $formattedNewEvents->first()['id'];
            $this->newEventsCount += $newEvents->count();
        }
    }

    public function togglePause(): void
    {
        $this->isPaused = !$this->isPaused;
    }

    public function clearNewCount(): void
    {
        $this->newEventsCount = 0;
    }

    protected function formatEvent(CoreCustomerEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->event_type,
            'type_label' => $this->getEventTypeLabel($event->event_type),
            'type_color' => $this->getEventTypeColor($event->event_type),
            'type_icon' => $this->getEventTypeIcon($event->event_type),
            'category' => $event->event_category,
            'page_url' => $event->page_url,
            'page_title' => $event->page_title,
            'value' => $event->conversion_value,
            'currency' => $event->currency ?? 'USD',
            'customer_name' => $event->coreCustomer?->getDisplayName() ?? 'Anonymous',
            'customer_id' => $event->coreCustomer?->uuid,
            'location' => $this->formatLocation($event),
            'source' => $event->getAttributionSource(),
            'device' => $event->device_type ?? 'Unknown',
            'device_icon' => $this->getDeviceIcon($event->device_type),
            'created_at' => $event->created_at->toIso8601String(),
            'time_ago' => $event->created_at->diffForHumans(),
            'timestamp' => $event->created_at->format('H:i:s'),
            'has_click_id' => !empty($event->gclid) || !empty($event->fbclid) || !empty($event->ttclid) || !empty($event->li_fat_id),
            'click_id_type' => $this->getClickIdType($event),
        ];
    }

    protected function getEventTypeLabel(string $type): string
    {
        return match ($type) {
            'page_view' => 'Page View',
            'view_item' => 'View Item',
            'add_to_cart' => 'Add to Cart',
            'begin_checkout' => 'Checkout',
            'purchase' => 'Purchase',
            'sign_up' => 'Sign Up',
            'login' => 'Login',
            'lead' => 'Lead',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    protected function getEventTypeColor(string $type): string
    {
        return match ($type) {
            'purchase' => 'success',
            'add_to_cart' => 'warning',
            'begin_checkout' => 'info',
            'sign_up', 'lead' => 'primary',
            'page_view', 'view_item' => 'gray',
            default => 'gray',
        };
    }

    protected function getEventTypeIcon(string $type): string
    {
        return match ($type) {
            'purchase' => 'heroicon-m-shopping-cart',
            'add_to_cart' => 'heroicon-m-shopping-bag',
            'begin_checkout' => 'heroicon-m-credit-card',
            'page_view' => 'heroicon-m-eye',
            'view_item' => 'heroicon-m-cursor-arrow-rays',
            'sign_up' => 'heroicon-m-user-plus',
            'login' => 'heroicon-m-arrow-right-on-rectangle',
            'lead' => 'heroicon-m-document-text',
            default => 'heroicon-m-bolt',
        };
    }

    protected function getDeviceIcon(?string $device): string
    {
        return match ($device) {
            'desktop' => 'heroicon-m-computer-desktop',
            'mobile' => 'heroicon-m-device-phone-mobile',
            'tablet' => 'heroicon-m-device-tablet',
            default => 'heroicon-m-question-mark-circle',
        };
    }

    protected function formatLocation(CoreCustomerEvent $event): string
    {
        $parts = array_filter([$event->city, $event->country_code]);
        return implode(', ', $parts) ?: 'Unknown';
    }

    protected function getClickIdType(CoreCustomerEvent $event): ?string
    {
        if ($event->gclid) return 'Google';
        if ($event->fbclid) return 'Facebook';
        if ($event->ttclid) return 'TikTok';
        if ($event->li_fat_id) return 'LinkedIn';
        return null;
    }

    public static function canView(): bool
    {
        return true;
    }
}
