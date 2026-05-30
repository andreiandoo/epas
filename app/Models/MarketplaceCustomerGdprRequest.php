<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GDPR data subject request — data export or deletion.
 *
 * Created from /cont/setari (Privacy tab). The worker
 * ExportMarketplaceCustomerDataJob fills export_file_path + sets
 * status='completed' when the ZIP is ready, then sends the customer
 * a one-time signed-download email valid until expires_at.
 */
class MarketplaceCustomerGdprRequest extends Model
{
    public const TYPE_EXPORT       = 'export';
    public const TYPE_DELETION     = 'deletion';
    public const TYPE_RECTIFICATION = 'rectification';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_customer_id',
        'request_type',
        'status',
        'export_file_path',
        'export_token',
        'file_size_bytes',
        'error_message',
        'requested_at',
        'processed_at',
        'expires_at',
        'downloaded_at',
    ];

    protected $casts = [
        'requested_at'  => 'datetime',
        'processed_at'  => 'datetime',
        'expires_at'    => 'datetime',
        'downloaded_at' => 'datetime',
        'file_size_bytes' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->export_file_path
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
