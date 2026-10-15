<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Site-ul unui tenant găzduit de noi (resources/tenant-sites): template-ul ales
 * și personalizarea lui. `draft` se editează în core, `published` e ce servește
 * site-ul public. Vezi config/tenant-sites.php.
 */
class TenantSite extends Model
{
    protected $fillable = [
        'tenant_id',
        'template',
        'draft',
        'published',
        'published_template',
        'published_at',
        'preview_token',
    ];

    protected $casts = [
        'draft' => 'array',
        'published' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantSite $site) {
            if (empty($site->preview_token)) {
                $site->preview_token = Str::random(40);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && ! empty($this->published);
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draft !== $this->published || $this->template !== $this->published_template;
    }

    public function publish(): void
    {
        $this->update([
            'published' => $this->draft,
            'published_template' => $this->template,
            'published_at' => now(),
        ]);
    }
}
