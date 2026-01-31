<?php

namespace App\Models\Seating;

use App\Models\MarketplaceClient;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingLayout extends Model
{
    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'is_partner',
        'partner_notes',
        'venue_id',
        'name',
        'status',
        'canvas_w',
        'canvas_h',
        'background_image_path',
        'background_image_url',
        'background_scale',
        'background_x',
        'background_y',
        'background_opacity',
        'version',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'canvas_w' => 'integer',
        'canvas_h' => 'integer',
        'background_scale' => 'float',
        'background_x' => 'integer',
        'background_y' => 'integer',
        'background_opacity' => 'float',
        'version' => 'integer',
        'is_partner' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'draft',
        'canvas_w' => 1920,
        'canvas_h' => 1080,
        'version' => 1,
    ];

    protected $appends = ['canvas_width', 'canvas_height'];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        // Auto-set tenant_id and created_by on create
        static::creating(function ($layout) {
            // Only set tenant_id if not provided AND user has a tenant_id AND no marketplace_client_id
            // (marketplace layouts don't need tenant_id)
            if (!$layout->tenant_id && !$layout->marketplace_client_id && auth()->check() && isset(auth()->user()->tenant_id)) {
                $layout->tenant_id = auth()->user()->tenant_id;
            }
            // Only set created_by if the authenticated user is a User model (not MarketplaceAdmin)
            if (!$layout->created_by && auth()->check() && auth()->user() instanceof User) {
                $layout->created_by = auth()->id();
            }
        });

        // Auto-set updated_by on update
        static::updating(function ($layout) {
            // Only set updated_by if the authenticated user is a User model (not MarketplaceAdmin)
            if (auth()->check() && auth()->user() instanceof User) {
                $layout->updated_by = auth()->id();
            }
        });
    }

    /**
     * Relationships
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SeatingSection::class, 'layout_id');
    }

    public function eventSnapshots(): HasMany
    {
        return $this->hasMany(EventSeatingLayout::class, 'layout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Check if layout is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Publish the layout
     */
    public function publish(): bool
    {
        $this->status = 'published';
        return $this->save();
    }

    /**
     * Accessor and Mutator for canvas_width (maps to canvas_w)
     */
    public function getCanvasWidthAttribute(): int
    {
        return $this->canvas_w;
    }

    public function setCanvasWidthAttribute($value): void
    {
        $this->attributes['canvas_w'] = $value;
    }

    /**
     * Accessor and Mutator for canvas_height (maps to canvas_h)
     */
    public function getCanvasHeightAttribute(): int
    {
        return $this->canvas_h;
    }

    public function setCanvasHeightAttribute($value): void
    {
        $this->attributes['canvas_h'] = $value;
    }

    /**
     * Clone the layout with all sections/rows/seats
     */
    public function cloneLayout(string $newName): self
    {
        $clone = $this->replicate(['version']);
        $clone->name = $newName;
        $clone->status = 'draft';
        $clone->version = 1;
        $clone->save();

        // Clone sections, rows, and seats
        foreach ($this->sections as $section) {
            $sectionClone = $section->replicate();
            $sectionClone->layout_id = $clone->id;
            $sectionClone->save();

            foreach ($section->rows as $row) {
                $rowClone = $row->replicate();
                $rowClone->section_id = $sectionClone->id;
                $rowClone->save();

                foreach ($row->seats as $seat) {
                    $seatClone = $seat->replicate(['seat_uid']);
                    $seatClone->row_id = $rowClone->id;
                    $seatClone->seat_uid = $sectionClone->name . '_' . $rowClone->label . '_' . $seat->label . '_' . uniqid();
                    $seatClone->save();
                }
            }
        }

        return $clone;
    }
}
