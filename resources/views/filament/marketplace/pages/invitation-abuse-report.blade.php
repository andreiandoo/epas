<x-filament-panels::page>
    @if(!$marketplace || !$invitationAbuse)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found.</p>
        </div>
    @else
        @php
            $currency = $marketplace->currency ?? 'RON';
            $sumAll = $invitationAbuse['all_time']['summary'];
            $sumPast = $invitationAbuse['past']['summary'];
            $sumUpcoming = $invitationAbuse['upcoming']['summary'];
            $eventsPast = $invitationAbuse['past']['events'];
            $eventsUpcoming = $invitationAbuse['upcoming']['events'];
            $orgsAll = $invitationAbuse['all_time']['top_organizers'];
        @endphp

        {{-- Header + refresh --}}
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 mb-5 overflow-hidden bg-blue-800 dark:bg-blue-900 rounded-xl shadow-sm">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-blue-100" />
                <div>
                    <h1 class="text-lg font-semibold text-white">Comision pierdut prin invitații gratuite</h1>
                    <p class="text-xs text-blue-100">Raport complet cu breakdown pe evenimente trecute vs. viitoare.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('filament.marketplace.pages.dashboard') }}" class="inline-flex items-center gap-1 text-xs font-medium text-blue-100 hover:text-white hover:underline">
                    <x-heroicon-o-arrow-left class="w-3.5 h-3.5" />
                    Dashboard
                </a>
                <a href="{{ request()->fullUrlWithQuery(['refresh_invite_abuse' => 1]) }}" class="inline-flex items-center gap-1 text-xs font-medium text-white hover:underline">
                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                    Actualizează cache
                </a>
            </div>
        </div>

        {{-- All-time aggregate --}}
        <div class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <h2 class="mb-3 text-sm font-semibold tracking-wider uppercase text-gray-700 dark:text-gray-300">Total istoric</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Comision pierdut (estimat)</p>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($sumAll['total_lost'], 2) }} {{ $currency }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Comision efectiv câștigat</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($sumAll['total_earned'], 2) }} {{ $currency }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Invitații / total bilete</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($sumAll['total_invitations']) }} <span class="text-sm font-normal text-gray-500">/ {{ number_format($sumAll['total_invitations'] + $sumAll['total_paid_tickets']) }}</span>
                    </p>
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ number_format($sumAll['invitation_ratio_pct'], 1) }}%</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Evenimente / organizatori</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($sumAll['events_count']) }} <span class="text-sm font-normal text-gray-500">/ {{ number_format($sumAll['unique_organizers']) }}</span></p>
                </div>
            </div>
        </div>

        {{-- Explanatory note --}}
        <div class="p-4 mb-5 text-xs italic text-gray-600 bg-gray-50 dark:bg-gray-900/40 dark:text-gray-400 rounded-lg border border-gray-200 dark:border-gray-700">
            Pentru fiecare eveniment se calculează media comisionului pe bilet vândut, apoi se proiectează pe numărul de invitații emise. Sunt afișate DOAR evenimentele unde proiecția de comision pierdut depășește comisionul efectiv câștigat. Include: invitații standalone (fără comandă) + bilete cu valoare 0 emise prin comandă (promo 100%, bulk admin, etc). Cache 30 min.
        </div>

        {{-- Split: past vs upcoming --}}
        <div class="grid grid-cols-1 gap-5 mb-5 lg:grid-cols-2">
            @include('filament.marketplace.pages.partials.invitation-abuse-bucket', [
                'title' => 'Evenimente trecute (închise)',
                'icon' => 'heroicon-o-clock',
                'iconColor' => 'text-gray-500',
                'summary' => $sumPast,
                'events' => $eventsPast,
                'currency' => $currency,
                'accentClass' => 'border-gray-300 dark:border-gray-700',
                'headerBg' => 'bg-gray-100 dark:bg-gray-800/60',
                'headerText' => 'text-gray-800 dark:text-gray-100',
            ])

            @include('filament.marketplace.pages.partials.invitation-abuse-bucket', [
                'title' => 'Evenimente viitoare / live',
                'icon' => 'heroicon-o-calendar-days',
                'iconColor' => 'text-emerald-600 dark:text-emerald-400',
                'summary' => $sumUpcoming,
                'events' => $eventsUpcoming,
                'currency' => $currency,
                'accentClass' => 'border-emerald-300 dark:border-emerald-800',
                'headerBg' => 'bg-emerald-100 dark:bg-emerald-900/40',
                'headerText' => 'text-emerald-800 dark:text-emerald-100',
            ])
        </div>

        {{-- Top organizers leaderboard --}}
        @if(count($orgsAll) > 0)
        <div class="mb-5 overflow-hidden bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="px-4 py-3 bg-blue-800 dark:bg-blue-900">
                <h3 class="text-sm font-semibold text-white">Top organizatori (după comision total pierdut, toate perioadele)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Organizator</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Evenimente</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Bilete vândute</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Invitații emise</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Câștigat</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Pierdut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orgsAll as $org)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-3 py-2">
                                <a href="{{ route('filament.marketplace.resources.organizers.edit', ['record' => $org['organizer_id']]) }}" class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $org['organizer_name'] }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($org['events']) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($org['total_paid_tickets']) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600 dark:text-rose-400 font-medium tabular-nums">{{ number_format($org['total_invitations']) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400 tabular-nums">{{ number_format($org['total_earned'], 2) }} {{ $currency }}</td>
                            <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400 font-bold tabular-nums">{{ number_format($org['total_lost'], 2) }} {{ $currency }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endif
</x-filament-panels::page>
