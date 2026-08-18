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
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Proces</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Ce este / ce face</th>
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
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $proc['description'] ?? '' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $proc['rss_h'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $proc['mem_pct'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    ℹ️ La PostgreSQL, coloana <strong>RAM</strong> (RSS) per proces include și <strong>memoria partajată</strong> (<code>shared_buffers</code>), aceeași pentru toate backend-urile — deci <strong>NU se adună</strong>. Memoria reală ≈ <code>shared_buffers</code> (o singură dată) + puțin per conexiune. „idle in transaction" = conexiune care ține tranzacția deschisă (poate bloca vacuum-ul) — de urmărit dacă apar multe.
                </div>
            </x-filament::section>
        @endif

        {{-- PostgreSQL database size --}}
        @if(!empty($database))
            <x-filament::section>
                <x-slot name="heading">Bază de date PostgreSQL</x-slot>
                <x-slot name="description">Dimensiunea bazei live „{{ $database['name'] }}" (parte din <code>/var</code>) + cele mai mari tabele (cu indexuri).</x-slot>

                <div class="mb-4 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $database['size_h'] }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">total bază de date</span>
                </div>

                @if(!empty($database['tables']))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Tabel</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Dimensiune (cu indexuri)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach($database['tables'] as $tbl)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $tbl['name'] }}</td>
                                        <td class="px-3 py-2 text-right font-mono">{{ $tbl['size_h'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- PostgreSQL connections + memory config --}}
        @if(!empty($pgConfig) || !empty($pgActivity))
            <x-filament::section>
                <x-slot name="heading">PostgreSQL — conexiuni & memorie</x-slot>
                <x-slot name="description">De aici vezi exact ce e fiecare conexiune „idle": ce bază, ce user, ce aplicație, de cât timp.</x-slot>

                @if(!empty($pgConfig))
                    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        @php
                            $cfgItems = [
                                ['shared_buffers', 'Shared buffers', 'memorie partajată (o singură dată!)'],
                                ['effective_cache_size', 'Effective cache', 'estimare cache OS'],
                                ['work_mem', 'Work mem', 'per operație de sortare'],
                                ['max_connections', 'Max conexiuni', 'limita configurată'],
                                ['current_connections', 'Conexiuni acum', 'total deschise'],
                                ['idle_in_transaction', 'Idle in transaction', 'de urmărit'],
                            ];
                        @endphp
                        @foreach($cfgItems as [$key, $label, $hint])
                            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                <div class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ $pgConfig[$key] ?? '—' }}</div>
                                <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $hint }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($pgSummary))
                    <div class="mb-4 flex flex-wrap gap-2 text-xs">
                        @foreach(($pgSummary['by_state'] ?? []) as $state => $count)
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium
                                {{ $state === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
                                   : ($state === 'idle in transaction' ? 'bg-red-50 text-red-700 dark:bg-red-400/10 dark:text-red-400'
                                   : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300') }}">
                                {{ $count }} × {{ $state }}
                            </span>
                        @endforeach
                        @foreach(($pgSummary['by_db'] ?? []) as $db => $count)
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                {{ $count }} pe „{{ $db }}"
                            </span>
                        @endforeach
                    </div>
                @endif

                @if(!empty($pgActivity))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">PID</th>
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Bază</th>
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">User</th>
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Aplicație</th>
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Client</th>
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Stare</th>
                                    <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">De</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach($pgActivity as $conn)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $conn['pid'] }}</td>
                                        <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $conn['datname'] }}</td>
                                        <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">{{ $conn['usename'] }}</td>
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $conn['application_name'] }}</td>
                                        <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $conn['client'] }}</td>
                                        <td class="px-3 py-2">
                                            <span class="{{ $conn['state'] === 'active' ? 'text-emerald-600 dark:text-emerald-400' : ($conn['state'] === 'idle in transaction' ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400') }}">
                                                {{ $conn['state'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $conn['idle_h'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Textul interogărilor e vizibil doar pentru propriile conexiuni (restul necesită rol de monitorizare). „Client = local" înseamnă conexiune prin socket, pe același server (ex. PgBouncer / aplicația locală).</p>
                @endif
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
