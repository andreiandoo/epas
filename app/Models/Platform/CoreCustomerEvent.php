<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;
use App\Models\Order;

class CoreCustomerEvent extends Model
{
    protected $fillable = [
        'core_customer_id',
        'tenant_id',
        'session_id',
        'event_type',
        'event_category',
        'event_data',
        'page_url',
        'page_title',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'ttclid',
        'li_fat_id',
        'order_id',
        'order_total',
        'currency',
        'ticket_count',
        'event_id',
        'device_type',
        'browser',
        'os',
        'ip_address',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'time_on_page',
        'scroll_depth',
        'is_converted',
        'conversion_value',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'order_total' => 'decimal:2',
        'conversion_value' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_converted' => 'boolean',
        'time_on_page' => 'integer',
        'scroll_depth' => 'integer',
        'ticket_count' => 'integer',
    ];

    public $timestamps = false;

    // Event types
    const TYPE_PAGE_VIEW = 'page_view';
    const TYPE_SESSION_START = 'session_start';
    const TYPE_SCROLL = 'scroll';
    const TYPE_CLICK = 'click';
    const TYPE_FORM_START = 'form_start';
    const TYPE_FORM_SUBMIT = 'form_submit';
    const TYPE_ADD_TO_CART = 'add_to_cart';
    const TYPE_BEGIN_CHECKOUT = 'begin_checkout';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REFUND = 'refund';
    const TYPE_SIGN_UP = 'sign_up';
    const TYPE_LOGIN = 'login';
    const TYPE_SEARCH = 'search';
    const TYPE_VIEW_ITEM = 'view_item';
    const TYPE_VIEW_ITEM_LIST = 'view_item_list';
    const TYPE_SELECT_ITEM = 'select_item';
    const TYPE_SHARE = 'share';
    const TYPE_VIDEO_START = 'video_start';
    const TYPE_VIDEO_PROGRESS = 'video_progress';
    const TYPE_VIDEO_COMPLETE = 'video_complete';
    const TYPE_FILE_DOWNLOAD = 'file_download';
    const TYPE_OUTBOUND_CLICK = 'outbound_click';
    const TYPE_ENGAGEMENT = 'engagement';
    const TYPE_CUSTOM = 'custom';

    // Event categories
    const CATEGORY_NAVIGATION = 'navigation';
    const CATEGORY_ENGAGEMENT = 'engagement';
    const CATEGORY_ECOMMERCE = 'ecommerce';
    const CATEGORY_CONVERSION = 'conversion';
    const CATEGORY_USER = 'user';
    const CATEGORY_MEDIA = 'media';
    const CATEGORY_CUSTOM = 'custom';

    public function coreCustomer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoreSession::class, 'session_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    public function scopeConversions($query)
    {
        return $query->where('is_converted', true);
    }

    public function scopePurchases($query)
    {
        return $query->where('event_type', self::TYPE_PURCHASE);
    }

    public function scopePageViews($query)
    {
        return $query->where('event_type', self::TYPE_PAGE_VIEW);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeWithClickId($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('gclid')
              ->orWhereNotNull('fbclid')
              ->orWhereNotNull('ttclid')
              ->orWhereNotNull('li_fat_id');
        });
    }

    public function scopeFromUtmSource($query, string $source)
    {
        return $query->where('utm_source', $source);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeLastHours($query, int $hours)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeLastDays($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helpers
    public function hasClickId(): bool
    {
        return $this->gclid || $this->fbclid || $this->ttclid || $this->li_fat_id;
    }

    public function getClickIdPlatform(): ?string
    {
        if ($this->gclid) return 'google';
        if ($this->fbclid) return 'facebook';
        if ($this->ttclid) return 'tiktok';
        if ($this->li_fat_id) return 'linkedin';
        return null;
    }

    public function isPurchase(): bool
    {
        return $this->event_type === self::TYPE_PURCHASE;
    }

    public function isEngagement(): bool
    {
        return $this->event_category === self::CATEGORY_ENGAGEMENT;
    }

    public function isEcommerce(): bool
    {
        return $this->event_category === self::CATEGORY_ECOMMERCE;
    }

    // Get data for ad platform conversion
    public function getConversionData(): array
    {
        return [
            'event_type' => $this->event_type,
            'event_time' => $this->created_at->timestamp,
            'event_id' => $this->id,
            'order_id' => $this->order_id,
            'value' => $this->conversion_value ?? $this->order_total,
            'currency' => $this->currency ?? 'USD',
            'page_url' => $this->page_url,
            'gclid' => $this->gclid,
            'fbclid' => $this->fbclid,
            'ttclid' => $this->ttclid,
            'li_fat_id' => $this->li_fat_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->event_data['user_agent'] ?? null,
        ];
    }

    // Attribution helper
    public function getAttributionSource(): string
    {
        if ($this->gclid) return 'Google Ads';
        if ($this->fbclid) return 'Facebook Ads';
        if ($this->ttclid) return 'TikTok Ads';
        if ($this->li_fat_id) return 'LinkedIn Ads';
        if ($this->utm_source) return ucfirst($this->utm_source);
        if ($this->referrer) {
            $host = parse_url($this->referrer, PHP_URL_HOST);
            return $host ?: 'Direct';
        }
        return 'Direct';
    }
}
