<x-filament-panels::page>
    <div x-data="{ activeSection: null }" class="space-y-6">
        {{-- Header with language toggle --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php
                    $iconName = str_replace('heroicon-o-', '', $content['icon'] ?? 'heroicon-o-book-open');
                @endphp
                <div class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
                    <x-dynamic-component :component="'heroicon-o-' . $iconName" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->t($content['description'] ?? '') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                <button
                    wire:click="switchLocale('ro')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $locale === 'ro' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    RO
                </button>
                <button
                    wire:click="switchLocale('en')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $locale === 'en' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    EN
                </button>
            </div>
        </div>

        {{-- Layout: TOC sidebar + Content --}}
        <div class="flex gap-6">
            {{-- Table of Contents (sticky sidebar on desktop) --}}
            @if(!empty($content['sections']))
                <nav class="hidden lg:block w-56 flex-shrink-0">
                    <div class="sticky top-24 space-y-1">
                        <p class="px-3 pb-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                            {{ $locale === 'ro' ? 'Cuprins' : 'Contents' }}
                        </p>
                        @foreach($content['sections'] as $i => $section)
                            <a
                                href="#section-{{ $section['id'] ?? $i }}"
                                class="block px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-md transition"
                            >
                                {{ $this->t($section['title']) }}
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif

            {{-- Main Content --}}
            <div class="flex-1 min-w-0 space-y-6">
                {{-- Mobile TOC (accordion) --}}
                @if(!empty($content['sections']))
                    <div x-data="{ tocOpen: false }" class="lg:hidden bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <button
                            @click="tocOpen = !tocOpen"
                            class="w-full px-4 py-3 flex items-center justify-between text-sm font-medium text-gray-900 dark:text-white"
                        >
                            <span>{{ $locale === 'ro' ? 'Cuprins' : 'Table of Contents' }}</span>
                            <x-heroicon-m-chevron-down class="w-4 h-4 transition" x-bind:class="tocOpen && 'rotate-180'" />
                        </button>
                        <div x-show="tocOpen" x-collapse class="px-4 pb-3 space-y-1">
                            @foreach($content['sections'] as $i => $section)
                                <a
                                    href="#section-{{ $section['id'] ?? $i }}"
                                    @click="tocOpen = false"
                                    class="block py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-primary-600"
                                >
                                    {{ $this->t($section['title']) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Sections --}}
                @foreach($content['sections'] ?? [] as $i => $section)
                    <div
                        id="section-{{ $section['id'] ?? $i }}"
                        x-data="{ open: true }"
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden scroll-mt-24"
                    >
                        {{-- Section header --}}
                        <button
                            @click="open = !open"
                            class="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                        >
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white text-left">
                                {{ $this->t($section['title']) }}
                            </h3>
                            <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-400 transition flex-shrink-0 ml-2" x-bind:class="open && 'rotate-180'" />
                        </button>

                        {{-- Section content --}}
                        <div x-show="open" x-collapse>
                            <div class="px-5 pb-5 space-y-5">
                                {{-- Steps --}}
                                @if(!empty($section['steps']))
                                    <div class="space-y-3">
                                        @foreach($section['steps'] as $stepIndex => $step)
                                            <div class="flex gap-3">
                                                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-500/20 flex items-center justify-center mt-0.5">
                                                    <span class="text-xs font-bold text-primary-700 dark:text-primary-300">{{ $stepIndex + 1 }}</span>
                                                </div>
                                                <div class="flex-1 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                                    {!! $this->formatStepText($this->t($step['text'])) !!}
                                                    @if(!empty($step['note']))
                                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 italic">
                                                            {{ $this->t($step['note']) }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Fields --}}
                                @if(!empty($section['fields']))
                                    <div class="space-y-2">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            {{ $locale === 'ro' ? 'Campuri' : 'Fields' }}
                                        </p>
                                        <div class="space-y-2">
                                            @foreach($section['fields'] as $field)
                                                <div class="flex items-start gap-3 px-3 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                                {{ $this->t($field['name']) }}
                                                            </span>
                                                            @if(!empty($field['required']))
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300">
                                                                    {{ $locale === 'ro' ? 'Obligatoriu' : 'Required' }}
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                                                                    {{ $locale === 'ro' ? 'Optional' : 'Optional' }}
                                                                </span>
                                                            @endif
                                                            @if(!empty($field['tab']))
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                                                    {{ $this->t($field['tab']) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                                            {{ $this->t($field['description']) }}
                                                        </p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Tips --}}
                                @if(!empty($section['tips']))
                                    @foreach($section['tips'] as $tip)
                                        @php
                                            $tipColors = match($tip['type'] ?? 'info') {
                                                'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200',
                                                'success' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200',
                                                default => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200',
                                            };
                                            $tipIcon = match($tip['type'] ?? 'info') {
                                                'warning' => 'heroicon-m-exclamation-triangle',
                                                'success' => 'heroicon-m-check-circle',
                                                default => 'heroicon-m-information-circle',
                                            };
                                        @endphp
                                        <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg border {{ $tipColors }}">
                                            <x-dynamic-component :component="$tipIcon" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                                            <p class="text-xs leading-relaxed">
                                                {{ $this->t($tip['text']) }}
                                            </p>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Empty state --}}
                @if(empty($content['sections']))
                    <div class="text-center py-12">
                        <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $locale === 'ro' ? 'Continut in pregatire' : 'Content coming soon' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $locale === 'ro' ? 'Acest ghid va fi disponibil in curand.' : 'This guide will be available soon.' }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
