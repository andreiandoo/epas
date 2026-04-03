<?php

namespace App\Models;

use App\Enums\CashlessMode;
use App\Enums\NfcChipType;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CashlessSettings;
use App\Models\Cashless\TopUpLocation;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FestivalEdition extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'slug',
        'year',
        'edition_number',
        'start_date',
        'end_date',
        'status',
        'currency',
        'cashless_mode',
        'nfc_chip_type',
        'settings',
        'meta',
    ];

    protected $casts = [
        'year'           => 'integer',
        'start_date'     => 'date',
        'end_date'       => 'date',
        'cashless_mode'  => CashlessMode::class,
        'nfc_chip_type'  => NfcChipType::class,
        'settings'       => 'array',
        'meta'           => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(VendorEdition::class);
    }

    public function vendorAccounts(): HasManyThrough
    {
        return $this->hasManyThrough(Vendor::class, VendorEdition::class, 'festival_edition_id', 'id', 'id', 'vendor_id');
    }

    public function posDevices(): HasMany
    {
        return $this->hasMany(VendorPosDevice::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(VendorProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(VendorProduct::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    public function wristbands(): HasMany
    {
        return $this->hasMany(Wristband::class);
    }

    public function wristbandTransactions(): HasMany
    {
        return $this->hasMany(WristbandTransaction::class);
    }

    public function festivalDays(): HasMany
    {
        return $this->hasMany(FestivalDay::class);
    }

    public function festivalPasses(): HasMany
    {
        return $this->hasMany(FestivalPass::class);
    }

    public function merchandiseItems(): HasMany
    {
        return $this->hasMany(MerchandiseItem::class);
    }

    public function merchandiseAllocations(): HasMany
    {
        return $this->hasMany(MerchandiseAllocation::class);
    }

    public function externalTickets(): HasMany
    {
        return $this->hasMany(FestivalExternalTicket::class);
    }

    public function cashlessAccounts(): HasMany
    {
        return $this->hasMany(CashlessAccount::class);
    }

    public function cashlessSales(): HasMany
    {
        return $this->hasMany(CashlessSale::class);
    }

    public function cashlessSettings(): HasOne
    {
        return $this->hasOne(CashlessSettings::class);
    }

    public function topupLocations(): HasMany
    {
        return $this->hasMany(TopUpLocation::class);
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isNfcMode(): bool
    {
        return $this->cashless_mode === CashlessMode::Nfc;
    }

    public function isQrMode(): bool
    {
        return $this->cashless_mode === CashlessMode::Qr;
    }

    public function isHybridMode(): bool
    {
        return $this->cashless_mode === CashlessMode::Hybrid;
    }

    public function supportsNfc(): bool
    {
        return $this->cashless_mode->supportsNfc();
    }

    public function supportsQr(): bool
    {
        return $this->cashless_mode->supportsQr();
    }

    public function totalRevenueCents(): int
    {
        return $this->saleItems()->sum('total_cents');
    }

    public function totalCashlessRevenueCents(): int
    {
        return $this->saleItems()->whereNotNull('cashless_sale_id')->sum('total_cents');
    }

    public function totalCommissionCents(): int
    {
        return $this->saleItems()->sum('commission_cents');
    }

    // ── Cashless billing helpers ──

    public function hasCashlessActivationInvoice(): bool
    {
        return Invoice::where('tenant_id', $this->tenant_id)
            ->whereJsonContains('meta->type', 'cashless_activation')
            ->whereJsonContains('meta->festival_edition_id', $this->id)
            ->exists();
    }

    public function hasCashlessCompletionInvoice(): bool
    {
        return Invoice::where('tenant_id', $this->tenant_id)
            ->whereJsonContains('meta->type', 'cashless_completion')
            ->whereJsonContains('meta->festival_edition_id', $this->id)
            ->exists();
    }

    public function cashlessInvoices()
    {
        return Invoice::where('tenant_id', $this->tenant_id)
            ->where(function ($q) {
                $q->whereJsonContains('meta->type', 'cashless_activation')
                    ->orWhereJsonContains('meta->type', 'cashless_completion');
            })
            ->whereJsonContains('meta->festival_edition_id', $this->id)
            ->get();
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ── NFC helpers ──

    public function isDesfireMode(): bool
    {
        return $this->nfc_chip_type === NfcChipType::DesfireEv3;
    }

    public function isNtagMode(): bool
    {
        return $this->nfc_chip_type === NfcChipType::Ntag213;
    }

    public function balanceOnChip(): bool
    {
        return $this->nfc_chip_type?->balanceOnChip() ?? false;
    }

    // ── Cashless microservice guard ──

    public function hasCashlessMicroservice(): bool
    {
        return $this->tenant?->hasMicroservice('cashless') ?? false;
    }
}
