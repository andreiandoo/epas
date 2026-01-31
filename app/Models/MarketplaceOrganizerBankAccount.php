<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrganizerBankAccount extends Model
{
    protected $fillable = [
        'marketplace_organizer_id',
        'bank_name',
        'iban',
        'account_holder',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    /**
     * Set this account as primary and unset others
     */
    public function setAsPrimary(): void
    {
        // Unset all other primary accounts for this organizer
        static::where('marketplace_organizer_id', $this->marketplace_organizer_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    /**
     * Get the bank code from IBAN (for Romanian IBANs)
     */
    public function getBankCodeAttribute(): ?string
    {
        if (strlen($this->iban) >= 8 && str_starts_with($this->iban, 'RO')) {
            return substr($this->iban, 4, 4);
        }
        return null;
    }

    /**
     * Get masked IBAN (shows first 4 and last 4 chars)
     */
    public function getMaskedIbanAttribute(): string
    {
        $iban = $this->iban;
        if (strlen($iban) <= 8) {
            return $iban;
        }
        return substr($iban, 0, 4) . str_repeat('*', strlen($iban) - 8) . substr($iban, -4);
    }
}
