<x-filament-panels::page>
    @php
        $bar = function (float $pct) {
            $color = $pct >= 90 ? 'bg-red-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-primary-600');
            return [$color, max(0, min(100, $pct))];
        };
    @endphp

    <div class="space-y-6">
        @unless($shellAvailable)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <code>shell_exec</code> pare dezactivat — tabelele df / procese și analiza de directoare nu vor funcționa. Metricile din <code>/proc</code> (RAM, load) rămân disponibile.
            </div>
        @endunless

        {{-- Top summary cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Disk --}}
            @php([$dc, $dp] = $bar($disk['percent'] ?? 0))
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Disc (aplicație)</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $disk['used_h'] ?? '—' }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">din {{ $disk['total_h'] ?? '—' }} · liber {{ $disk['free_h'] ?? '—' }}</div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-2 rounded-full {{ $dc }}" style="width: {{ $dp }}%"></div>
                </div>
                <div class="mt-1 text-right text-xs font-medium text-gray-600 dark:text-gray-300">{{ $disk['percent'] ?? 0 }}%</div>
            </div>

            {{-- RAM --}}
            @php([$mc, $mp] = $bar($memory['percent'] ?? 0))
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">RAM</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $memory['used_h'] ?? '—' }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">din {{ $memory['total_h'] ?? '—' }} · liber {{ $memory['available_h'] ?? '—' }}</div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-2 rounded-full {{ $mc }}" style="width: {{ $mp }}%"></div>
                </div>
                <div class="mt-1 text-right text-xs font-medium text-gray-600 dark:text-gray-300">{{ $memory['percent'] ?? 0 }}%</div>
            </div>

            {{-- Load --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Load (1 / 5 / 15 min)</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $load['1'] ?? '—' }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $load['5'] ?? '—' }} · {{ $load['15'] ?? '—' }} · {{ $cpu['cores'] ?? '?' }} nuclee
                    @if(!is_null($load['per_core'] ?? null))
                        · ~{{ $load['per_core'] }}%/core
                    @endif
                </div>
            </div>

            {{-- Swap + uptime --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Swap / Uptime</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $swap['used_h'] ?? '—' }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    swap din {{ $swap['total_h'] ?? '—' }} ({{ $swap['percent'] ?? 0 }}%)
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Uptime: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $uptime ?? '—' }}</span></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">PHP memory_limit: {{ $phpMemoryLimit ?? '—' }}</div>
            </div>
        </div>

        {{-- Filesystems --}}
        @if(count($mounts) > 0)
            <x-filament::section>
                <x-slot name="heading">Sisteme de fișiere</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Mount</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Filesystem</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Total</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Ocupat</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Liber</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($mounts as $mnt)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $mnt['mount'] }}</td>
                                    <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $mnt['fs'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $mnt['total_h'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $mnt['used_h'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $mnt['avail_h'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono {{ $mnt['percent'] >= 90 ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">{{ $mnt['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Top processes by memory --}}
        @if(count($processes) > 0)
            <x-filament::section>
                <x-slot name="heading">Procese — top după memorie</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">PID</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">User</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Comandă</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">RAM</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">%RAM</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($processes as $proc)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $proc['pid'] }}</td>
                                    <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $proc['user'] }}</td>
                                    <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $proc['command'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $proc['rss_h'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $proc['mem_pct'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Directory breakdown (opt-in) --}}
        <x-filament::section>
            <x-slot name="heading">Spațiu pe directoare (doar aplicația)</x-slot>
            <x-slot name="description">Cel mai scump calcul — pornit manual din butonul „Analizează directoare". Rezultat păstrat 15 min.</x-slot>

            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                ⚠️ Acesta e <strong>doar directorul aplicației</strong>. Diferența față de „ocupat" pe disc ({{ $disk['used_h'] ?? '—' }}) e ocupată de <strong>alte lucruri de pe server</strong> — datele PostgreSQL, alte site-uri, loguri, sistem — pe care user-ul web nu le poate scana fără <code>root</code>. Pentru breakdown complet al discului, rulează în SSH:
                <code class="mt-1 block break-all rounded bg-gray-100 p-2 dark:bg-black/30">sudo du -bx --max-depth=1 / 2>/dev/null | sort -rn | head -20 | awk '{printf "%.2f GB\t%s\n",$1/1073741824,$2}'</code>
            </div>

            @if(!$dirScanned)
                <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Neanalizat încă. Apasă <strong>Analizează directoare</strong> (sus) pentru a calcula.
                </div>
            @elseif(count($dirSizes) === 0)
                <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Nu s-a putut calcula (shell indisponibil sau permisiuni).
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Director</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Dimensiune</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($dirSizes as $dir)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 {{ $dir['label'] === '(total aplicație)' ? 'font-semibold' : '' }}">
                                    <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $dir['label'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $dir['size'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
