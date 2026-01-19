<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceStatusLog extends Model
{
    protected $fillable = [
        'service_name',
        'service_type',
        'is_online',
        'response_time_ms',
        'version',
        'error_message',
        'metadata',
        'checked_at',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'metadata' => 'array',
        'checked_at' => 'datetime',
    ];

    public function scopeForService($query, string $serviceName)
    {
        return $query->where('service_name', $serviceName);
    }

    public function scopeLast30Days($query)
    {
        return $query->where('checked_at', '>=', now()->subDays(30));
    }

    public function scopeLatestPerService($query)
    {
        return $query->whereIn('id', function ($subquery) {
            $subquery->selectRaw('MAX(id)')
                ->from('service_status_logs')
                ->groupBy('service_name');
        });
    }

    /**
     * Get uptime percentage for a service over the last 30 days
     */
    public static function getUptimePercentage(string $serviceName): float
    {
        $logs = self::forService($serviceName)
            ->last30Days()
            ->get();

        if ($logs->isEmpty()) {
            return 100.0;
        }

        $onlineCount = $logs->where('is_online', true)->count();
        return round(($onlineCount / $logs->count()) * 100, 2);
    }

    /**
     * Get daily status summary for charts
     */
    public static function getDailyStatusForChart(string $serviceName, int $days = 30): array
    {
        $logs = self::forService($serviceName)
            ->where('checked_at', '>=', now()->subDays($days))
            ->orderBy('checked_at')
            ->get()
            ->groupBy(fn ($log) => $log->checked_at->format('Y-m-d'));

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayLogs = $logs->get($date, collect());

            if ($dayLogs->isEmpty()) {
                $result[] = [
                    'date' => $date,
                    'uptime' => 100,
                    'avg_response_time' => 0,
                    'checks' => 0,
                ];
            } else {
                $onlineCount = $dayLogs->where('is_online', true)->count();
                $result[] = [
                    'date' => $date,
                    'uptime' => round(($onlineCount / $dayLogs->count()) * 100, 1),
                    'avg_response_time' => round($dayLogs->avg('response_time_ms') ?? 0),
                    'checks' => $dayLogs->count(),
                ];
            }
        }

        return $result;
    }
}
