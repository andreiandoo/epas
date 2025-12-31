<?php

namespace App\Models\Tracking;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TxIdentityLink extends Model
{
    protected $table = 'tx_identity_links';

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'person_id',
        'confidence',
        'linked_at',
        'link_source',
        'order_id',
        'metadata',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
        'confidence' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Valid link sources.
     */
    public const LINK_SOURCES = [
        'order_completed',
        'login',
        'registration',
        'manual',
        'email_click',
        'social_login',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function scopeFromSource($query, string $source)
    {
        return $query->where('link_source', $source);
    }

    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence', '>=', $threshold);
    }

    // Static helpers

    /**
     * Create or update an identity link.
     */
    public static function linkIdentity(
        int $tenantId,
        string $visitorId,
        int $personId,
        string $source,
        ?int $orderId = null,
        float $confidence = 1.0,
        array $metadata = []
    ): self {
        return static::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'visitor_id' => $visitorId,
                'person_id' => $personId,
            ],
            [
                'confidence' => $confidence,
                'link_source' => $source,
                'order_id' => $orderId,
                'linked_at' => now(),
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Find person_id for a visitor.
     */
    public static function findPersonId(int $tenantId, string $visitorId): ?int
    {
        $link = static::forTenant($tenantId)
            ->forVisitor($visitorId)
            ->orderByDesc('confidence')
            ->orderByDesc('linked_at')
            ->first();

        return $link?->person_id;
    }

    /**
     * Find all visitors for a person.
     */
    public static function findVisitorIds(int $tenantId, int $personId): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->pluck('visitor_id')
            ->toArray();
    }

    /**
     * Perform identity stitching and backfill events.
     */
    public function performStitching(): int
    {
        return TxEvent::backfillPersonId(
            $this->tenant_id,
            $this->visitor_id,
            $this->person_id
        );
    }
}
