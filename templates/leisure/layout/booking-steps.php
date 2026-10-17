<?php
/**
 * Pașii rezervării (zi → interval → durată → persoane), pe starea LVBooking
 * (assets/booking.js). Inclus în cardul de pe desktop și în sertarul de pe mobil.
 * $lvCompact: varianta îngustă a cardului.
 */
$lvCompact = $lvCompact ?? false;
$step = 1;
?>
            <div class="mt-6" x-data="{ grid: false }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="text-sm font-bold"><?= $step++ ?>. Alege ziua</span>
                    <button type="button" @click="grid = !grid" class="text-xs font-semibold text-pine-700 underline-offset-4 hover:underline" x-text="grid ? 'Zilele următoare' : 'Tot calendarul'"></button>
                </div>

                <div x-show="lvLoadingMonth && !lvStrip.length" class="grid grid-cols-5 gap-2" aria-hidden="true">
                    <template x-for="i in 5" :key="i"><div class="h-[74px] animate-pulse rounded-[16px] bg-pine-900/[.06]"></div></template>
                </div>
                <p x-show="lvNoDates" class="rounded-[16px] border border-pine-900/10 px-4 py-4 text-sm text-pine-900/60">Nu sunt zile cu locuri în următoarele două luni. Revino curând sau <a href="<?= e(ts_link('contact')) ?>" class="font-bold text-pine-900 underline">scrie-ne</a>.</p>

                <div x-show="!grid" class="scrollbar-hide -mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    <template x-for="d in lvStrip" :key="d.date">
                        <button type="button" @click="lvPickDate(d.date)" :class="lvDate === d.date ? 'is-active' : ''" class="date-card <?= $lvCompact ? 'min-w-[64px]' : 'min-w-[72px]' ?> flex-none rounded-[16px] border border-pine-900/10 px-3 py-3 text-center">
                            <span class="block text-[9px] font-bold uppercase tracking-[.12em] opacity-55" x-text="d.day"></span>
                            <strong class="mt-1 block font-display text-xl" x-text="d.number"></strong>
                            <span class="mt-1 block h-3 text-[8px] uppercase tracking-[.08em]" :class="d.limited ? 'text-ember' : 'opacity-55'" x-text="d.limited ? 'puține' : d.label"></span>
                        </button>
                    </template>
                </div>

                <div x-show="grid" x-cloak class="rounded-[20px] border border-pine-900/10 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <button type="button" @click="lvShiftMonth(-1)" :disabled="!lvCanPrev" class="grid h-8 w-8 place-items-center rounded-full border border-pine-900/15 disabled:opacity-30" aria-label="Luna anterioară"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></button>
                        <strong class="text-sm capitalize" x-text="lvMonthLabel"></strong>
                        <button type="button" @click="lvShiftMonth(1)" class="grid h-8 w-8 place-items-center rounded-full border border-pine-900/15" aria-label="Luna următoare"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-[9px] font-bold uppercase tracking-[.08em] text-pine-900/40">
                        <span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span>
                    </div>
                    <div class="mt-1 grid grid-cols-7 gap-1" :class="lvLoadingMonth ? 'opacity-50' : ''">
                        <template x-for="c in lvCells" :key="c.key">
                            <button type="button" @click="c.open && (lvPickDate(c.date), grid = false)" :disabled="c.empty || !c.open"
                                    :class="c.empty ? 'invisible' : (lvDate === c.date ? 'bg-pine-900 text-acid' : (c.open ? (c.status === 'limited' ? 'bg-ember/15 hover:bg-ember/25' : 'bg-acid/40 hover:bg-acid') : 'text-pine-900/25 line-through'))"
                                    class="aspect-square rounded-[10px] text-xs font-semibold transition disabled:cursor-default" x-text="c.empty ? '' : c.day"></button>
                        </template>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3 text-[10px] text-pine-900/50">
                        <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-acid/60"></i>locuri libere</span>
                        <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-ember/30"></i>ultimele locuri</span>
                    </div>
                </div>
            </div>

            <div class="mt-6" x-show="lvDate && (lvLoadingSlots || !lvWholeDay)">
                <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold"><?= $step++ ?>. Alege ora</span><span class="text-[10px] font-semibold text-pine-900/45" x-text="lvTimed.length ? lvTimed.length + ' intervale' : ''"></span></div>
                <div x-show="lvLoadingSlots" class="grid grid-cols-3 gap-2" aria-hidden="true">
                    <template x-for="i in 3" :key="i"><div class="h-[46px] animate-pulse rounded-[14px] bg-pine-900/[.06]"></div></template>
                </div>
                <div x-show="!lvLoadingSlots" class="grid grid-cols-3 gap-2">
                    <template x-for="slot in lvTimed" :key="slot.id">
                        <button type="button" @click="lvPickSlot(slot)" :disabled="!slot.open" :class="lvSlotId === slot.id ? 'is-active' : ''" class="time-card rounded-[14px] border border-pine-900/10 px-2 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-35">
                            <span x-text="slot.time_slot_start"></span>
                            <span x-show="slot.open && slot.status === 'limited'" class="mt-0.5 block text-[8px] uppercase tracking-[.1em] text-ember" x-text="'încă ' + slot.remaining"></span>
                            <span x-show="!slot.open" class="mt-0.5 block text-[8px] uppercase tracking-[.1em]">complet</span>
                        </button>
                    </template>
                </div>
                <p x-show="!lvLoadingSlots && !lvSlots.length" class="text-sm text-pine-900/55">Nu mai sunt intervale în ziua aleasă.</p>
            </div>
            <p x-show="lvDate && !lvLoadingSlots && lvWholeDay && lvSlot" class="mt-4 rounded-[14px] bg-acid/30 px-4 py-3 text-xs font-semibold text-pine-900" x-text="'Acces toată ziua · ' + (lvSlot ? 'încă ' + lvSlot.remaining + ' locuri' : '')"></p>

            <div class="mt-6" x-show="lvVariants.length > 1">
                <div class="mb-3"><span class="text-sm font-bold"><?= $step++ ?>. Durata</span></div>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="(v, i) in lvVariants" :key="i">
                        <button type="button" @click="lvVariant = i" :class="lvVariant === i ? 'is-active' : ''" class="time-card rounded-[14px] border border-pine-900/10 px-3 py-2.5 text-left text-sm font-semibold">
                            <span class="block" x-text="v.label || (v.duration_minutes + ' min')"></span>
                            <span class="block text-[10px] font-normal opacity-60" x-text="lvLei(v.price_cents)"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold"><?= $step ?>. <span x-text="lv.category === 'rental' ? 'Bucăți' : 'Persoane'"></span></span><span class="text-xs text-pine-900/45" x-text="lvUnit + (lv.category === 'rental' ? ' / bucată' : ' / persoană')"></span></div>
                <div class="flex items-center justify-between rounded-[17px] border border-pine-900/10 px-4 py-3.5">
                    <div><strong class="block text-sm" x-text="lv.name"></strong><span class="text-[10px] text-pine-900/45" x-text="lvSlot ? 'maximum ' + lvMaxQty + ' pe comandă' : 'alege ziua întâi'"></span></div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="lvDec()" class="grid h-9 w-9 place-items-center rounded-full border border-pine-900/15" aria-label="Scade numărul"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg></button>
                        <strong class="w-6 text-center" x-text="lvQty"></strong>
                        <button type="button" @click="lvInc()" class="grid h-9 w-9 place-items-center rounded-full bg-pine-900 text-acid" aria-label="Crește numărul"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></button>
                    </div>
                </div>
            </div>
