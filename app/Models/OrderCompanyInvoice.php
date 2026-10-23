<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O factură pe persoană juridică cerută la o comandă: fie partea
 * marketplace-ului (emisă automat în Oblio), fie partea unui organizator
 * (încărcată de el). Vezi App\Services\Invoicing\CompanyInvoiceService.
 */
class OrderCompanyInvoice extends Model
{
    public const ISSUER_MARKETPLACE = 'marketplace';
    public const ISSUER_ORGANIZER = 'organizer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'În curs de emitere',
        self::STATUS_ISSUED => 'Emisă',
        self::STATUS_FAILED => 'Eroare la emitere',
        self::STATUS_REQUESTED => 'Așteaptă factura organizatorului',
        self::STATUS_UPLOADED => 'Încărcată de organizator',
        self::STATUS_NOT_APPLICABLE => 'Organizatorul nu emite factură (persoană fizică)',
    ];

    protected $fillable = [
        'marketplace_client_id',
        'order_id',
        'issuer',
        'marketplace_organizer_id',
        'status',
        'company',
        'lines',
        'subtotal',
        'vat_amount',
        'amount',
        'currency',
        'invoice_id',
        'file_path',
        'file_name',
        'uploaded_at',
        'uploaded_by',
        'sent_to_customer_at',
        'attempts',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'company' => 'array',
        'lines' => 'array',
        'meta' => 'array',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'uploaded_at' => 'datetime',
        'sent_to_customer_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function isMarketplace(): bool
    {
        return $this->issuer === self::ISSUER_MARKETPLACE;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Are de unde descărca un PDF (Oblio pentru marketplace, fișier încărcat pentru organizator). */
    public function hasDocument(): bool
    {
        if ($this->isMarketplace()) {
            return $this->status === self::STATUS_ISSUED && $this->invoice_id !== null;
        }

        return $this->file_path !== null;
    }
}
