<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessReportSnapshot extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'report_type',
        'period_start', 'period_end', 'dimensions', 'metrics',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'dimensions'   => 'array',
        'metrics'      => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }

    public function scopeOfType($query, string $type) { return $query->where('report_type', $type); }
    public function scopeForEdition($query, int $id) { return $query->where('festival_edition_id', $id); }
}
