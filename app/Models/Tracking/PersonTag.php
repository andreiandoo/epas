<?php

namespace App\Models\Tracking;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PersonTag extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'category',
        'color',
        'icon',
        'description',
        'is_system',
        'is_auto',
        'priority',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_auto' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Tag categories.
     */
    public const CATEGORIES = [
        'behavior' => 'Behavior',
        'demographic' => 'Demographic',
        'preference' => 'Preference',
        'lifecycle' => 'Lifecycle',
        'engagement' => 'Engagement',
        'custom' => 'Custom',
    ];

    /**
     * Default colors for tag categories.
     */
    public const CATEGORY_COLORS = [
        'behavior' => '#3B82F6',     // Blue
        'demographic' => '#8B5CF6',  // Purple
        'preference' => '#EC4899',   // Pink
        'lifecycle' => '#10B981',    // Green
        'engagement' => '#F59E0B',   // Amber
        'custom' => '#6B7280',       // Gray
    ];

    /**
     * System tag definitions.
     */
    public const SYSTEM_TAGS = [
        // Lifecycle
        ['name' => 'New Visitor', 'slug' => 'new-visitor', 'category' => 'lifecycle', 'icon' => 'heroicon-o-sparkles'],
        ['name' => 'Returning Visitor', 'slug' => 'returning-visitor', 'category' => 'lifecycle', 'icon' => 'heroicon-o-arrow-path'],
        ['name' => 'First-Time Buyer', 'slug' => 'first-time-buyer', 'category' => 'lifecycle', 'icon' => 'heroicon-o-shopping-bag'],
        ['name' => 'Repeat Buyer', 'slug' => 'repeat-buyer', 'category' => 'lifecycle', 'icon' => 'heroicon-o-arrow-path-rounded-square'],
        ['name' => 'VIP', 'slug' => 'vip', 'category' => 'lifecycle', 'icon' => 'heroicon-o-star'],
        ['name' => 'Churned', 'slug' => 'churned', 'category' => 'lifecycle', 'icon' => 'heroicon-o-arrow-right-on-rectangle'],
        ['name' => 'At Risk', 'slug' => 'at-risk', 'category' => 'lifecycle', 'icon' => 'heroicon-o-exclamation-triangle'],
        ['name' => 'Win-Back', 'slug' => 'win-back', 'category' => 'lifecycle', 'icon' => 'heroicon-o-arrow-uturn-left'],

        // Behavior
        ['name' => 'Cart Abandoner', 'slug' => 'cart-abandoner', 'category' => 'behavior', 'icon' => 'heroicon-o-shopping-cart'],
        ['name' => 'High Engagement', 'slug' => 'high-engagement', 'category' => 'behavior', 'icon' => 'heroicon-o-fire'],
        ['name' => 'Low Engagement', 'slug' => 'low-engagement', 'category' => 'behavior', 'icon' => 'heroicon-o-minus-circle'],
        ['name' => 'Email Opener', 'slug' => 'email-opener', 'category' => 'behavior', 'icon' => 'heroicon-o-envelope-open'],
        ['name' => 'Email Clicker', 'slug' => 'email-clicker', 'category' => 'behavior', 'icon' => 'heroicon-o-cursor-arrow-rays'],
        ['name' => 'Mobile User', 'slug' => 'mobile-user', 'category' => 'behavior', 'icon' => 'heroicon-o-device-phone-mobile'],
        ['name' => 'Desktop User', 'slug' => 'desktop-user', 'category' => 'behavior', 'icon' => 'heroicon-o-computer-desktop'],

        // Engagement
        ['name' => 'Newsletter Subscriber', 'slug' => 'newsletter-subscriber', 'category' => 'engagement', 'icon' => 'heroicon-o-newspaper'],
        ['name' => 'Social Follower', 'slug' => 'social-follower', 'category' => 'engagement', 'icon' => 'heroicon-o-share'],
        ['name' => 'Event Attendee', 'slug' => 'event-attendee', 'category' => 'engagement', 'icon' => 'heroicon-o-ticket'],
        ['name' => 'Frequent Visitor', 'slug' => 'frequent-visitor', 'category' => 'engagement', 'icon' => 'heroicon-o-clock'],

        // Preference
        ['name' => 'Price Sensitive', 'slug' => 'price-sensitive', 'category' => 'preference', 'icon' => 'heroicon-o-currency-dollar'],
        ['name' => 'Premium Buyer', 'slug' => 'premium-buyer', 'category' => 'preference', 'icon' => 'heroicon-o-sparkles'],
        ['name' => 'Early Bird', 'slug' => 'early-bird', 'category' => 'preference', 'icon' => 'heroicon-o-sun'],
        ['name' => 'Last Minute', 'slug' => 'last-minute', 'category' => 'preference', 'icon' => 'heroicon-o-bolt'],
    ];

    protected static function booted(): void
    {
        static::creating(function (PersonTag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
            if (empty($tag->color) && $tag->category) {
                $tag->color = self::CATEGORY_COLORS[$tag->category] ?? '#6B7280';
            }
        });
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function persons(): BelongsToMany
    {
        return $this->belongsToMany(CoreCustomer::class, 'person_tag_assignments', 'tag_id', 'person_id')
            ->withPivot(['source', 'source_id', 'confidence', 'assigned_at', 'expires_at', 'metadata'])
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PersonTagAssignment::class, 'tag_id');
    }

    public function rule(): HasOne
    {
        return $this->hasOne(PersonTagRule::class, 'tag_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PersonTagLog::class, 'tag_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeAutoTagging($query)
    {
        return $query->where('is_auto', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority')->orderBy('name');
    }

    // Helpers

    public function getPersonCount(): int
    {
        return $this->assignments()->count();
    }

    public static function findBySlug(int $tenantId, string $slug): ?self
    {
        return static::forTenant($tenantId)->where('slug', $slug)->first();
    }

    public static function createSystemTags(int $tenantId): void
    {
        foreach (self::SYSTEM_TAGS as $tagDef) {
            static::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $tagDef['slug']],
                array_merge($tagDef, [
                    'tenant_id' => $tenantId,
                    'is_system' => true,
                    'color' => self::CATEGORY_COLORS[$tagDef['category']] ?? '#6B7280',
                ])
            );
        }
    }
}
