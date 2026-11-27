{{-- Vederi rapide (tab-urile paginii) ca pastile cu contor; "De completat" aduna tab-urile marcate ca atare --}}
@php
    $fmt = fn ($v) => is_numeric($v) ? number_format((int) $v, 0, ',', '.') : $v;
    $main = [];
    $todo = [];
    foreach ($tabs as $key => $tab) {
        if (in_array((string) $key, $todoKeys, true)) {
            $todo[$key] = $tab;
        } else {
            $main[$key] = $tab;
        }
    }
    $activeTab = (string) $activeTab;
    $todoActive = array_key_exists($activeTab, $todo);
@endphp
<div class="ep-qv" role="tablist" aria-label="{{ __('Vederi') }}">
    @foreach ($main as $key => $tab)
        @php $on = (string) $key === $activeTab; @endphp
        <button type="button" role="tab" wire:key="ep-qv-{{ $key }}"
                aria-selected="{{ $on ? 'true' : 'false' }}"
                class="ep-view{{ $on ? ' on' : '' }}"
                wire:click="$set('activeTab', '{{ $key }}')">
            {{ $tab->getLabel() }}@if (filled($badge = $tab->getBadge()))<span class="n">{{ $fmt($badge) }}</span>@endif
        </button>
    @endforeach

    @if ($todo)
        <div class="ep-todo" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
            <button type="button" class="ep-view{{ $todoActive ? ' on' : '' }}" aria-haspopup="menu"
                    x-bind:aria-expanded="open" x-on:click="open = ! open">
                {{ $todoLabel ?? __('De completat') }}<span class="n">▾</span>
            </button>
            <div class="ep-menu" role="menu" x-show="open" x-cloak>
                @foreach ($todo as $key => $tab)
                    <button type="button" role="menuitem" wire:key="ep-todo-{{ $key }}"
                            class="{{ (string) $key === $activeTab ? 'on' : '' }}"
                            wire:click="$set('activeTab', '{{ $key }}')" x-on:click="open = false">
                        {{ $tab->getLabel() }}@if (filled($badge = $tab->getBadge()))<span class="n">{{ $fmt($badge) }}</span>@endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
