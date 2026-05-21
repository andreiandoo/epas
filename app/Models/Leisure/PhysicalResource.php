<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One physical unit of equipment owned by a leisure tenant — a boat, kayak,
 * bike, sled, locker, etc. Each unit carries a unique printable QR code.
 *
 * Linking with a TicketType happens via linked_ticket_type_ids (JSON array of
 * ints). Empty/null = any ticket type with service_category=rental can use it.
 */
class PhysicalResource extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_USE = 'in_use';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'tenant_id',
        'physical_resource_type_id',
        'resource_type',
        'name',
        'label',
        'qr_code',
        'status',
        'linked_ticket_type_ids',
        'meta',
    ];

    protected $casts = [
        'linked_ticket_type_ids' => 'array',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PhysicalResourceType::class, 'physical_resource_type_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(ResourceRental::class);
    }

    public function activeRental(): HasOne
    {
        return $this->hasOne(ResourceRental::class)->whereNull('ended_at');
    }

    /**
     * Generate a unique QR code with deterministic prefix. Caller is
     * responsible for verifying uniqueness via the DB constraint.
     */
    public static function generateQrCode(int $tenantId, string $resourceType): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($resourceType)) ?: 'RES');
        return sprintf('%s-%d-%s', $prefix, $tenantId, strtoupper(Str::random(6)));
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeForTenant($q, int $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeForResourceType($q, string $type)
    {
        return $q->where('resource_type', $type);
    }

    public function isAllowedForTicketType(int $ticketTypeId): bool
    {
        $linked = $this->linked_ticket_type_ids;
        if (empty($linked) || ! is_array($linked)) {
            return true; // no whitelist = any rental ticket type
        }
        return in_array($ticketTypeId, array_map('intval', $linked), true);
    }
}
