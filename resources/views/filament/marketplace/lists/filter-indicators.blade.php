{{-- Filament's active-filter row, with the total number of results in front. --}}
@php use Filament\Tables\Filters\Indicator; @endphp
<div class="fi-ta-filter-indicators">
    <div>
        <span class="ep-lst-total">{{ __('Rezultate') }}: <strong>{{ number_format($total, 0, ',', '.') }}</strong></span>
        <span class="ep-lst-total-sep" aria-hidden="true">|</span>
        <span class="fi-ta-filter-indicators-label">
            {{ __('filament-tables::table.filters.indicator') }}
        </span>

        <div class="fi-ta-filter-indicators-badges-ctn" role="list">
            @foreach ($filterIndicators as $indicator)
                <x-filament::badge :color="$indicator->getColor()" role="listitem">
                    {{ $indicator->getLabel() }}

                    @if ($indicator->isRemovable())
                        @php $indicatorRemoveLivewireClickHandler = $indicator->getRemoveLivewireClickHandler(); @endphp
                        <x-slot
                            name="deleteButton"
                            :label="__('filament-tables::table.filters.actions.remove.label')"
                            :wire:click="$indicatorRemoveLivewireClickHandler"
                            wire:loading.attr="disabled"
                            wire:target="removeTableFilter"
                        ></x-slot>
                    @endif
                </x-filament::badge>
            @endforeach
        </div>
    </div>

    @if (collect($filterIndicators)->contains(fn (Indicator $indicator): bool => $indicator->isRemovable()))
        {{ $removeAll }}
    @endif
</div>
