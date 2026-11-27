@php
    // Câmp de dată afișat zz/ll/aaaa, oricare ar fi limba browserului
    // (<input type="date"> arată formatul american pe un browser în engleză).
    // Scrie în proprietatea Alpine $model a componentei părinte, ca AAAA-LL-ZZ.
    // Butonul deschide calendarul nativ, ascuns.
    $max = $max ?? null;
@endphp
<div
    style="position:relative;display:flex;align-items:center;"
    x-data="{
        text: '',
        toEu(iso) { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] : ''; },
        fromEu(str) {
            const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(str || '');
            if (!m) return '';
            const d = new Date(Date.UTC(+m[3], +m[2] - 1, +m[1]));
            if (d.getUTCDate() !== +m[1] || d.getUTCMonth() !== +m[2] - 1) return '';
            const iso = m[3] + '-' + m[2] + '-' + m[1];
            return @js($max) && iso > @js($max) ? '' : iso;
        },
        mask(v) {
            const d = (v || '').replace(/\D/g, '').slice(0, 8);
            return [d.slice(0, 2), d.slice(2, 4), d.slice(4, 8)].filter(Boolean).join('/');
        },
        init() {
            this.text = this.toEu(this.{{ $model }});
            this.$watch('{{ $model }}', v => { if (v && this.fromEu(this.text) !== v) this.text = this.toEu(v); });
        },
    }"
>
    <input type="text" inputmode="numeric" maxlength="10" placeholder="zz/ll/aaaa" autocomplete="off"
        x-model="text"
        x-on:input="text = mask(text); {{ $model }} = fromEu(text)"
        x-on:keydown.enter.prevent
        style="{{ $style }}padding-right:34px;">
    <button type="button" title="{{ __('Alege din calendar') }}" aria-label="{{ __('Alege din calendar') }}"
        x-on:click="try { $refs.picker.showPicker(); } catch (e) { $refs.picker.focus(); }"
        style="position:absolute;right:6px;display:inline-flex;padding:2px;border:0;background:transparent;color:inherit;opacity:.7;cursor:pointer;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
    </button>
    <input type="date" x-ref="picker" x-model="{{ $model }}" @if($max) max="{{ $max }}" @endif tabindex="-1" aria-hidden="true"
        style="position:absolute;right:0;bottom:0;width:1px;height:1px;opacity:0;pointer-events:none;border:0;padding:0;">
</div>
