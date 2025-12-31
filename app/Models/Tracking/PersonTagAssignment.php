<?php

namespace App\Models\Tracking;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonTagAssignment extends Model
{
    protected $fillable = [
        'tenant_id',
        'person_id',
        'tag_id',
        'source',
        'source_id',
        'confidence',
        'assigned_at',
        'expires_at',
        'assigned_by',
        'metadata',
    ];

    protected $casts = [
        'confidence' => 'float',
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public $timestamps = true;

    /**
     * Assignment sources.
     */
    public const SOURCES = [
        'manual' => 'Manual',
        'auto_rule' => 'Auto Rule',
        'import' => 'Import',
        'api' => 'API',
        'segment' => 'Segment',
        'event' => 'Event Trigger',
        'ml' => 'ML Model',
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

    public function tag(): BelongsTo
    {
        return $this->belongsTo(PersonTag::class, 'tag_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForTag($query, int $tagId)
    {
        return $query->where('tag_id', $tagId);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence', '>=', $threshold);
    }

    // Helpers

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Assign a tag to a person.
     */
    public static function assignTag(
        int $tenantId,
        int $personId,
        int $tagId,
        string $source = 'manual',
        ?int $sourceId = null,
        ?float $confidence = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $assignedBy = null,
        ?array $metadata = null
    ): self {
        $assignment = static::updateOrCreate(
            [
                'person_id' => $personId,
                'tag_id' => $tagId,
            ],
            [
                'tenant_id' => $tenantId,
                'source' => $source,
                'source_id' => $sourceId,
                'confidence' => $confidence,
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
                'assigned_by' => $assignedBy,
                'metadata' => $metadata,
            ]
        );

        // Log the assignment
        PersonTagLog::create([
            'tenant_id' => $tenantId,
            'person_id' => $personId,
            'tag_id' => $tagId,
            'action' => 'assigned',
            'source' => $source,
            'source_id' => $sourceId,
            'performed_by' => $assignedBy,
            'created_at' => now(),
        ]);

        return $assignment;
    }

    /**
     * Remove a tag from a person.
     */
    public static function removeTag(
        int $personId,
        int $tagId,
        string $source = 'manual',
        ?int $performedBy = null
    ): bool {
        $assignment = static::where('person_id', $personId)
            ->where('tag_id', $tagId)
            ->first();

        if (!$assignment) {
            return false;
        }

        // Log the removal
        PersonTagLog::create([
            'tenant_id' => $assignment->tenant_id,
            'person_id' => $personId,
            'tag_id' => $tagId,
            'action' => 'removed',
            'source' => $source,
            'performed_by' => $performedBy,
            'created_at' => now(),
        ]);

        return $assignment->delete();
    }

    /**
     * Get all tags for a person.
     */
    public static function getTagsForPerson(int $tenantId, int $personId): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->active()
            ->with('tag:id,name,slug,category,color,icon')
            ->get()
            ->map(fn($a) => [
                'id' => $a->tag_id,
                'name' => $a->tag->name,
                'slug' => $a->tag->slug,
                'category' => $a->tag->category,
                'color' => $a->tag->color,
                'icon' => $a->tag->icon,
                'source' => $a->source,
                'confidence' => $a->confidence,
                'assigned_at' => $a->assigned_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get persons with specific tags.
     */
    public static function getPersonsWithTags(int $tenantId, array $tagIds, bool $requireAll = false): array
    {
        $query = static::forTenant($tenantId)
            ->active()
            ->whereIn('tag_id', $tagIds);

        if ($requireAll) {
            $query->groupBy('person_id')
                ->havingRaw('COUNT(DISTINCT tag_id) = ?', [count($tagIds)]);
        }

        return $query->distinct()->pluck('person_id')->toArray();
    }
}
