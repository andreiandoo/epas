<x-filament-panels::page>
    {{-- Header Widgets --}}
    @if (count($this->getHeaderWidgets()))
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    @endif

    {{-- Grid View --}}
    <div class="fi-ta">
        {{-- Filters --}}
        <div class="mb-4 flex flex-wrap gap-4 items-center">
            <div class="flex-1 min-w-[200px]">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="tableSearch"
                    placeholder="Search files..."
                    class="fi-input block w-full rounded-lg border-none bg-white/5 py-2 px-3 text-sm text-white ring-1 ring-white/10 transition duration-75 focus:ring-2 focus:ring-primary-500"
                >
            </div>
        </div>

        {{-- Media Grid --}}
        @php
            $records = $this->getTableRecords();
        @endphp

        @if($records->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                @foreach($records as $record)
                    <div
                        class="group relative bg-gray-900 rounded-lg overflow-hidden border border-white/10 hover:border-primary-500 transition-all duration-200 cursor-pointer"
                        wire:click="mountTableAction('view', '{{ $record->getKey() }}')"
                    >
                        {{-- Image/Preview --}}
                        <div class="aspect-square relative overflow-hidden bg-gray-800">
                            @if($record->is_image)
                                <img
                                    src="{{ $record->url }}"
                                    alt="{{ $record->filename }}"
                                    class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                                    loading="lazy"
                                >
                            @elseif($record->is_video)
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-900/50 to-purple-600/30">
                                    <svg class="w-12 h-12 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            @elseif(str_contains($record->mime_type ?? '', 'pdf'))
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-900/50 to-red-600/30">
                                    <svg class="w-12 h-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="absolute bottom-1/3 text-xs font-bold text-red-400">PDF</span>
                                </div>
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-700/50 to-gray-600/30">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                            @endif

                            {{-- Overlay with actions --}}
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center gap-2">
                                <a
                                    href="{{ $record->url }}"
                                    target="_blank"
                                    class="p-2 bg-white/20 rounded-full hover:bg-white/30 transition-colors"
                                    wire:click.stop
                                    title="Download"
                                >
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                </a>
                                <button
                                    wire:click.stop="mountTableAction('delete', '{{ $record->getKey() }}')"
                                    class="p-2 bg-red-500/50 rounded-full hover:bg-red-500/70 transition-colors"
                                    title="Delete"
                                >
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>

                            {{-- Compression badge --}}
                            @if(isset($record->metadata['compressed_at']))
                                <div class="absolute top-2 left-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Compressed
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="p-3">
                            <p class="text-sm text-white truncate font-medium" title="{{ $record->filename }}">
                                {{ $record->filename }}
                            </p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-400">
                                    {{ $record->human_readable_size }}
                                </span>
                                @if($record->width && $record->height)
                                    <span class="text-xs text-gray-500">
                                        {{ $record->width }}x{{ $record->height }}
                                    </span>
                                @endif
                            </div>
                            @if($record->collection)
                                <span class="inline-block mt-2 px-2 py-0.5 text-xs rounded-full bg-primary-500/20 text-primary-400">
                                    {{ $record->collection }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $records->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-300">No media files</h3>
                <p class="mt-1 text-sm text-gray-500">Upload files through the application or scan existing files.</p>
            </div>
        @endif
    </div>

    {{-- Footer Widgets --}}
    @if (count($this->getFooterWidgets()))
        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="$this->getFooterWidgetsColumns()"
        />
    @endif
</x-filament-panels::page>
