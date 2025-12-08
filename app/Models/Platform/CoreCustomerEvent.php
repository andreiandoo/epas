<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;
use App\Models\Order;

class CoreCustomerEvent extends Model
{
    protected $fillable = [
        'customer_id',
        'tenant_id',
        'session_id',
        'visitor_id',
        'event_type',
        'event_category',
        'event_action',
        'event_label',
        'event_value',
        'page_url',
        'page_path',
        'page_title',
        'page_type',
        'content_id',
        'content_type',
        'content_name',
        'event_id',
        'order_id',
        'ticket_id',
        'product_sku',
        'product_price',
        'quantity',
        'currency',
        'cart_id',
        'cart_value',
        'source',
        'medium',
        'campaign',
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
        'fbc',
        'fbp',
        'ttp',
        'device_type',
        'device_brand',
        'device_model',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'screen_width',
        'screen_height',
        'ip_address',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'occurred_at',
        'time_on_page_seconds',
        'scroll_depth_percent',
        'sent_to_platform',
        'sent_to_tenant',
        'processing_log',
    ];

    protected $casts = [
        'event_value' => 'decimal:2',
        'product_price' => 'decimal:2',
        'cart_value' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'quantity' => 'integer',
        'screen_width' => 'integer',
        'screen_height' => 'integer',
        'time_on_page_seconds' => 'integer',
        'scroll_depth_percent' => 'integer',
        'sent_to_platform' => 'boolean',
        'sent_to_tenant' => 'boolean',
        'processing_log' => 'array',
        'occurred_at' => 'datetime',
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
        return $this->belongsTo(CoreCustomer::class, 'customer_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoreSession::class, 'session_id', 'session_id');
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
        return $query->whereIn('event_type', [self::TYPE_PURCHASE, self::TYPE_SIGN_UP, self::TYPE_LEAD]);
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
            'event_time' => $this->occurred_at?->timestamp ?? $this->created_at?->timestamp,
            'event_id' => $this->id,
            'order_id' => $this->order_id,
            'value' => $this->event_value ?? $this->cart_value ?? 0,
            'currency' => $this->currency ?? 'EUR',
            'page_url' => $this->page_url,
            'gclid' => $this->gclid,
            'fbclid' => $this->fbclid,
            'ttclid' => $this->ttclid,
            'li_fat_id' => $this->li_fat_id,
            'ip_address' => $this->ip_address,
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
