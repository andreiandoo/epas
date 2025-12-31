<?php

namespace App\Models\Tracking;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonTagLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'person_id',
        'tag_id',
        'action',
        'source',
        'source_id',
        'performed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Log actions.
     */
    public const ACTIONS = [
        'assigned' => 'Assigned',
        'removed' => 'Removed',
        'expired' => 'Expired',
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

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
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

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    // Static helpers

    /**
     * Get tag activity summary for a person.
     */
    public static function getPersonActivity(int $tenantId, int $personId, int $limit = 50): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->with(['tag:id,name,slug,color', 'performedByUser:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'action' => $log->action,
                'tag_name' => $log->tag->name ?? null,
                'tag_slug' => $log->tag->slug ?? null,
                'tag_color' => $log->tag->color ?? null,
                'source' => $log->source,
                'performed_by' => $log->performedByUser->name ?? null,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get tag statistics.
     */
    public static function getTagStats(int $tenantId, int $tagId, int $days = 30): array
    {
        $logs = static::forTenant($tenantId)
            ->forTag($tagId)
            ->recent($days)
            ->get();

        return [
            'total_assigned' => $logs->where('action', 'assigned')->count(),
            'total_removed' => $logs->where('action', 'removed')->count(),
            'total_expired' => $logs->where('action', 'expired')->count(),
            'by_source' => $logs->where('action', 'assigned')
                ->groupBy('source')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }
}
