<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;

class EventActivityLog extends Page
{
    use InteractsWithRecord;
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Activity Log';

    protected string $view = 'filament.marketplace.resources.event-resource.pages.event-activity-log';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Verify this event belongs to the current marketplace
        $marketplace = static::getMarketplaceClient();

        if ($this->record->marketplace_client_id !== $marketplace?->id) {
            abort(403, 'Unauthorized access to this event');
        }
    }

    public function getBreadcrumb(): string
    {
        return 'Activity Log';
    }

    /**
     * Get activity logs for this event
     */
    public function getActivityLogs(): \Illuminate\Support\Collection
    {
        return Activity::query()
            ->where('subject_type', get_class($this->record))
            ->where('subject_id', $this->record->id)
            ->with('causer')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Activity $activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'causer_name' => $this->getCauserName($activity),
                    'causer_type' => $this->getCauserType($activity),
                    'causer_email' => $this->getCauserEmail($activity),
                    'changes' => $this->formatChanges($activity),
                    'created_at' => $activity->created_at,
                    'formatted_date' => $activity->created_at->format('d M Y'),
                    'formatted_time' => $activity->created_at->format('H:i:s'),
                    'relative_time' => $activity->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * Get the causer's display name
     */
    protected function getCauserName(Activity $activity): string
    {
        if (!$activity->causer) {
            return 'System';
        }

        // Check if it's a User model
        if ($activity->causer_type === 'App\\Models\\User') {
            return $activity->causer->name ?? $activity->causer->email ?? 'Admin User';
        }

        // Check if it's a MarketplaceOrganizer
        if ($activity->causer_type === 'App\\Models\\MarketplaceOrganizer') {
            return $activity->causer->name ?? $activity->causer->email ?? 'Organizer';
        }

        // Check if it's a Customer
        if ($activity->causer_type === 'App\\Models\\Customer') {
            $firstName = $activity->causer->first_name ?? '';
            $lastName = $activity->causer->last_name ?? '';
            return trim($firstName . ' ' . $lastName) ?: $activity->causer->email ?? 'Customer';
        }

        return 'Unknown';
    }

    /**
     * Get the causer type label
     */
    protected function getCauserType(Activity $activity): string
    {
        if (!$activity->causer) {
            return 'system';
        }

        return match ($activity->causer_type) {
            'App\\Models\\User' => 'admin',
            'App\\Models\\MarketplaceOrganizer' => 'organizer',
            'App\\Models\\Customer' => 'customer',
            default => 'unknown',
        };
    }

    /**
     * Get the causer's email
     */
    protected function getCauserEmail(Activity $activity): ?string
    {
        if (!$activity->causer) {
            return null;
        }

        return $activity->causer->email ?? null;
    }

    /**
     * Format the changes for display
     */
    protected function formatChanges(Activity $activity): array
    {
        $changes = [];
        $properties = $activity->properties;

        // Check for old and new values (for updates)
        if ($properties->has('old') && $properties->has('attributes')) {
            $old = $properties->get('old');
            $new = $properties->get('attributes');

            foreach ($new as $key => $newValue) {
                $oldValue = $old[$key] ?? null;

                // Skip if values are the same
                if ($oldValue === $newValue) {
                    continue;
                }

                // Format the field name nicely
                $fieldName = $this->formatFieldName($key);

                // Format values for display
                $changes[] = [
                    'field' => $fieldName,
                    'old' => $this->formatValue($oldValue),
                    'new' => $this->formatValue($newValue),
                ];
            }
        }

        // For create events, show all attributes
        if ($activity->event === 'created' && $properties->has('attributes')) {
            $attributes = $properties->get('attributes');
            foreach ($attributes as $key => $value) {
                if ($value !== null && $value !== '') {
                    $changes[] = [
                        'field' => $this->formatFieldName($key),
                        'old' => null,
                        'new' => $this->formatValue($value),
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Format field name for display
     */
    protected function formatFieldName(string $field): string
    {
        // Convert snake_case to Title Case
        $formatted = str_replace('_', ' ', $field);
        return ucwords($formatted);
    }

    /**
     * Format value for display
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '(empty)';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        if ($value instanceof Carbon) {
            return $value->format('d M Y H:i');
        }

        // Check if it's a date string
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('d M Y H:i');
            } catch (\Exception $e) {
                // Not a valid date, return as is
            }
        }

        // Truncate long strings
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }

        return (string) $value;
    }

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back_to_edit')
                ->label('Back to Edit')
                ->icon('heroicon-o-arrow-left')
                ->url(EventResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
