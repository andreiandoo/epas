<?php

/*
| Bara de atenție a operatorilor (microserviciul operator-cockpit).
| Pragurile pot fi suprascrise per marketplace din configurația microserviciului
| (pivot marketplace_client_microservices.config → thresholds).
*/

return [

    'microservice_slug' => 'operator-cockpit',

    // Cât de des cere browserul starea barei (ms).
    'poll_interval_ms' => (int) env('OPERATOR_COCKPIT_POLL_MS', 15000),

    // Câte elemente maxim pe categorie trimitem în sertar.
    'max_items_per_category' => 40,

    // Praguri după care pătratul pulsează (minute).
    'thresholds' => [
        'chat_queue_minutes' => 2,
        'ticket_unassigned_minutes' => 240,
        'ticket_mine_idle_minutes' => 1440,
        'todo_idle_minutes' => 2880,
        'artist_pending_minutes' => 2880,
        'event_waiting_minutes' => 1440,
        'event_starts_within_days' => 7,
    ],

    'snooze_minutes' => 60,
];
