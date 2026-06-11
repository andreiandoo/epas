<?php

namespace App\Models;

use App\Support\Translatable;
use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SupportDepartment extends Model
{
    use SecureMarketplaceScoping;
    use Translatable;
    use LogsActivity;

    protected $fillable = [
        'marketplace_client_id',
        'slug',
        'name',
        'description',
        'notify_emails',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'notify_emails' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'is_active', 'notify_emails'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('support');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function problemTypes(): HasMany
    {
        return $this->hasMany(SupportProblemType::class)->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Marketplace admins that handle tickets on this department.
     * Used to scope the assignee dropdown and to pick default notify
     * recipients on ticket creation.
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceAdmin::class,
            'support_department_admins',
            'support_department_id',
            'marketplace_admin_id'
        )->withTimestamps();
    }
}
