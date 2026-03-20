@php $c = $this->record; @endphp

{{-- Purchase Behavior --}}
<x-filament::section>
    <x-slot name="heading">Purchase Behavior</x-slot>
    <div class="grid gap-x-6 gap-y-0 divide-y divide-gray-100 dark:divide-gray-800 md:grid-cols-3 md:divide-y-0">
        @foreach([
            'Total Orders' => number_format($c->total_orders ?? 0),
            'Total Tickets' => number_format($c->total_tickets ?? 0),
            'Total Spent' => number_format((float)($c->total_spent ?? 0), 2) . ' ' . ($c->currency ?? 'EUR'),
            'Avg Order Value' => number_format((float)($c->average_order_value ?? 0), 2) . ' ' . ($c->currency ?? 'EUR'),
            'LTV' => number_format((float)($c->lifetime_value ?? 0), 2) . ' ' . ($c->currency ?? 'EUR'),
            'Predicted LTV' => $c->predicted_ltv ? number_format((float)$c->predicted_ltv, 2) . ' ' . ($c->currency ?? 'EUR') : '—',
            'Purchase Frequency' => $c->purchase_frequency_days ? 'Every ' . $c->purchase_frequency_days . ' days' : '—',
            'Days Since Last' => $c->days_since_last_purchase ?? '—',
            'Cart Abandoned' => $c->has_cart_abandoned ? 'Yes' : 'No',
        ] as $label => $value)
            <div class="flex justify-between px-1 py-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
            </div>
        @endforeach
    </div>
</x-filament::section>

{{-- Engagement Metrics --}}
<div class="mt-4">
    <x-filament::section>
        <x-slot name="heading">Engagement Metrics</x-slot>
        <div class="grid gap-x-6 gap-y-0 divide-y divide-gray-100 dark:divide-gray-800 md:grid-cols-3 md:divide-y-0">
            @php
                $ts = $c->total_time_spent_seconds ?? 0;
                $timeLabel = $ts < 60 ? $ts . 's' : ($ts < 3600 ? round($ts/60) . 'min' : round($ts/3600,1) . 'h');
                $as = $c->avg_session_duration_seconds ?? 0;
                $avgLabel = $as < 60 ? $as . 's' : round($as/60,1) . 'min';
            @endphp
            @foreach([
                'Total Visits' => number_format($c->total_visits ?? 0),
                'Total Pageviews' => number_format($c->total_pageviews ?? 0),
                'Total Sessions' => number_format($c->total_sessions ?? 0),
                'Time Spent' => $timeLabel,
                'Avg Session' => $avgLabel,
                'Bounce Rate' => $c->bounce_rate !== null ? number_format((float) $c->bounce_rate, 1) . '%' : '—',
                'Events Viewed' => $c->total_events_viewed ?? 0,
                'Events Attended' => $c->total_events_attended ?? 0,
            ] as $label => $value)
                <div class="flex justify-between px-1 py-2 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                    <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</div>
