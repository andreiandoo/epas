<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\TicketType;
use App\Models\MarketplaceOrganizer;
use App\Models\Venue;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EventActivityLog extends Page
{
    use InteractsWithRecord;
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Istoric activitate';

    protected string $view = 'filament.marketplace.resources.event-resource.pages.event-activity-log';

    /** In-request memo for related-model name lookups (organizer, venue). */
    protected array $nameCache = [];

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
        return 'Istoric activitate';
    }

    /**
     * Romanian, human-readable labels for the event / ticket-type fields.
     * Anything not listed falls back to a prettified snake_case string.
     */
    protected function fieldLabels(): array
    {
        return [
            // Event — content
            'title' => 'Titlu',
            'subtitle' => 'Subtitlu',
            'slug' => 'Slug (URL)',
            'short_description' => 'Descriere scurtă',
            'description' => 'Descriere',
            'ticket_terms' => 'Termeni bilete',
            'thank_you_message' => 'Mesaj de mulțumire',
            'seo' => 'Setări SEO',
            'admin_notes' => 'Note interne',

            // Event — media
            'poster_url' => 'Imagine afiș',
            'poster_original_filename' => 'Fișier afiș',
            'hero_image_url' => 'Imagine hero',
            'hero_image_original_filename' => 'Fișier hero',
            'featured_image' => 'Imagine reprezentativă',
            'homepage_featured_image' => 'Imagine homepage',
            'video_url' => 'Video',

            // Event — location & links
            'address' => 'Adresă',
            'suggested_venue_name' => 'Locație sugerată',
            'venue_id' => 'Locație',
            'marketplace_city_id' => 'Oraș',
            'marketplace_event_category_id' => 'Categorie',
            'website_url' => 'Site web',
            'facebook_url' => 'Facebook',
            'event_website_url' => 'Site eveniment',
            'redirect_url' => 'URL redirect',

            // Event — scheduling
            'duration_mode' => 'Mod durată',
            'event_date' => 'Data evenimentului',
            'start_time' => 'Ora de început',
            'door_time' => 'Ora deschidere uși',
            'end_time' => 'Ora de sfârșit',
            'range_start_date' => 'Data început (interval)',
            'range_end_date' => 'Data sfârșit (interval)',
            'range_start_time' => 'Ora început (interval)',
            'range_end_time' => 'Ora sfârșit (interval)',
            'multi_slots' => 'Sloturi multi-zi',

            // Event — status / flags
            'is_published' => 'Publicat',
            'is_featured' => 'Recomandat',
            'is_promoted' => 'Promovat',
            'is_homepage_featured' => 'Recomandat pe homepage',
            'is_general_featured' => 'Recomandat general',
            'is_category_featured' => 'Recomandat în categorie',
            'is_city_featured' => 'Recomandat în oraș',
            'is_sold_out' => 'Sold out',
            'is_cancelled' => 'Anulat',
            'cancel_reason' => 'Motiv anulare',
            'is_postponed' => 'Amânat',
            'postponed_reason' => 'Motiv amânare',
            'is_template' => 'Șablon',
            'is_online' => 'Eveniment online',
            'door_sales_only' => 'Doar vânzări la ușă',
            'submitted_at' => 'Trimis spre aprobare',
            'rejected_at' => 'Respins',
            'rejection_reason' => 'Motiv respingere',
            'access_password' => 'Parolă acces',
            'pending_changes_status' => 'Status modificări în așteptare',

            // Event — commission / marketplace
            'commission_rate' => 'Rată comision',
            'commission_mode' => 'Mod comision',
            'use_fixed_commission' => 'Comision fix',
            'marketplace_organizer_id' => 'Organizator',
            'marketplace_client_id' => 'Marketplace',
            'display_template' => 'Șablon afișare',
            'target_price' => 'Preț țintă',
            'general_stock' => 'Stoc general',
            'general_quota' => 'Cotă generală',

            // Event — online
            'online_provider' => 'Furnizor online',
            'online_meeting_url' => 'Link întâlnire online',
            'online_passcode' => 'Cod acces online',
            'online_lobby_opens_minutes_before' => 'Lobby deschis (min. înainte)',

            // Event — options
            'enable_ticket_groups' => 'Grupuri de bilete',
            'enable_ticket_perks' => 'Beneficii bilete',
            'generate_fomo' => 'FOMO activ',
            'has_custom_related' => 'Evenimente similare personalizate',

            // TicketType
            'name' => 'Denumire bilet',
            'price_cents' => 'Preț',
            'quota_total' => 'Stoc',
            'status' => 'Status',
        ];
    }

    /**
     * Fields that are internal / auto-maintained and only add noise. Skipped
     * entirely from the human timeline.
     */
    protected function hiddenFields(): array
    {
        return [
            'updated_at', 'created_at', 'deleted_at', 'id', 'uuid',
            'views_count', 'interested_count', 'quota_sold',
            'has_session_today', 'has_session_tomorrow', 'has_session_this_weekend',
            'next_session_at', 'cheapest_price_cents',
            'fomo_displayed_remaining', 'fomo_displayed_remaining_updated_at',
            'pending_changes', 'pending_changes_submitted_at',
            'occurrence_number', 'sort_order', 'position',
        ];
    }

    /** Fields rendered as Da / Nu. */
    protected function booleanFields(): array
    {
        return [
            'is_published', 'is_featured', 'is_promoted', 'is_homepage_featured',
            'is_general_featured', 'is_category_featured', 'is_city_featured',
            'is_sold_out', 'is_cancelled', 'is_postponed', 'is_template', 'is_online',
            'door_sales_only', 'use_fixed_commission', 'enable_ticket_groups',
            'enable_ticket_perks', 'generate_fomo', 'has_custom_related',
            'is_indoor', 'is_outdoor', 'is_kid_friendly', 'is_accessible',
            'is_weather_sensitive',
        ];
    }

    /** Fields that hold the JSON translatable {en, ro, ...} structure. */
    protected function translatableFields(): array
    {
        return ['title', 'subtitle', 'short_description', 'description', 'ticket_terms', 'thank_you_message', 'name'];
    }

    /**
     * Get activity logs for this event PLUS its ticket types, merged into one
     * chronological, human-readable timeline.
     */
    public function getActivityLogs(): \Illuminate\Support\Collection
    {
        $eventClass = get_class($this->record);
        $ticketTypeIds = $this->record->ticketTypes()->pluck('id')->all();

        $activities = Activity::query()
            ->where(function ($q) use ($eventClass, $ticketTypeIds) {
                $q->where(function ($q2) use ($eventClass) {
                    $q2->where('subject_type', $eventClass)
                        ->where('subject_id', $this->record->id);
                });
                if (!empty($ticketTypeIds)) {
                    $q->orWhere(function ($q2) use ($ticketTypeIds) {
                        $q2->whereIn('subject_type', [TicketType::class, 'ticket_type'])
                            ->whereIn('subject_id', $ticketTypeIds);
                    });
                }
            })
            ->with('causer')
            ->orderByDesc('created_at')
            ->get();

        return $activities->map(function (Activity $activity) {
            $isTicketType = $this->isTicketTypeActivity($activity);
            $changes = $this->formatChanges($activity, $isTicketType);
            $subjectLabel = $isTicketType ? $this->ticketTypeName($activity) : null;

            return [
                'id' => $activity->id,
                'event' => $activity->event,
                'subject_kind' => $isTicketType ? 'ticket_type' : 'event',
                'subject_label' => $subjectLabel,
                'summary' => $this->buildSummary($activity, $isTicketType, $changes, $subjectLabel),
                'causer_name' => $this->getCauserName($activity),
                'causer_type' => $this->getCauserType($activity),
                'causer_type_label' => $this->getCauserTypeLabel($activity),
                'causer_email' => $this->getCauserEmail($activity),
                'changes' => $changes,
                'created_at' => $activity->created_at,
                'formatted_date' => $activity->created_at->timezone('Europe/Bucharest')->format('d M Y'),
                'formatted_time' => $activity->created_at->timezone('Europe/Bucharest')->format('H:i'),
                'relative_time' => $activity->created_at->timezone('Europe/Bucharest')->diffForHumans(),
            ];
        })
        // Drop entries that ended up with nothing meaningful to show
        // (e.g. an "updated" event where every changed column was noise).
        ->filter(fn ($log) => $log['event'] === 'created' || $log['event'] === 'deleted' || !empty($log['changes']))
        ->values();
    }

    protected function isTicketTypeActivity(Activity $activity): bool
    {
        return in_array($activity->subject_type, [TicketType::class, 'ticket_type'], true);
    }

    protected function ticketTypeName(Activity $activity): string
    {
        $props = $activity->properties;
        $attrs = $props->get('attributes', []);
        $name = $attrs['name'] ?? null;
        if (is_array($name)) {
            $name = $name['ro'] ?? $name['en'] ?? reset($name);
        }
        if (!$name && $activity->subject) {
            $subjName = $activity->subject->name ?? null;
            $name = is_array($subjName) ? ($subjName['ro'] ?? $subjName['en'] ?? reset($subjName)) : $subjName;
        }
        return $name ? (string) $name : 'bilet';
    }

    /**
     * Build the one-line human summary of what happened.
     */
    protected function buildSummary(Activity $activity, bool $isTicketType, array $changes, ?string $subjectLabel): string
    {
        $event = $activity->event;

        if ($isTicketType) {
            $label = $subjectLabel ? "«{$subjectLabel}»" : '';
            return match ($event) {
                'created' => trim("A adăugat tipul de bilet {$label}"),
                'deleted' => trim("A șters tipul de bilet {$label}"),
                default => $this->summariseTicketUpdate($label, $changes),
            };
        }

        return match ($event) {
            'created' => 'A creat evenimentul',
            'deleted' => 'A șters evenimentul',
            default => $this->summariseEventUpdate($changes),
        };
    }

    protected function summariseTicketUpdate(string $label, array $changes): string
    {
        if (empty($changes)) {
            return trim("A modificat tipul de bilet {$label}");
        }
        $parts = [];
        foreach ($changes as $c) {
            if ($c['new'] !== '(empty)' && $c['old'] !== '(empty)') {
                $parts[] = mb_strtolower($c['field']) . ' ' . $c['old'] . ' → ' . $c['new'];
            } elseif ($c['new'] !== '(empty)') {
                $parts[] = mb_strtolower($c['field']) . ' → ' . $c['new'];
            }
        }
        $suffix = $parts ? ': ' . implode(', ', $parts) : '';
        return trim("A modificat tipul de bilet {$label}") . $suffix;
    }

    protected function summariseEventUpdate(array $changes): string
    {
        if (empty($changes)) {
            return 'A actualizat evenimentul';
        }
        $labels = array_values(array_unique(array_map(fn ($c) => $c['field'], $changes)));
        // Keep the sentence short: list up to 4 fields, then "și încă N".
        $shown = array_slice($labels, 0, 4);
        $more = count($labels) - count($shown);
        $list = implode(', ', $shown);
        if ($more > 0) {
            $list .= " și încă {$more}";
        }
        return 'A modificat: ' . $list;
    }

    /**
     * Get the causer's display name — robust against morph-map aliases.
     * Any resolvable causer yields name / first+last / email; only a truly
     * missing causer is "Sistem".
     */
    protected function getCauserName(Activity $activity): string
    {
        $causer = $activity->causer;
        if (!$causer) {
            return 'Sistem';
        }

        $name = $causer->name ?? null;
        if (!$name) {
            $first = $causer->first_name ?? '';
            $last = $causer->last_name ?? '';
            $name = trim($first . ' ' . $last) ?: null;
        }

        return $name ?: ($causer->email ?? 'Utilizator');
    }

    /**
     * Machine key for colour/icon selection in the view. instanceof-based so
     * it works whether causer_type is a full class name or a morph alias.
     */
    protected function getCauserType(Activity $activity): string
    {
        $causer = $activity->causer;
        if (!$causer) {
            return 'system';
        }

        return match (true) {
            $causer instanceof \App\Models\User => 'admin',
            $causer instanceof \App\Models\MarketplaceAdmin => 'staff',
            $causer instanceof \App\Models\MarketplaceOrganizer => 'organizer',
            $causer instanceof \App\Models\MarketplaceCustomer => 'customer',
            default => 'user',
        };
    }

    /** Romanian label for the causer type badge. */
    protected function getCauserTypeLabel(Activity $activity): string
    {
        return match ($this->getCauserType($activity)) {
            'admin' => 'Administrator',
            'staff' => 'Staff marketplace',
            'organizer' => 'Organizator',
            'customer' => 'Client',
            'system' => 'Sistem',
            default => 'Utilizator',
        };
    }

    protected function getCauserEmail(Activity $activity): ?string
    {
        $causer = $activity->causer;
        if (!$causer) {
            return null;
        }

        return $causer->email ?? null;
    }

    /**
     * Format the changes for display, with human labels and clean values.
     */
    protected function formatChanges(Activity $activity, bool $isTicketType): array
    {
        $changes = [];
        $properties = $activity->properties;
        $hidden = $this->hiddenFields();
        $labels = $this->fieldLabels();

        // Updates: diff old vs new.
        if ($properties->has('old') && $properties->has('attributes')) {
            $old = $properties->get('old') ?? [];
            $new = $properties->get('attributes') ?? [];

            foreach ($new as $key => $newValue) {
                if (in_array($key, $hidden, true)) {
                    continue;
                }
                $oldValue = $old[$key] ?? null;

                $oldFmt = $this->formatValue($oldValue, $key);
                $newFmt = $this->formatValue($newValue, $key);

                // Skip if the human-visible values are identical (e.g. only the
                // untouched EN locale of a translatable field changed).
                if ($oldFmt === $newFmt) {
                    continue;
                }

                $changes[] = [
                    'field' => $labels[$key] ?? $this->formatFieldName($key),
                    'old' => $oldFmt,
                    'new' => $newFmt,
                ];
            }

            return $changes;
        }

        // Creates: show a curated subset only (avoid dumping every default flag).
        if ($activity->event === 'created' && $properties->has('attributes')) {
            $attributes = $properties->get('attributes') ?? [];

            $createdWhitelist = $isTicketType
                ? ['name', 'price_cents', 'quota_total', 'status']
                : ['title', 'event_date', 'range_start_date', 'suggested_venue_name', 'venue_id', 'address', 'marketplace_organizer_id', 'duration_mode'];

            foreach ($createdWhitelist as $key) {
                if (!array_key_exists($key, $attributes)) {
                    continue;
                }
                $value = $attributes[$key];
                if ($value === null || $value === '') {
                    continue;
                }
                $newFmt = $this->formatValue($value, $key);
                if ($newFmt === '(empty)') {
                    continue;
                }
                $changes[] = [
                    'field' => $labels[$key] ?? $this->formatFieldName($key),
                    'old' => '(empty)',
                    'new' => $newFmt,
                ];
            }
        }

        return $changes;
    }

    /**
     * Fallback prettifier for fields without an explicit label.
     */
    protected function formatFieldName(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Format a single value for display, aware of its field so translatable
     * JSON, prices, booleans and dates all read naturally.
     */
    protected function formatValue(mixed $value, string $field = ''): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }

        // Booleans (explicit set + native bools)
        if (in_array($field, $this->booleanFields(), true) || is_bool($value)) {
            $truthy = $value === true || $value === 1 || $value === '1' || $value === 'true';
            return $truthy ? 'Da' : 'Nu';
        }

        // Price stored in cents
        if ($field === 'price_cents' || $field === 'cheapest_price_cents' || $field === 'target_price') {
            $lei = is_numeric($value) ? ((float) $value) / 100 : null;
            if ($lei !== null) {
                return rtrim(rtrim(number_format($lei, 2, '.', ''), '0'), '.') . ' lei';
            }
        }

        // Translatable JSON {en, ro, ...} → Romanian text, HTML stripped
        if (in_array($field, $this->translatableFields(), true)) {
            return $this->limit($this->extractTranslatableText($value));
        }

        // SEO / other big arrays → don't dump JSON
        if ($field === 'seo') {
            return 'actualizat';
        }

        // Foreign keys we can name
        if ($field === 'marketplace_organizer_id') {
            return $this->resolveName('organizer', $value);
        }
        if ($field === 'venue_id') {
            return $this->resolveName('venue', $value);
        }

        // Commission mode / duration mode / status → friendly labels
        if ($field === 'commission_mode') {
            return match ((string) $value) {
                'included' => 'Inclus în preț',
                'added_on_top' => 'Adăugat la preț',
                default => (string) $value,
            };
        }
        if ($field === 'duration_mode') {
            return match ((string) $value) {
                'single_day' => 'O singură zi',
                'date_range' => 'Interval de date',
                'multi_day' => 'Multi-zi cu sloturi',
                default => (string) $value,
            };
        }
        if ($field === 'status') {
            return match ((string) $value) {
                'active' => 'Activ',
                'hidden' => 'Ascuns',
                'draft' => 'Ciornă',
                default => (string) $value,
            };
        }

        if (is_array($value)) {
            // Unknown array — extract locale text if present, else compact note
            $text = $this->extractTranslatableText($value);
            return $text !== '' ? $this->limit($text) : 'actualizat';
        }

        if ($value instanceof Carbon) {
            return $value->format('d M Y H:i');
        }

        // Date strings
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('d M Y H:i');
            } catch (\Exception $e) {
                // not a date, fall through
            }
        }

        return $this->limit((string) $value);
    }

    /**
     * Pull the Romanian (or first available) plain-text value out of a
     * translatable field, whether it arrives as an array or a JSON string.
     */
    protected function extractTranslatableText(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            $text = $value['ro'] ?? $value['en'] ?? (reset($value) ?: '');
            if (is_array($text)) {
                $text = reset($text) ?: '';
            }
        } else {
            $text = (string) $value;
        }

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }

    protected function limit(string $value, int $length = 120): string
    {
        $value = trim($value);
        if ($value === '') {
            return '(empty)';
        }
        return Str::limit($value, $length);
    }

    /**
     * Resolve organizer / venue id → name, memoised per request.
     */
    protected function resolveName(string $kind, mixed $id): string
    {
        if (!is_numeric($id)) {
            return (string) $id;
        }
        $id = (int) $id;
        $cacheKey = "{$kind}:{$id}";
        if (array_key_exists($cacheKey, $this->nameCache)) {
            return $this->nameCache[$cacheKey];
        }

        $name = match ($kind) {
            'organizer' => MarketplaceOrganizer::find($id)?->name,
            'venue' => Venue::find($id)?->name,
            default => null,
        };

        return $this->nameCache[$cacheKey] = ($name ? (string) $name : "#{$id}");
    }

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back_to_edit')
                ->label('Înapoi la editare')
                ->icon('heroicon-o-arrow-left')
                ->url(EventResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
