<section class="ep-card" aria-labelledby="ep-h-dr">
    <div class="ep-card-h">
        <h2 id="ep-h-dr">{{ __('Vânzări pe zi, pe evenimente') }}</h2>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">
            <label for="dailyReportDate" class="ep-hint">{{ __('Ziua:') }}</label>
            <input
                type="date"
                id="dailyReportDate"
                wire:model.live="date"
                min="{{ $dailyReportMinDate }}"
                max="{{ $dailyReportMaxDate }}"
                class="ep-in"
            />
        </div>
    </div>

    <div class="ep-drwrap" wire:key="daily-report-{{ $dailyReportDate }}">
        {{-- Pe telefoane (<640px) grupul "Total" se ascunde, iar coloanele
             "În ziua aleasă" preiau lățimea. --}}
        <style>
            @media (max-width: 639px) {
                .dr-total-cell { display: none !important; }
                .dr-col-total { width: 0 !important; }
                .dr-col-name  { width: 34% !important; }
                .dr-col-venue { width: 16% !important; }
            }
        </style>
        <table class="ep-tbl" style="width: 100%; table-layout: fixed;">
            <colgroup>
                <col class="dr-col-name" style="width: 260px;">
                <col class="dr-col-venue" style="width: 160px;">
                <col><col><col><col>
                <col class="dr-col-total"><col class="dr-col-total"><col class="dr-col-total"><col class="dr-col-total">
            </colgroup>
            <thead class="ep-sticky">
                <tr class="r1">
                    <th rowspan="2" class="l">{{ __('Eveniment') }}</th>
                    <th rowspan="2" class="l">{{ __('Locație') }}</th>
                    <th colspan="4" class="grp g1 sep">{{ __('În ziua aleasă') }}</th>
                    <th colspan="4" class="grp g2 sep dr-total-cell">{{ __('Total') }}</th>
                </tr>
                <tr class="r2">
                    <th class="sep">{{ __('Cmd.') }}</th>
                    <th>{{ __('Bilete') }}</th>
                    <th>{{ __('Vânzări') }}</th>
                    <th title="{{ __('Comision net, fără TVA') }}">{{ __('Comision net') }}</th>
                    <th class="dr-total-cell sep">{{ __('Cmd.') }}</th>
                    <th class="dr-total-cell">{{ __('Bilete') }}</th>
                    <th class="dr-total-cell">{{ __('Vânzări') }}</th>
                    <th class="dr-total-cell" title="{{ __('Comision net, fără TVA') }}">{{ __('Comision net') }}</th>
                </tr>
            </thead>
            <tbody x-data="{ showAll: false }">
                @forelse($dailyEventReport as $i => $row)
                    {{-- Rândurile peste al 10-lea rămân ascunse până la "Vezi toate datele". --}}
                    @php $isMulti = $row['is_multi_event'] ?? false; @endphp
                    <tr class="@if($isMulti) multi @endif" @if($i >= 10 && !$isMulti) x-show="showAll" x-cloak @endif>
                        <td class="nm">
                            @if($isMulti)
                                <div title="{{ $row['event_date_label'] }}" class="clip">
                                    <span class="multi-name">{{ $row['event_name'] }}</span>
                                    <span class="ep-hint">({{ $row['event_date_label'] }})</span>
                                </div>
                            @else
                                <a href="{{ $row['event_edit_url'] }}" target="_blank" class="clip ep-evlink"
                                   title="{{ $row['event_name'] }} ({{ $row['event_date_label'] }})">
                                    <span class="evn">{{ $row['event_name'] }}</span>
                                    <span class="ep-hint">({{ $row['event_date_label'] }})</span>
                                </a>
                            @endif
                        </td>
                        <td class="ven">
                            <div class="clip" title="{{ $row['venue_name'] }}{{ $row['venue_city'] ? ' — ' . $row['venue_city'] : '' }}">{{ $row['venue_name'] }}</div>
                            @if($row['venue_city'])
                                <div class="clip ep-hint" style="font-size: 11px;">{{ $row['venue_city'] }}</div>
                            @endif
                        </td>
                        <td class="num sep">{{ number_format($row['orders_day']) }}</td>
                        <td class="num">{{ number_format($row['tickets_day']) }}</td>
                        <td class="num strong">{{ number_format($row['sales_day'], 2) }}</td>
                        <td class="num strong">{{ number_format($row['commission_day'], 2) }}@if(($dailyReportVatRate ?? 0) > 0)<span class="sub">(+TVA {{ number_format($row['commission_day'] * $dailyReportVatRate / 100, 2) }})</span>@endif</td>
                        <td class="num dr-total-cell sep mut">{{ number_format($row['orders_total']) }}</td>
                        <td class="num dr-total-cell mut">{{ number_format($row['tickets_total']) }}</td>
                        <td class="num dr-total-cell strong">{{ number_format($row['sales_total'], 2) }}</td>
                        <td class="num dr-total-cell strong">{{ number_format($row['commission_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="ep-hint" style="text-align: center; padding: 22px;">
                            {{ __('Nicio vânzare în ziua selectată.') }}
                        </td>
                    </tr>
                @endforelse

                {{-- Comută lista scurtă (10 rânduri) pe lista completă. --}}
                @if(count($dailyEventReport) > 10)
                    <tr x-show="!showAll">
                        <td colspan="10" style="padding: 0;">
                            <button type="button" x-on:click="showAll = true" class="ep-showall">
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
                @endphp
                <tfoot>
                    <tr class="tot">
                        <td colspan="2" style="text-align: right;" class="mut">Total ({{ count($dailyEventReport) }} {{ count($dailyEventReport) === 1 ? 'eveniment' : 'evenimente' }}):</td>
                        <td class="num sep">{{ number_format($sumOrdersDay) }}</td>
                        <td class="num">{{ number_format($sumTicketsDay) }}</td>
                        <td class="num strong">{{ number_format($sumSalesDay, 2) }}</td>
                        <td class="num strong">{{ number_format($sumCommissionDay, 2) }}@if(($dailyReportVatRate ?? 0) > 0)<span class="sub">(+TVA {{ number_format($sumCommissionDay * $dailyReportVatRate / 100, 2) }})</span>@endif</td>
                        <td class="dr-total-cell sep"></td>
                        <td class="dr-total-cell"></td>
                        <td class="dr-total-cell"></td>
                        <td class="dr-total-cell"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</section>
