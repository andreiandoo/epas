<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;
use App\Models\Order;

class PlatformConversion extends Model
{
    protected $fillable = [
        'platform_ad_account_id',
        'core_customer_id',
        'core_customer_event_id',
        'tenant_id',
        'order_id',
        'conversion_type',
        'conversion_action_id',
        'value',
        'currency',
        'click_id',
        'click_id_type',
        'hashed_email',
        'hashed_phone',
        'user_data',
        'event_data',
        'status',
        'platform_response',
        'error_message',
        'retry_count',
        'sent_at',
        'confirmed_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'user_data' => 'array',
        'event_data' => 'array',
        'platform_response' => 'array',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    // Conversion types
    const TYPE_PURCHASE = 'purchase';
    const TYPE_ADD_TO_CART = 'add_to_cart';
    const TYPE_BEGIN_CHECKOUT = 'begin_checkout';
    const TYPE_LEAD = 'lead';
    const TYPE_SIGN_UP = 'sign_up';
    const TYPE_VIEW_CONTENT = 'view_content';
    const TYPE_PAGE_VIEW = 'page_view';
    const TYPE_CUSTOM = 'custom';

    // Click ID types
    const CLICK_GCLID = 'gclid';
    const CLICK_FBCLID = 'fbclid';
    const CLICK_TTCLID = 'ttclid';
    const CLICK_LI_FAT_ID = 'li_fat_id';

    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    public function platformAdAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAdAccount::class);
    }

    public function coreCustomer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class);
    }

    public function coreCustomerEvent(): BelongsTo
    {
        return $this->belongsTo(CoreCustomerEvent::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('platform_ad_account_id', $accountId);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('conversion_type', $type);
    }

    public function scopePurchases($query)
    {
        return $query->where('conversion_type', self::TYPE_PURCHASE);
    }

    public function scopeWithClickId($query)
    {
        return $query->whereNotNull('click_id');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Status management
    public function markSent(array $response = []): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'platform_response' => $response,
        ]);
    }

    public function markConfirmed(): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function markSkipped(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'error_message' => $reason,
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }

    public function canRetry(int $maxRetries = 5): bool
    {
        return $this->retry_count < $maxRetries && $this->status === self::STATUS_FAILED;
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function hasClickId(): bool
    {
        return !empty($this->click_id);
    }

    public function getPlatformName(): string
    {
        return $this->platformAdAccount?->getPlatformLabel() ?? 'Unknown';
    }

    // Build user data for platform APIs
    public function buildUserData(): array
    {
        $data = [];

        if ($this->hashed_email) {
            $data['em'] = $this->hashed_email;
        }

        if ($this->hashed_phone) {
            $data['ph'] = $this->hashed_phone;
        }

        if ($this->click_id) {
            switch ($this->click_id_type) {
                case self::CLICK_GCLID:
                    $data['gclid'] = $this->click_id;
                    break;
                case self::CLICK_FBCLID:
                    $data['fbc'] = $this->click_id;
                    break;
                case self::CLICK_TTCLID:
                    $data['ttclid'] = $this->click_id;
                    break;
                case self::CLICK_LI_FAT_ID:
                    $data['li_fat_id'] = $this->click_id;
                    break;
            }
        }

        // Merge any additional user data
        if ($this->user_data) {
            $data = array_merge($data, $this->user_data);
        }

        return $data;
    }

    // Static factory for creating conversions
    public static function createFromEvent(
        PlatformAdAccount $account,
        CoreCustomerEvent $event,
        CoreCustomer $customer,
        string $conversionType = self::TYPE_PURCHASE
    ): self {
        $clickId = null;
        $clickIdType = null;

        // Determine which click ID to use based on platform
        if ($account->isGoogle() && $event->gclid) {
            $clickId = $event->gclid;
            $clickIdType = self::CLICK_GCLID;
        } elseif ($account->isFacebook() && $event->fbclid) {
            $clickId = $event->fbclid;
            $clickIdType = self::CLICK_FBCLID;
        } elseif ($account->isTiktok() && $event->ttclid) {
            $clickId = $event->ttclid;
            $clickIdType = self::CLICK_TTCLID;
        } elseif ($account->isLinkedin() && $event->li_fat_id) {
            $clickId = $event->li_fat_id;
            $clickIdType = self::CLICK_LI_FAT_ID;
        }

        return static::create([
            'platform_ad_account_id' => $account->id,
            'core_customer_id' => $customer->id,
            'core_customer_event_id' => $event->id,
            'tenant_id' => $event->tenant_id,
            'order_id' => $event->order_id,
            'conversion_type' => $conversionType,
            'value' => $event->conversion_value ?? $event->order_total ?? 0,
            'currency' => $event->currency ?? 'USD',
            'click_id' => $clickId,
            'click_id_type' => $clickIdType,
            'hashed_email' => $customer->email_hash,
            'hashed_phone' => $customer->phone_hash,
            'status' => self::STATUS_PENDING,
            'event_data' => [
                'event_type' => $event->event_type,
                'page_url' => $event->page_url,
                'ip_address' => $event->ip_address,
            ],
        ]);
    }
}
