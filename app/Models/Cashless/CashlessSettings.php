<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessSettings extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',

        // Top-up limits
        'min_topup_cents',
        'max_topup_cents',
        'max_balance_cents',
        'daily_topup_limit_cents',

        // Cashout settings
        'allow_online_cashout',
        'allow_physical_cashout',
        'min_cashout_cents',
        'cashout_fee_cents',
        'cashout_fee_percentage',
        'auto_cashout_after_festival',
        'auto_cashout_delay_days',
        'auto_cashout_method',

        // Transfer settings
        'allow_account_transfer',
        'max_transfer_cents',
        'transfer_fee_cents',

        // POS settings
        'require_pin_above_cents',
        'max_charge_cents',
        'charge_cooldown_seconds',

        // Age verification
        'enforce_age_verification',
        'age_verification_method',

        // Currency & display
        'currency',
        'currency_symbol',
        'display_decimals',

        // Notifications
        'low_balance_threshold_cents',
        'send_receipt_on_purchase',
        'send_daily_summary',

        // Tipping
        'allow_tipping',
        'tip_presets',
        'max_tip_cents',

        'meta',
    ];

    protected $casts = [
        'min_topup_cents'            => 'integer',
        'max_topup_cents'            => 'integer',
        'max_balance_cents'          => 'integer',
        'daily_topup_limit_cents'    => 'integer',
        'allow_online_cashout'       => 'boolean',
        'allow_physical_cashout'     => 'boolean',
        'min_cashout_cents'          => 'integer',
        'cashout_fee_cents'          => 'integer',
        'cashout_fee_percentage'     => 'decimal:2',
        'auto_cashout_after_festival' => 'boolean',
        'auto_cashout_delay_days'    => 'integer',
        'allow_account_transfer'     => 'boolean',
        'max_transfer_cents'         => 'integer',
        'transfer_fee_cents'         => 'integer',
        'require_pin_above_cents'    => 'integer',
        'max_charge_cents'           => 'integer',
        'charge_cooldown_seconds'    => 'integer',
        'enforce_age_verification'   => 'boolean',
        'display_decimals'           => 'integer',
        'low_balance_threshold_cents' => 'integer',
        'send_receipt_on_purchase'   => 'boolean',
        'send_daily_summary'         => 'boolean',
        'allow_tipping'              => 'boolean',
        'tip_presets'                => 'array',
        'max_tip_cents'              => 'integer',
        'meta'                       => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    // ── Helpers ──

    public static function forEdition(int $editionId): ?self
    {
        return static::where('festival_edition_id', $editionId)->first();
    }

    public function isTopUpAllowed(int $amountCents): bool
    {
        return $amountCents >= $this->min_topup_cents
            && $amountCents <= $this->max_topup_cents;
    }

    public function isCashoutAllowed(int $amountCents): bool
    {
        return $amountCents >= $this->min_cashout_cents;
    }

    public function calculateCashoutFee(int $amountCents): int
    {
        $fixedFee = $this->cashout_fee_cents;
        $percentageFee = (int) round($amountCents * $this->cashout_fee_percentage / 100);

        return $fixedFee + $percentageFee;
    }

    public function requiresPin(int $amountCents): bool
    {
        return $this->require_pin_above_cents !== null
            && $amountCents > $this->require_pin_above_cents;
    }
}
