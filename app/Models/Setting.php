<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name',
        'cui',
        'reg_com',
        'vat_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'default_currency',
        'bank_name',
        'bank_account',
        'bank_swift',
        'invoice_prefix',
        'invoice_next_number',
        'invoice_series',
        'default_payment_terms_days',
        'logo_path',
        'invoice_footer',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'invoice_next_number' => 'integer',
        'default_payment_terms_days' => 'integer',
    ];

    /**
     * Get the single settings record (singleton pattern)
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * Generate next invoice number and increment
     */
    public function getNextInvoiceNumber(): string
    {
        $number = $this->invoice_next_number;
        $prefix = $this->invoice_prefix ?? 'INV';
        $series = $this->invoice_series ? "{$this->invoice_series}-" : '';

        // Increment for next time
        $this->increment('invoice_next_number');

        return "{$prefix}-{$series}" . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
