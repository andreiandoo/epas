@php
    $record = $getRecord();
@endphp

@if($record->lat && $record->lng)
<div class="rounded-xl overflow-hidden border">
    <iframe
        width="100%"
        height="250"
        style="border:0"
        loading="lazy"
        allowfullscreen
        referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps?q={{ $record->lat }},{{ $record->lng }}&hl={{ app()->getLocale() }}&z=15&output=embed">
    </iframe>
</div>
@endif
