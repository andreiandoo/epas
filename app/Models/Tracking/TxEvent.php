<?php

namespace App\Models\Tracking;

use App\Models\Event;
use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreSession;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TxEvent extends Model
{
    protected $table = 'tx_events';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'event_name',
        'event_version',
        'occurred_at',
        'received_at',
        'tenant_id',
        'site_id',
        'source_system',
        'visitor_id',
        'session_id',
        'sequence_no',
        'person_id',
        'consent_snapshot',
        'context',
        'entities',
        'payload',
        'idempotency_key',
        'prev_event_id',
    ];

    protected $casts = [
        'event_id' => 'string',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'event_version' => 'integer',
        'sequence_no' => 'integer',
        'consent_snapshot' => 'array',
        'context' => 'array',
        'entities' => 'array',
        'payload' => 'array',
    ];

    /**
     * Valid source systems for events.
     */
    public const SOURCE_SYSTEMS = [
        'web',
        'mobile',
        'scanner',
        'backend',
        'payments',
        'shop',
        'wallet',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (TxEvent $event) {
            if (empty($event->event_id)) {
                $event->event_id = (string) Str::uuid();
            }
            if (empty($event->received_at)) {
                $event->received_at = now();
            }
            if (empty($event->event_version)) {
                $event->event_version = 1;
            }
        });
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoreSession::class, 'session_id', 'session_id');
    }

    // Entity accessors

    public function getEventEntityIdAttribute(): ?int
    {
        return $this->entities['event_entity_id'] ?? null;
    }

    public function getOrderIdAttribute(): ?int
    {
        return $this->entities['order_id'] ?? null;
    }

    public function getCartIdAttribute(): ?string
    {
        return $this->entities['cart_id'] ?? null;
    }

    public function getTicketIdAttribute(): ?int
    {
        return $this->entities['ticket_id'] ?? null;
    }

    public function eventEntity(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_entity_id')
            ->withDefault();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id')
            ->withDefault();
    }

    // Context accessors

    public function getPageUrlAttribute(): ?string
    {
        return $this->context['page']['url'] ?? null;
    }

    public function getPageTypeAttribute(): ?string
    {
        return $this->context['page']['page_type'] ?? null;
    }

    public function getUtmSourceAttribute(): ?string
    {
        return $this->context['utm']['source'] ?? null;
    }

    public function getUtmMediumAttribute(): ?string
    {
        return $this->context['utm']['medium'] ?? null;
    }

    public function getUtmCampaignAttribute(): ?string
    {
        return $this->context['utm']['campaign'] ?? null;
    }

    public function getDeviceTypeAttribute(): ?string
    {
        return $this->context['device']['device_type'] ?? null;
    }

    // Consent accessors

    public function hasAnalyticsConsent(): bool
    {
        return $this->consent_snapshot['analytics'] ?? false;
    }

    public function hasMarketingConsent(): bool
    {
        return $this->consent_snapshot['marketing'] ?? false;
    }

    public function hasDataProcessingConsent(): bool
    {
        return $this->consent_snapshot['data_processing'] ?? false;
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForVisitor($query, string $visitorId)
    {
        return $query->where('visitor_id', $visitorId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeOfType($query, string $eventName)
    {
        return $query->where('event_name', $eventName);
    }

    public function scopeOfTypes($query, array $eventNames)
    {
        return $query->whereIn('event_name', $eventNames);
    }

    public function scopeFromSource($query, string $sourceSystem)
    {
        return $query->where('source_system', $sourceSystem);
    }

    public function scopeOccurredBetween($query, $start, $end)
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }

    public function scopeForEventEntity($query, int $eventEntityId)
    {
        return $query->whereRaw("entities->>'event_entity_id' = ?", [(string) $eventEntityId]);
    }

    public function scopeForOrder($query, int $orderId)
    {
        return $query->whereRaw("entities->>'order_id' = ?", [(string) $orderId]);
    }

    public function scopeUnstitched($query)
    {
        return $query->whereNull('person_id');
    }

    public function scopeStitched($query)
    {
        return $query->whereNotNull('person_id');
    }

    public function scopeWithConsent($query, string $scope = 'analytics')
    {
        return $query->whereRaw("(consent_snapshot->>?) = 'true'", [$scope]);
    }

    // Static helpers

    public static function findByIdempotencyKey(string $key): ?self
    {
        return static::where('idempotency_key', $key)->first();
    }

    public static function createFromEnvelope(array $envelope): self
    {
        return static::create([
            'event_id' => $envelope['event_id'] ?? Str::uuid(),
            'event_name' => $envelope['event_name'],
            'event_version' => $envelope['event_version'] ?? 1,
            'occurred_at' => $envelope['occurred_at'],
            'tenant_id' => $envelope['tenant_id'],
            'site_id' => $envelope['site_id'] ?? null,
            'source_system' => $envelope['source_system'] ?? 'web',
            'visitor_id' => $envelope['visitor_id'] ?? null,
            'session_id' => $envelope['session_id'] ?? null,
            'sequence_no' => $envelope['sequence_no'] ?? null,
            'person_id' => $envelope['person_id'] ?? null,
            'consent_snapshot' => $envelope['consent_snapshot'] ?? [],
            'context' => $envelope['context'] ?? [],
            'entities' => $envelope['entities'] ?? [],
            'payload' => $envelope['payload'] ?? [],
            'idempotency_key' => $envelope['idempotency_key'] ?? null,
            'prev_event_id' => $envelope['prev_event_id'] ?? null,
        ]);
    }

    /**
     * Backfill person_id for all events from this visitor.
     */
    public static function backfillPersonId(int $tenantId, string $visitorId, int $personId): int
    {
        return static::where('tenant_id', $tenantId)
            ->where('visitor_id', $visitorId)
            ->whereNull('person_id')
            ->update(['person_id' => $personId]);
    }
}
