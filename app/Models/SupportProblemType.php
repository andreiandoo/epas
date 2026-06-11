<?php

namespace App\Models;

use App\Support\Translatable;
use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SupportProblemType extends Model
{
    use SecureMarketplaceScoping;
    use Translatable;
    use LogsActivity;

    /**
     * Vocabulary for required_fields. Keep in sync with the marketplace
     * form renderer and SupportTicketController validation.
     */
    public const KNOWN_FIELDS = [
        'url',
        'invoice_series',
        'invoice_number',
        'event_id',
        'module_name',
    ];

    protected $fillable = [
        'marketplace_client_id',
        'support_department_id',
        'slug',
        'name',
        'description',
        'required_fields',
        'allowed_opener_types',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'required_fields' => 'array',
        'allowed_opener_types' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'required_fields', 'allowed_opener_types', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('support');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'support_department_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function isAvailableFor(string $openerType): bool
    {
        $allowed = $this->allowed_opener_types ?: ['organizer', 'customer'];
        return in_array($openerType, $allowed, true);
    }
}
