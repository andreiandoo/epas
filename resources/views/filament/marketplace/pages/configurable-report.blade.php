<x-filament-panels::page>
    @php
        $reports = $this->savedReports();
        $current = $this->currentReport();
    @endphp

    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-gray-500 dark:text-gray-400 mr-1">Rapoarte:</span>
        @forelse($reports as $r)
            <a href="{{ $this->reportUrl($r->id) }}"
               @class([
                   'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium ring-1 transition',
                   'bg-primary-600 text-white ring-primary-600' => $current?->id === $r->id,
                   'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-700' => $current?->id !== $r->id,
               ])
               @if($current?->id === $r->id) aria-current="page" @endif>
                {{ $r->name }}
            </a>
        @empty
            <span class="text-sm text-gray-500 dark:text-gray-400">Niciun raport. Creează unul cu „Raport nou”.</span>
        @endforelse
    </div>

    @if($current)
        <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">
            {{ count((array) $current->columns) }} coloane alese
            @if($current->created_by_name) · creat de {{ $current->created_by_name }} @endif
            · modificat {{ $current->updated_at?->format('d.m.Y H:i') }}
        </p>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
