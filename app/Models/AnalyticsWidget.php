<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id', 'type', 'title', 'data_source',
        'config', 'position', 'refresh_interval',
    ];

    protected $casts = [
        'config' => 'array',
        'position' => 'array',
    ];

    const TYPE_CHART = 'chart';
    const TYPE_METRIC = 'metric';
    const TYPE_TABLE = 'table';
    const TYPE_MAP = 'map';

    public function dashboard(): BelongsTo { return $this->belongsTo(AnalyticsDashboard::class, 'dashboard_id'); }
}
