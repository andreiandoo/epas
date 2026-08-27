<?php

namespace App\Models\Chat;

use App\Models\SupportDepartment;
use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator canned response / macro. Bodies may contain {variables}
 * (e.g. {name}, {event}) expanded by the operator UI at insert time.
 */
class ChatCannedResponse extends Model
{
    use SecureMarketplaceScoping;

    protected $fillable = [
        'marketplace_client_id',
        'support_department_id',
        'shortcut',
        'title',
        'body',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'support_department_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
