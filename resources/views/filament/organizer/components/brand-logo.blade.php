@php
    $organizer = auth('organizer')->user()?->organizer;
@endphp

<div class="flex items-center gap-3">
    @if($organizer?->logo)
        <img
            src="{{ Storage::url($organizer->logo) }}"
            alt="{{ $organizer->name }}"
            class="h-8 w-8 rounded-full object-cover"
        />
    @else
        <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white font-bold text-sm">
            {{ substr($organizer?->name ?? 'O', 0, 1) }}
        </div>
    @endif
    <span class="font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[150px]">
        {{ $organizer?->name ?? 'Organizer' }}
    </span>
</div>
