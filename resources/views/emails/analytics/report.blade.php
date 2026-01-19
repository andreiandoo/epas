<x-mail::message>
# {{ $data['schedule']['frequency'] ?? 'Analytics' }} Report

**{{ $data['event']['name'] }}**

@if(isset($data['event']['date']))
Event Date: {{ $data['event']['date'] }}
@if($data['event']['days_until'] > 0)
({{ $data['event']['days_until'] }} days remaining)
@endif
@endif

---

## Period: {{ $data['schedule']['period_start'] }} - {{ $data['schedule']['period_end'] }}

@if(isset($data['overview']))
## Overview

<x-mail::table>
| Metric | Value | Change |
|:-------|------:|-------:|
| Revenue | {{ number_format($data['overview']['revenue']['total'] ?? 0, 2) }} {{ $event->currency ?? 'EUR' }} | {{ ($data['comparison']['changes']['revenue'] ?? 0) >= 0 ? '+' : '' }}{{ $data['comparison']['changes']['revenue'] ?? 0 }}% |
| Tickets Sold | {{ number_format($data['overview']['tickets']['sold'] ?? 0) }} | - |
| Unique Visitors | {{ number_format($data['overview']['visits']['unique'] ?? 0) }} | {{ ($data['comparison']['changes']['visitors'] ?? 0) >= 0 ? '+' : '' }}{{ $data['comparison']['changes']['visitors'] ?? 0 }}% |
| Conversion Rate | {{ $data['overview']['conversion']['rate'] ?? 0 }}% | - |
</x-mail::table>
@endif

@if(isset($data['goals']) && count($data['goals']) > 0)
## Goals Progress

@foreach($data['goals'] as $goal)
**{{ $goal['type_label'] }}{{ $goal['name'] ? ': ' . $goal['name'] : '' }}**
- Progress: {{ number_format($goal['progress'], 1) }}%
- Current: {{ $goal['current'] }} / Target: {{ $goal['target'] }}
- Status: {{ ucfirst($goal['status']) }}

@endforeach
@endif

@if(isset($data['traffic_sources']) && count($data['traffic_sources']) > 0)
## Top Traffic Sources

<x-mail::table>
| Source | Visitors | Share |
|:-------|------:|------:|
@foreach($data['traffic_sources'] as $source)
| {{ $source['name'] }} | {{ number_format($source['visitors']) }} | {{ $source['percent'] }}% |
@endforeach
</x-mail::table>
@endif

@if(isset($data['milestones_summary']))
## Campaigns Summary

- Active Campaigns: {{ $data['milestones_summary']['active'] }} / {{ $data['milestones_summary']['total'] }}
- Total Budget: {{ number_format($data['milestones_summary']['total_budget'], 2) }} {{ $event->currency ?? 'EUR' }}
- Attributed Revenue: {{ number_format($data['milestones_summary']['total_attributed'], 2) }} {{ $event->currency ?? 'EUR' }}
@endif

@if(isset($data['top_locations']) && count($data['top_locations']) > 0)
## Top Locations

@foreach(array_slice($data['top_locations'], 0, 5) as $location)
- {{ $location['city'] }}, {{ $location['country'] }}: {{ $location['tickets'] }} tickets
@endforeach
@endif

---

<x-mail::button :url="config('app.url') . '/marketplace/event-analytics?event=' . $event->id">
View Full Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<x-mail::subcopy>
You received this email because you have scheduled reports enabled for this event.
To manage your report settings, visit the event analytics dashboard.
</x-mail::subcopy>
</x-mail::message>
