@php
    /**
     * Operator checklist for a payout. Steps come from
     * MarketplacePayout::getOperatorSteps() (approve → decont → factură →
     * factură POS). Only the PENDING steps are rendered, in order, so each one
     * disappears as the operator completes it; the whole section hides when
     * none remain. The POS step is present only when the period has POS
     * commission to bill.
     */
    $record = $getRecord();
    $steps = $record->getOperatorSteps();
    $total = count($steps);
    $pending = array_values(array_filter($steps, fn ($s) => empty($s['done'])));
    $doneCount = $total - count($pending);
@endphp

@if(count($pending) > 0)
    <div class="space-y-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ $doneCount }} din {{ $total }} {{ $total === 1 ? 'pas finalizat' : 'pași finalizați' }} — urmează:
        </p>
        <ol class="space-y-1.5">
            @foreach($pending as $i => $step)
                <li class="flex items-start gap-3 p-2.5 rounded-lg border {{ $i === 0 ? 'border-primary-300 bg-primary-50 dark:border-primary-700 dark:bg-primary-900/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
                    <span class="flex items-center justify-center w-6 h-6 mt-0.5 rounded-full text-xs font-bold shrink-0 {{ $i === 0 ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $doneCount + $i + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium {{ $i === 0 ? 'text-primary-800 dark:text-primary-200' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $step['label'] }}
                            </span>
                            @if($i === 0)
                                <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400 shrink-0">Următorul pas</span>
                            @endif
                        </div>
                        @if(!empty($step['hint']))
                            <div class="mt-1 text-[11px] leading-tight text-gray-600 dark:text-gray-400">
                                {{ $step['hint'] }}
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
