<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'template_data',
        'preview_image',
        'version',
        'parent_id',
        'is_default',
        'last_used_at',
    ];

    protected $casts = [
        'template_data' => 'array',
        'is_default' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this template
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the parent template (for versioning)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TicketTemplate::class, 'parent_id');
    }

    /**
     * Scope to active templates only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to default template
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get template metadata
     */
    public function getMeta(): array
    {
        return $this->template_data['meta'] ?? [];
    }

    /**
     * Get template layers
     */
    public function getLayers(): array
    {
        return $this->template_data['layers'] ?? [];
    }

    /**
     * Get template assets
     */
    public function getAssets(): array
    {
        return $this->template_data['assets'] ?? [];
    }

    /**
     * Get template size in mm
     */
    public function getSize(): array
    {
        $meta = $this->getMeta();
        return [
            'width' => $meta['size_mm']['w'] ?? 80,
            'height' => $meta['size_mm']['h'] ?? 200,
        ];
    }

    /**
     * Get template DPI
     */
    public function getDpi(): int
    {
        return $this->getMeta()['dpi'] ?? 300;
    }

    /**
     * Get template orientation
     */
    public function getOrientation(): string
    {
        return $this->getMeta()['orientation'] ?? 'portrait';
    }

    /**
     * Mark template as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Create a new version of this template
     */
    public function createVersion(array $templateData, string $name = null): self
    {
        return self::create([
            'tenant_id' => $this->tenant_id,
            'name' => $name ?? $this->name . ' (v' . ($this->version + 1) . ')',
            'description' => $this->description,
            'status' => 'draft',
            'template_data' => $templateData,
            'version' => $this->version + 1,
            'parent_id' => $this->id,
            'is_default' => false,
        ]);
    }

    /**
     * Set as default template
     */
    public function setAsDefault(): void
    {
        // Unset other defaults for this tenant
        self::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
