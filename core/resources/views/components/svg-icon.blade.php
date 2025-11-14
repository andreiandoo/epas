@props(['name'])

@php
    // Normalize icon name (remove 'icon-' prefix if present)
    $iconName = str_replace('icon-', '', $name);

    // Build component name (Laravel auto-detects components/icons/ directory)
    $iconComponent = "icons.{$iconName}";
@endphp

{{-- Check if view exists before rendering --}}
@if(view()->exists("components.icons.{$iconName}"))
    <x-dynamic-component :component="$iconComponent" {{ $attributes }} />
@else
    {{-- Fallback: display icon name if icon doesn't exist --}}
    <span class="inline-block text-xs text-red-500" title="Icon not found: {{ $iconName }}">
        [{{ $iconName }}]
    </span>
@endif
