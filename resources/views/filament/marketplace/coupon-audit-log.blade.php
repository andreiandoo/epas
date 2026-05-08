@php
    /**
     * Audit log pentru un coupon code.
     * Variabila $record vine din ViewField.
     *
     * Afișează:
     *  - Cine a creat codul (nume, email)
     *  - Când (timestamp + IP + device)
     *  - Istoric editări (causer, timestamp, IP, device, ce s-a schimbat)
     */
    $record = $getRecord();

    /** @var \App\Models\Coupon\CouponCode $record */
    if (!$record || !$record->exists) {
        return;
    }

    $activities = $record->activities()->with('causer')->limit(50)->get();

    /**
     * Parsează un user-agent în format "Browser X pe OS Y" — minimal,
     * fără dependență externă. Acoperă cazurile comune (Chrome, Firefox,
     * Safari, Edge pe Windows / macOS / Linux / iOS / Android).
     */
    $parseUa = function (?string $ua): array {
        if (!$ua) return ['device' => 'Necunoscut', 'icon' => 'computer'];

        $browser = 'Browser necunoscut';
        if (preg_match('/Edg\/([\d.]+)/', $ua, $m)) { $browser = 'Edge ' . explode('.', $m[1])[0]; }
        elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) { $browser = 'Firefox ' . explode('.', $m[1])[0]; }
        elseif (preg_match('/OPR\/([\d.]+)/', $ua, $m) || preg_match('/Opera\/([\d.]+)/', $ua, $m)) { $browser = 'Opera ' . explode('.', $m[1])[0]; }
        elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m)) { $browser = 'Chrome ' . explode('.', $m[1])[0]; }
        elseif (preg_match('/Version\/([\d.]+).+Safari/', $ua, $m)) { $browser = 'Safari ' . explode('.', $m[1])[0]; }

        $os = 'OS necunoscut';
        $icon = 'computer';
        if (preg_match('/iPhone|iPad|iPod/', $ua)) { $os = 'iOS'; $icon = 'phone'; }
        elseif (preg_match('/Android/', $ua)) { $os = 'Android'; $icon = 'phone'; }
        elseif (preg_match('/Windows NT 10\.0/', $ua)) { $os = 'Windows 10/11'; }
        elseif (preg_match('/Windows NT/', $ua)) { $os = 'Windows'; }
        elseif (preg_match('/Mac OS X ([\d_]+)/', $ua, $m)) { $os = 'macOS ' . str_replace('_', '.', $m[1]); }
        elseif (preg_match('/Linux/', $ua)) { $os = 'Linux'; }

        return ['device' => $browser . ' pe ' . $os, 'icon' => $icon];
    };

    $createActivity = $activities->firstWhere('event', 'created');
    $creator = $record->creator;
    $createdProps = $createActivity?->properties ?? collect();
    $createdUa = $parseUa($createdProps['user_agent'] ?? null);
    $createdIp = $createdProps['ip'] ?? null;
@endphp

<div class="space-y-6">
    {{-- Created by card --}}
    <div class="p-4 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-white/5">
        <h3 class="mb-3 text-xs font-bold tracking-wider uppercase text-gray-500 dark:text-gray-400">Creat de</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white rounded-full bg-primary-500">
                    {{ $creator ? strtoupper(substr($creator->name, 0, 1)) : '?' }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                        {{ $creator?->name ?? '— necunoscut —' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        {{ $creator?->email ?? '' }}
                    </p>
                </div>
            </div>

            <div class="text-sm">
                <dl class="space-y-1.5">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">La</dt>
                        <dd class="font-medium text-gray-900 dark:text-white text-right">
                            {{ $record->created_at?->translatedFormat('d M Y, H:i:s') ?? '—' }}
                        </dd>
                    </div>
                    @if ($createdIp)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">IP</dt>
                            <dd class="font-mono text-xs text-gray-900 dark:text-white">{{ $createdIp }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Device</dt>
                        <dd class="font-medium text-gray-900 dark:text-white text-right">{{ $createdUa['device'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Activity log --}}
    @if ($activities->count() > 0)
        <div>
            <h3 class="mb-3 text-xs font-bold tracking-wider uppercase text-gray-500 dark:text-gray-400">
                Istoric ({{ $activities->count() }})
            </h3>

            <div class="space-y-2">
                @foreach ($activities as $activity)
                    @php
                        $causer = $activity->causer;
                        $props = $activity->properties ?? collect();
                        $ua = $parseUa($props['user_agent'] ?? null);
                        $ip = $props['ip'] ?? null;
                        $eventLabel = match ($activity->event) {
                            'created' => ['Creat', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300'],
                            'updated' => ['Editat', 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300'],
                            'deleted' => ['Șters', 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300'],
                            default => [ucfirst($activity->event ?? '—'), 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300'],
                        };
                        $changes = $props['attributes'] ?? null;
                        $previous = $props['old'] ?? null;
                    @endphp

                    <div class="p-3 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-white/5">
                        <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
                            <span class="px-2 py-0.5 text-[11px] font-bold rounded-full {{ $eventLabel[1] }}">
                                {{ $eventLabel[0] }}
                            </span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ $causer?->name ?? '— sistem —' }}
                            </span>
                            @if ($causer?->email)
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    ({{ $causer->email }})
                                </span>
                            @endif
                            <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                                {{ $activity->created_at?->translatedFormat('d M Y, H:i:s') }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            @if ($ip)
                                <span><strong class="font-semibold text-gray-700 dark:text-gray-300">IP:</strong> <span class="font-mono">{{ $ip }}</span></span>
                            @endif
                            <span><strong class="font-semibold text-gray-700 dark:text-gray-300">Device:</strong> {{ $ua['device'] }}</span>
                        </div>

                        @if (is_array($changes) && count($changes) && $activity->event === 'updated')
                            <details class="text-xs">
                                <summary class="font-semibold cursor-pointer text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                    Vezi câmpurile modificate ({{ count($changes) }})
                                </summary>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left border-b border-gray-200 dark:border-white/10">
                                                <th class="py-1 pr-3 text-gray-500 dark:text-gray-400">Câmp</th>
                                                <th class="py-1 pr-3 text-gray-500 dark:text-gray-400">Înainte</th>
                                                <th class="py-1 text-gray-500 dark:text-gray-400">După</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($changes as $field => $newValue)
                                                @php
                                                    $oldValue = is_array($previous) ? ($previous[$field] ?? null) : null;
                                                    $fmt = function ($v) {
                                                        if ($v === null) return '—';
                                                        if (is_bool($v)) return $v ? 'da' : 'nu';
                                                        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
                                                        return (string) $v;
                                                    };
                                                @endphp
                                                <tr class="border-b border-gray-100 dark:border-white/5">
                                                    <td class="py-1 pr-3 font-mono font-medium text-gray-700 dark:text-gray-300">{{ $field }}</td>
                                                    <td class="py-1 pr-3 text-gray-500 dark:text-gray-400 line-through">{{ $fmt($oldValue) }}</td>
                                                    <td class="py-1 text-gray-900 dark:text-white">{{ $fmt($newValue) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="p-4 text-sm text-center text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-white/5">
            Nu există încă entries în log. Codul a fost creat înainte ca audit-ul să fie activat.
        </div>
    @endif
</div>
