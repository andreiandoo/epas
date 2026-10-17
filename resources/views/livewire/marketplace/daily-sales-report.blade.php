<div class="mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h3 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
            <x-heroicon-o-calendar-days class="w-4 h-4" />
            Raport vânzări pe zi
        </h3>
        <div class="flex items-center gap-2">
            <label for="dailyReportDate" class="text-xs text-gray-500 dark:text-gray-400">Ziua:</label>
            <input
                type="date"
                id="dailyReportDate"
                wire:model.live="date"
                min="{{ $dailyReportMinDate }}"
                max="{{ $dailyReportMaxDate }}"
                class="px-2 py-1 text-xs bg-white border border-gray-300 rounded-md dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>
    </div>

    <div class="overflow-auto max-h-[70vh] bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700" wire:key="daily-report-{{ $dailyReportDate }}">
        {{-- Responsive columns: on phones (<640px) the "Total" group
             cells are hidden AND its colgroup widths collapse to 0, so
             the "Azi" columns reflow to fill the freed width; the
             event/venue columns also shrink so the table fits without a
             wide horizontal scroll. On desktop everything shows at full
             width. (Uses a scoped media query instead of Tailwind's
             `sm:table-cell`, which was hiding the group on desktop too.) --}}
        <style>
            @media (max-width: 639px) {
                .dr-total-cell { display: none !important; }
                .dr-col-total { width: 0 !important; }
                .dr-col-name  { width: 34% !important; }
                .dr-col-venue { width: 16% !important; }
            }
        </style>
        <table class="text-xs border-separate border-spacing-0" style="width: 100%; table-layout: fixed;">
            {{-- Explicit column widths so max-width on td actually clips the event name --}}
            <colgroup>
                <col class="dr-col-name" style="width: 260px;">
                <col class="dr-col-venue" style="width: 160px;">
                <col><col><col><col>
                <col class="dr-col-total"><col class="dr-col-total"><col class="dr-col-total"><col class="dr-col-total">
            </colgroup>
            <thead class="text-gray-700 dark:text-gray-200">
                {{-- Group row — sticky to top of scroll container --}}
                <tr>
                    <th rowspan="2" style="position: sticky; top: 0; z-index: 20;" class="px-3 py-2 font-semibold text-left text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-900 border-b border-gray-300 dark:border-gray-700">Eveniment</th>
                    <th rowspan="2" style="position: sticky; top: 0; z-index: 20;" class="px-3 py-2 font-semibold text-left text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-900 border-b border-gray-300 dark:border-gray-700">Locație</th>
                    <th colspan="4" style="position: sticky; top: 0; z-index: 20; background-color: #4f46e5;" class="px-3 py-2 font-bold text-center text-white border-b border-l border-gray-300 dark:border-gray-700">Azi</th>
                    <th colspan="4" style="position: sticky; top: 0; z-index: 20; background-color: #059669;" class="dr-total-cell px-3 py-2 font-bold text-center text-white border-b border-l border-gray-300 dark:border-gray-700">Total</th>
                </tr>
                {{-- Sub-header row — sticky just under the group row --}}
                <tr>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #c7d2fe;" class="px-3 py-2 font-semibold text-right text-indigo-900 whitespace-nowrap border-b border-l border-gray-300 dark:border-gray-700">Cmd.</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #c7d2fe;" class="px-3 py-2 font-semibold text-right text-indigo-900 whitespace-nowrap border-b border-gray-300 dark:border-gray-700">Bilete</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #c7d2fe;" class="px-3 py-2 font-semibold text-right text-indigo-900 whitespace-nowrap border-b border-gray-300 dark:border-gray-700">Vânzări</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #c7d2fe;" class="px-3 py-2 font-semibold text-right text-indigo-900 whitespace-nowrap border-b border-gray-300 dark:border-gray-700" title="Comision net, fără TVA">Comision net</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #a7f3d0;" class="dr-total-cell px-3 py-2 font-semibold text-right text-emerald-900 whitespace-nowrap border-b border-l border-gray-300 dark:border-gray-700">Cmd.</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #a7f3d0;" class="dr-total-cell px-3 py-2 font-semibold text-right text-emerald-900 whitespace-nowrap border-b border-gray-300 dark:border-gray-700">Bilete</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #a7f3d0;" class="dr-total-cell px-3 py-2 font-semibold text-right text-emerald-900 whitespace-nowrap border-b border-gray-300 dark:border-gray-700">Vânzări</th>
                    <th style="position: sticky; top: 34px; z-index: 10; background-color: #a7f3d0;" class="dr-total-cell px-3 py-2 font-semibold text-right text-emerald-900 whitespace-nowrap border-b border-gray-300 dark:border-gray-700" title="Comision net, fără TVA">Comision net</th>
                </tr>
            </thead>
            <tbody x-data="{ showAll: false }">
                @forelse($dailyEventReport as $i => $row)
                    {{-- No hover bg change — Filament's panel hover styles produce
                         a white-on-white row in dark mode. Plain row stays readable.
                         Rows past the 10th stay hidden until "Vezi toate datele". --}}
                    @php $isMulti = $row['is_multi_event'] ?? false; @endphp
                    <tr class="text-gray-700 dark:text-gray-300 @if($isMulti) bg-amber-50/60 dark:bg-amber-900/10 @endif" @if($i >= 10 && !$isMulti) x-show="showAll" x-cloak @endif>
                        <td style="max-width: 300px;" class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 overflow-hidden">
                            @if($isMulti)
                                <div title="{{ $row['event_date_label'] }}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <span class="font-medium text-amber-700 dark:text-amber-300">{{ $row['event_name'] }}</span>
                                    <span class="font-normal text-gray-400 dark:text-gray-500 text-[11px]">({{ $row['event_date_label'] }})</span>
                                </div>
                            @else
                                <a href="{{ $row['event_edit_url'] }}" target="_blank"
                                   class="block text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline"
                                   title="{{ $row['event_name'] }} ({{ $row['event_date_label'] }})"
                                   style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <span class="font-medium">{{ $row['event_name'] }}</span>
                                    <span class="font-normal text-gray-400 dark:text-gray-500 text-[11px]">({{ $row['event_date_label'] }})</span>
                                </a>
                            @endif
                        </td>
                        <td class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="text-gray-900 dark:text-gray-100 truncate" title="{{ $row['venue_name'] }}{{ $row['venue_city'] ? ' — ' . $row['venue_city'] : '' }}">{{ $row['venue_name'] }}</div>
                            @if($row['venue_city'])
                                <div class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ $row['venue_city'] }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums border-b border-l border-gray-100 dark:border-gray-700">{{ number_format($row['orders_day']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums border-b border-gray-100 dark:border-gray-700">{{ number_format($row['tickets_day']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-indigo-600 dark:text-indigo-400 border-b border-gray-100 dark:border-gray-700">{{ number_format($row['sales_day'], 2) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-emerald-600 dark:text-emerald-400 border-b border-gray-100 dark:border-gray-700">{{ number_format($row['commission_day'], 2) }}@if(($dailyReportVatRate ?? 0) > 0)<span class="block text-[10px] font-normal text-gray-400 dark:text-gray-500">(+TVA {{ number_format($row['commission_day'] * $dailyReportVatRate / 100, 2) }})</span>@endif</td>
                        <td class="dr-total-cell px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400 border-b border-l border-gray-100 dark:border-gray-700">{{ number_format($row['orders_total']) }}</td>
                        <td class="dr-total-cell px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">{{ number_format($row['tickets_total']) }}</td>
                        <td class="dr-total-cell px-3 py-2 text-right tabular-nums font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700">{{ number_format($row['sales_total'], 2) }}</td>
                        <td class="dr-total-cell px-3 py-2 text-right tabular-nums font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700">{{ number_format($row['commission_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-6 text-center text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                            Nicio vânzare în ziua selectată.
                        </td>
                    </tr>
                @endforelse

                {{-- Show-all toggle: collapse to the first 10 rows; button
                     (full width, right before the totals) reveals the rest. --}}
                @if(count($dailyEventReport) > 10)
                    <tr x-show="!showAll">
                        <td colspan="10" class="p-0 border-b border-gray-100 dark:border-gray-700">
                            <button type="button" x-on:click="showAll = true"
                                    class="w-full px-3 py-2.5 text-sm font-semibold text-indigo-600 transition-colors dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                Vezi toate datele ({{ count($dailyEventReport) }} evenimente)
                            </button>
                        </td>
                    </tr>
                @endif
            </tbody>
            @if(count($dailyEventReport) > 0)
                @php
                    $sumOrdersDay = array_sum(array_column($dailyEventReport, 'orders_day'));
                    $sumTicketsDay = array_sum(array_column($dailyEventReport, 'tickets_day'));
                    $sumSalesDay = array_sum(array_column($dailyEventReport, 'sales_day'));
                    $sumCommissionDay = array_sum(array_column($dailyEventReport, 'commission_day'));
                    $sumOrdersTotal = array_sum(array_column($dailyEventReport, 'orders_total'));
                    $sumTicketsTotal = array_sum(array_column($dailyEventReport, 'tickets_total'));
                    $sumSalesTotal = array_sum(array_column($dailyEventReport, 'sales_total'));
                    $sumCommissionTotal = array_sum(array_column($dailyEventReport, 'commission_total'));
                @endphp
                <tfoot class="bg-gray-50 dark:bg-gray-900/80">
                    <tr class="font-semibold text-gray-900 dark:text-white">
                        <td colspan="2" class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">Total ({{ count($dailyEventReport) }} {{ count($dailyEventReport) === 1 ? 'eveniment' : 'evenimente' }}):</td>
                        <td class="px-3 py-2 text-right tabular-nums border-l border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white">{{ number_format($sumOrdersDay) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-900 dark:text-white">{{ number_format($sumTicketsDay) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-bold text-indigo-800 dark:text-indigo-300">{{ number_format($sumSalesDay, 2) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-bold text-emerald-800 dark:text-emerald-300">{{ number_format($sumCommissionDay, 2) }}@if(($dailyReportVatRate ?? 0) > 0)<span class="block text-[10px] font-normal text-gray-500 dark:text-gray-400">(+TVA {{ number_format($sumCommissionDay * $dailyReportVatRate / 100, 2) }})</span>@endif</td>
                        <td class="dr-total-cell px-3 py-2 text-right tabular-nums border-l border-gray-200 dark:border-gray-700"></td>
                        <td class="dr-total-cell px-3 py-2"></td>
                        <td class="dr-total-cell px-3 py-2"></td>
                        <td class="dr-total-cell px-3 py-2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
