<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebTemplateCustomization extends Model
{
    protected $fillable = [
        'web_template_id',
        'unique_token',
        'label',
        'tenant_id',
        'customization_data',
        'demo_data_overrides',
        'preview_password',
        'self_service_token',
        'self_service_fields',
        'status',
        'expires_at',
        'viewed_count',
        'last_viewed_at',
        'utm_data',
    ];

    protected $casts = [
        'customization_data' => 'array',
        'demo_data_overrides' => 'array',
        'self_service_fields' => 'array',
        'utm_data' => 'array',
        'expires_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $customization) {
            if (empty($customization->unique_token)) {
                $customization->unique_token = Str::random(12);
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WebTemplate::class, 'web_template_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(WebTemplateFeedback::class, 'web_template_customization_id');
    }

    public function getMergedData(): array
    {
        $demoData = $this->template->default_demo_data ?? [];
        $overrides = $this->demo_data_overrides ?? [];
        $customizations = $this->customization_data ?? [];

        return array_replace_recursive($demoData, $overrides, $customizations);
    }

    public function getPreviewUrl(string $domain = 'tixello.ro'): string
    {
        return $this->template->getPreviewUrl($this, $domain);
    }

    public function hasPassword(): bool
    {
        return !empty($this->preview_password);
    }

    public function checkPassword(string $password): bool
    {
        return $this->preview_password === $password;
    }

    public function generateSelfServiceToken(): string
    {
        $this->self_service_token = Str::random(32);
        $this->save();
        return $this->self_service_token;
    }

    public function getSelfServiceUrl(): ?string
    {
        if (!$this->self_service_token) {
            return null;
        }
        return route('web-template.self-service', ['token' => $this->self_service_token]);
    }

    /**
     * Get the fields the client is allowed to edit in self-service mode.
     * Falls back to all customizable_fields from the template if not restricted.
     */
    public function getAllowedSelfServiceFields(): array
    {
        if (!empty($this->self_service_fields)) {
            return $this->self_service_fields;
        }
        return $this->template->customizable_fields ?? [];
    }

    public function getAverageRating(): ?float
    {
        $avg = $this->feedbacks()->avg('rating');
        return $avg ? round($avg, 1) : null;
    }

    public function recordView(?array $utmParams = null): void
    {
        $this->increment('viewed_count');
        $this->update(['last_viewed_at' => now()]);

        // Store UTM data if provided
        if ($utmParams && array_filter($utmParams)) {
            $utmLog = $this->utm_data ?? [];
            $utmLog[] = array_merge($utmParams, [
                'recorded_at' => now()->toIso8601String(),
                'ip_hash' => substr(md5(request()->ip()), 0, 8),
            ]);
            // Keep last 500 entries
            if (count($utmLog) > 500) {
                $utmLog = array_slice($utmLog, -500);
            }
            $this->update(['utm_data' => $utmLog]);
        }

        // Check for milestone notifications
        app(\App\Services\WebTemplate\ProspectViewNotifier::class)
            ->checkAndNotify($this->fresh());
    }
}
