<x-mail::message>
@if($data['is_achieved'])
# Goal Achieved!

Congratulations! You've reached your {{ strtolower($data['goal_type']) }} goal for **{{ $data['event_name'] }}**.
@else
# {{ $data['threshold'] }}% of Goal Reached

Great progress! You've reached {{ $data['threshold'] }}% of your {{ strtolower($data['goal_type']) }} goal for **{{ $data['event_name'] }}**.
@endif

---

## Goal Details

<x-mail::table>
| | |
|:--|--:|
| Goal Type | {{ $data['goal_type'] }} |
@if($data['goal_name'])
| Goal Name | {{ $data['goal_name'] }} |
@endif
| Target | {{ $data['target_value'] }} |
| Current | {{ $data['current_value'] }} |
| Progress | {{ number_format($data['current_progress'], 1) }}% |
@if($data['days_remaining'] !== null)
| Days Remaining | {{ $data['days_remaining'] }} |
@endif
</x-mail::table>

@if($data['is_achieved'])
<x-mail::panel>
Your goal has been marked as **achieved**. Consider setting a new, more ambitious goal to keep the momentum going!
</x-mail::panel>
@else
@if($data['days_remaining'] !== null && $data['days_remaining'] <= 7)
<x-mail::panel>
**Heads up!** You have only {{ $data['days_remaining'] }} days remaining to reach your target.
</x-mail::panel>
@endif
@endif

<x-mail::button :url="config('app.url') . '/marketplace/event-analytics?event=' . $goal->event_id">
View Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<x-mail::subcopy>
You received this email because you have goal alerts enabled.
To manage your alert settings, visit the event analytics dashboard.
</x-mail::subcopy>
</x-mail::message>
