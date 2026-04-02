<?php

namespace App\Models\Cashless;

use App\Models\Customer;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'customer_id', 'cashless_account_id',
        'age', 'age_group', 'gender', 'city', 'country',
        'total_spent_cents', 'total_transactions', 'avg_transaction_cents',
        'max_transaction_cents', 'min_transaction_cents',
        'total_topped_up_cents', 'total_cashed_out_cents', 'net_spend_cents',
        'top_categories', 'top_products', 'top_vendors', 'product_type_distribution',
        'first_transaction_at', 'last_transaction_at', 'peak_hour',
        'active_hours', 'active_days', 'avg_time_between_purchases',
        'spending_score', 'frequency_score', 'diversity_score', 'overall_score',
        'segment', 'tags',
        'is_minor', 'has_age_restricted_attempts', 'flagged_for_review', 'flag_reason',
        'meta', 'calculated_at',
    ];

    protected $casts = [
        'age'                        => 'integer',
        'total_spent_cents'          => 'integer',
        'total_transactions'         => 'integer',
        'avg_transaction_cents'      => 'integer',
        'max_transaction_cents'      => 'integer',
        'min_transaction_cents'      => 'integer',
        'total_topped_up_cents'      => 'integer',
        'total_cashed_out_cents'     => 'integer',
        'net_spend_cents'            => 'integer',
        'top_categories'             => 'array',
        'top_products'               => 'array',
        'top_vendors'                => 'array',
        'product_type_distribution'  => 'array',
        'first_transaction_at'       => 'datetime',
        'last_transaction_at'        => 'datetime',
        'peak_hour'                  => 'integer',
        'active_hours'               => 'array',
        'active_days'                => 'array',
        'avg_time_between_purchases' => 'integer',
        'spending_score'             => 'integer',
        'frequency_score'            => 'integer',
        'diversity_score'            => 'integer',
        'overall_score'              => 'integer',
        'tags'                       => 'array',
        'is_minor'                   => 'boolean',
        'has_age_restricted_attempts' => 'boolean',
        'flagged_for_review'         => 'boolean',
        'meta'                       => 'array',
        'calculated_at'              => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function account(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'cashless_account_id'); }

    public function isWhale(): bool { return $this->segment === 'whale'; }
    public function isMinor(): bool { return $this->is_minor; }

    public function scopeSegment($query, string $segment) { return $query->where('segment', $segment); }
    public function scopeForEdition($query, int $id) { return $query->where('festival_edition_id', $id); }
    public function scopeHighScore($query, int $min = 80) { return $query->where('overall_score', '>=', $min); }
}
