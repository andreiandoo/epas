@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Storage;

    $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
    $marketplace = $marketplaceAdmin?->marketplaceClient;
    $locale = app()->getLocale();

    $microservices = collect();
    if ($marketplace) {
        $microservices = $marketplace->microservices()
            ->wherePivot('is_active', true)
            ->orderByPivot('sort_order')
            ->get();
    }
@endphp

<div
    id="ep-secondary-sidebar"
    x-data
    x-show="$store.secondarySidebar.open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="-translate-x-4 opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="-translate-x-4 opacity-0"
    @keydown.escape.window="$store.secondarySidebar.close()"
    x-cloak
    class="ep-secondary-sidebar"
>
    {{-- Header --}}
    <div class="ep-secondary-sidebar-header">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
            </svg>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Microservices</span>
        </div>
        <button
            @click="$store.secondarySidebar.close()"
            class="ep-secondary-sidebar-close"
            title="Închide"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Scrollable nav --}}
    <nav class="ep-secondary-sidebar-nav">
        {{-- Section: Active microservices --}}
        @if($microservices->isNotEmpty())
            <div class="ep-secondary-sidebar-section">
                <div class="ep-secondary-sidebar-section-label">Active</div>
                <ul>
                    @foreach($microservices as $ms)
                        <li>
                            <a href="{{ url('/marketplace/microservices/' . $ms->slug . '/settings') }}"
                               class="ep-secondary-sidebar-item"
                               data-ep-secondary-link
                               data-microservice-slug="{{ $ms->slug }}">
                                @if($ms->icon_image)
                                    <img src="{{ Storage::disk('public')->url($ms->icon_image) }}"
                                         alt="" class="w-5 h-5 rounded object-cover flex-shrink-0">
                                @else
                                    <svg class="w-5 h-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
                                    </svg>
                                @endif
                                <span class="truncate">{{ $ms->getTranslation('name', $locale) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Link: All microservices --}}
        <div class="ep-secondary-sidebar-section">
            <ul>
                <li>
                    <a href="{{ url('/marketplace/microservices') }}"
                       class="ep-secondary-sidebar-item ep-secondary-sidebar-item-all"
                       data-ep-secondary-link>
                        <svg class="w-5 h-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        <span class="truncate">Toate microserviciile</span>
                    </a>
                </li>
            </ul>
        </div>

        {{-- Section: Other Services (cloned from DOM) --}}
        <div class="ep-secondary-sidebar-section" id="ep-secondary-sidebar-services-section" style="display: none;">
            <div class="ep-secondary-sidebar-section-label">Alte servicii</div>
            <ul id="ep-secondary-sidebar-services-clone"></ul>
        </div>
    </nav>
</div>
