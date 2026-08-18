<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Info banner --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <span><span class="font-semibold text-gray-900 dark:text-white">{{ count($backups) }}</span> backup-uri</span>
                <span>Total: <span class="font-semibold text-gray-900 dark:text-white">{{ $this->getTotalSize() }}</span></span>
                <span class="text-gray-400 dark:text-gray-500">·</span>
                <span>Backup automat zilnic la <strong>03:00</strong>, păstrate <strong>7 zile</strong>.</span>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Backup-uri disponibile</x-slot>
            <x-slot name="description">Cele mai noi primele. Fișierele se descarcă direct (streaming), nu prin browser-ul de admin.</x-slot>

            @if(count($backups) === 0)
                <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Nu există încă niciun backup în <code>storage/backups/</code>.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Fișier</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Tip</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Dimensiune</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Creat</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Acțiuni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($backups as $backup)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $backup['name'] }}</td>
                                    <td class="px-3 py-2">
                                        @if($backup['is_auto'])
                                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">Automat</span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-400">Manual</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-700 dark:text-gray-300">{{ $backup['size'] }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $backup['modified'] }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ $backup['download_url'] }}"
                                               class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-500">
                                                <x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5 shrink-0" />
                                                Descarcă
                                            </a>
                                            <button type="button"
                                                    wire:click="deleteBackup('{{ $backup['name'] }}')"
                                                    wire:confirm="Ștergi definitiv backup-ul {{ $backup['name'] }}?"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                                                <x-heroicon-m-trash class="h-3.5 w-3.5 shrink-0" />
                                                Șterge
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
