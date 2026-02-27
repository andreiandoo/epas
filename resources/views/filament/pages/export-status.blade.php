<x-filament-panels::page>
    <div @if($this->hasInProgressExports()) wire:poll.2s @endif>

        @php $exports = $this->getExports(); @endphp

        @if($exports->isEmpty())
            <x-filament::section>
                <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <p class="text-lg font-medium">No exports yet</p>
                    <p class="mt-1 text-sm">Start an export from the Artists or Venues list page.</p>
                </div>
            </x-filament::section>
        @else
            <div class="space-y-3">
                @foreach($exports as $export)
                    @php
                        $isComplete = $export->completed_at !== null;
                        $progress = $export->total_rows > 0
                            ? round(($export->processed_rows / $export->total_rows) * 100)
                            : 0;
                        $exporterLabel = str($export->exporter)->classBasename()->beforeLast('Exporter')->toString();
                        $failed = $export->getFailedRowsCount();
                    @endphp

                    <x-filament::section>
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex-1 min-w-0">
                                {{-- Header with type and status --}}
                                <div class="flex flex-wrap items-center gap-3 mb-2">
                                    <span class="text-lg font-semibold">{{ $exporterLabel }} Export</span>

                                    @if($isComplete)
                                        @if($failed === 0)
                                            <x-filament::badge color="success">Completed</x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">Completed with errors</x-filament::badge>
                                        @endif
                                    @else
                                        <x-filament::badge color="info">
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-3 h-3 animate-spin" viewBox="0 0 24 24" fill="none">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                </svg>
                                                In Progress
                                            </span>
                                        </x-filament::badge>
                                    @endif
                                </div>

                                {{-- Progress bar (only for in-progress) --}}
                                @if(!$isComplete)
                                    <div class="w-full h-3 mb-3 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-700">
                                        <div class="h-full transition-all duration-500 rounded-full bg-primary-500"
                                             style="width: {{ $progress }}%"></div>
                                    </div>
                                @endif

                                {{-- Stats row --}}
                                <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($export->processed_rows) }}</span>
                                        / {{ number_format($export->total_rows) }} rows
                                        <span class="text-xs">({{ $progress }}%)</span>
                                    </span>
                                    <span>
                                        <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($export->successful_rows) }}</span> successful
                                    </span>
                                    @if($failed > 0)
                                        <span class="text-red-500">
                                            <span class="font-medium">{{ number_format($failed) }}</span> failed
                                        </span>
                                    @endif
                                    <span>Started: {{ $export->created_at->format('d M Y, H:i:s') }}</span>
                                    @if($isComplete)
                                        <span>Completed: {{ \Carbon\Carbon::parse($export->completed_at)->format('d M Y, H:i:s') }}</span>
                                        <span>Duration: {{ $export->created_at->diffForHumans(\Carbon\Carbon::parse($export->completed_at), true) }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 shrink-0">
                                @if($isComplete && $export->successful_rows > 0)
                                    <x-filament::button
                                        wire:click="downloadExport({{ $export->id }})"
                                        icon="heroicon-o-arrow-down-tray"
                                        size="sm"
                                        color="success"
                                    >
                                        Download CSV
                                    </x-filament::button>
                                @endif

                                <x-filament::button
                                    wire:click="deleteExport({{ $export->id }})"
                                    wire:confirm="Are you sure you want to delete this export?"
                                    icon="heroicon-o-trash"
                                    size="sm"
                                    color="danger"
                                    outlined
                                >
                                    Delete
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
