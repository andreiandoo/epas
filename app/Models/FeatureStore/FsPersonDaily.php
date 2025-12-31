<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonDaily extends Model
{
    protected $table = 'fs_person_daily';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'date',
        'views_count',
        'carts_count',
        'checkouts_count',
        'purchases_count',
        'attendance_count',
        'gross_amount',
        'net_amount',
        'avg_order_value',
        'avg_decision_time_ms',
        'discount_usage_rate',
        'affiliate_rate',
        'currency',
    ];

    protected $casts = [
        'date' => 'date',
        'views_count' => 'integer',
        'carts_count' => 'integer',
        'checkouts_count' => 'integer',
        'purchases_count' => 'integer',
        'attendance_count' => 'integer',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'avg_order_value' => 'decimal:2',
        'avg_decision_time_ms' => 'integer',
        'discount_usage_rate' => 'decimal:4',
        'affiliate_rate' => 'decimal:4',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeWithPurchases($query)
    {
        return $query->where('purchases_count', '>', 0);
    }

    public function scopeWithAttendance($query)
    {
        return $query->where('attendance_count', '>', 0);
    }

    // Calculated metrics

    public function getConversionRateAttribute(): float
    {
        if ($this->views_count === 0) {
            return 0;
        }
        return $this->purchases_count / $this->views_count;
    }

    public function getCartAbandonmentRateAttribute(): float
    {
        if ($this->carts_count === 0) {
            return 0;
        }
        return 1 - ($this->purchases_count / $this->carts_count);
    }

    public function getCheckoutCompletionRateAttribute(): float
    {
        if ($this->checkouts_count === 0) {
            return 0;
        }
        return $this->purchases_count / $this->checkouts_count;
    }

    public function getAttendanceRateAttribute(): float
    {
        if ($this->purchases_count === 0) {
            return 0;
        }
        return $this->attendance_count / $this->purchases_count;
    }

    // Static aggregations

    public static function aggregateForPerson(int $tenantId, int $personId, int $days = 90): array
    {
        $data = static::forTenant($tenantId)
            ->forPerson($personId)
            ->forDateRange(now()->subDays($days), now())
            ->get();

        return [
            'total_views' => $data->sum('views_count'),
            'total_carts' => $data->sum('carts_count'),
            'total_checkouts' => $data->sum('checkouts_count'),
            'total_purchases' => $data->sum('purchases_count'),
            'total_attendance' => $data->sum('attendance_count'),
            'total_gross' => $data->sum('gross_amount'),
            'total_net' => $data->sum('net_amount'),
            'avg_order_value' => $data->avg('avg_order_value'),
            'avg_decision_time_ms' => $data->avg('avg_decision_time_ms'),
            'active_days' => $data->count(),
        ];
    }
}
