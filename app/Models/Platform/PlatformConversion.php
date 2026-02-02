<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;
use App\Models\Order;

class PlatformConversion extends Model
{
    protected $fillable = [
        'ad_account_id',
        'customer_id',
        'customer_event_id',
        'tenant_id',
        'conversion_id',
        'event_type',
        'conversion_time',
        'value',
        'currency',
        'order_id',
        'click_id',
        'user_data',
        'status',
        'error_message',
        'sent_at',
        'api_response',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'user_data' => 'array',
        'api_response' => 'array',
        'sent_at' => 'datetime',
        'conversion_time' => 'datetime',
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
        return $this->belongsTo(PlatformAdAccount::class, 'ad_account_id');
    }

    public function coreCustomer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'customer_id');
    }

    public function coreCustomerEvent(): BelongsTo
    {
        return $this->belongsTo(CoreCustomerEvent::class, 'customer_event_id');
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
        return $query->where('ad_account_id', $accountId);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopePurchases($query)
    {
        return $query->where('event_type', self::TYPE_PURCHASE);
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
            'api_response' => $response,
        ]);
    }

    public function markConfirmed(): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
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

        // Get user data from related customer if available
        if ($this->coreCustomer) {
            if ($this->coreCustomer->email_hash) {
                $data['em'] = $this->coreCustomer->email_hash;
            }
            if ($this->coreCustomer->phone_hash) {
                $data['ph'] = $this->coreCustomer->phone_hash;
            }
        }

        if ($this->click_id) {
            $data['click_id'] = $this->click_id;
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
        string $eventType = self::TYPE_PURCHASE
    ): ?self {
        $clickId = null;

        // Determine which click ID to use based on platform
        if ($account->isGoogle() && $event->gclid) {
            $clickId = $event->gclid;
        } elseif ($account->isFacebook() && $event->fbclid) {
            $clickId = $event->fbclid;
        } elseif ($account->isTiktok() && $event->ttclid) {
            $clickId = $event->ttclid;
        } elseif ($account->isLinkedin() && $event->li_fat_id) {
            $clickId = $event->li_fat_id;
        }

        // Generate unique conversion ID
        $conversionId = 'conv_' . uniqid() . '_' . $event->id;

        // Check for duplicate by conversion_id
        if (static::where('conversion_id', $conversionId)->exists()) {
            \Illuminate\Support\Facades\Log::info('Duplicate conversion detected, skipping', [
                'account_id' => $account->id,
                'conversion_id' => $conversionId,
                'event_type' => $eventType,
            ]);
            return null;
        }

        return static::create([
            'ad_account_id' => $account->id,
            'customer_id' => $customer->id,
            'customer_event_id' => $event->id,
            'tenant_id' => $event->tenant_id,
            'conversion_id' => $conversionId,
            'event_type' => $eventType,
            'conversion_time' => $event->occurred_at ?? $event->created_at,
            'value' => $event->event_value ?? 0,
            'currency' => $event->currency ?? 'EUR',
            'order_id' => $event->order_id,
            'click_id' => $clickId,
            'user_data' => [
                'email_hash' => $customer->email_hash,
                'phone_hash' => $customer->phone_hash,
            ],
            'status' => self::STATUS_PENDING,
        ]);
    }
}
