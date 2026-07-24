<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$resp   = api_get('/tenant-client/events', ['per_page' => 100]);
$events = tc_events($resp);

$fallback = 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=200&q=80';
$zileRo = ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'];
$luniAbbr = ['','Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Noi','Dec'];

$jsEvents = [];
$firstTs = null;
foreach ($events as $e) {
    $ts = !empty($e['start_date']) ? strtotime($e['start_date']) : null;
    if ($ts && ($firstTs === null || $ts < $firstTs)) { $firstTs = $ts; }
    $sold = (bool) ($e['is_sold_out'] ?? false);
    $jsEvents[] = [
        'id'       => $e['id'] ?? 0,
        'slug'     => $e['slug'] ?? '',
        'title'    => $e['title'] ?? 'Spectacol',
        'author'   => $e['category']['name'] ?? '',
        'date'     => $ts ? date('Y-m-d', $ts) : '',
        'day'      => $ts ? (int) date('j', $ts) : '',
        'weekday'  => $ts ? $zileRo[(int) date('w', $ts)] : '',
        'month'    => $ts ? $luniAbbr[(int) date('n', $ts)] : '',
        'time'     => !empty($e['start_time']) ? substr($e['start_time'], 0, 5) : '',
        'venue'    => $e['venue']['name'] ?? '',
        'price'    => $e['price_from'] ?? null,
        'currency' => $e['currency'] ?? 'RON',
        'image'    => asset_url($e['poster_url'] ?? $e['hero_image_url'] ?? null, $fallback),
        'availability' => $sold ? 'Sold Out' : 'Locuri disponibile',
        'soldOut'  => $sold,
    ];
}
$curMonth = $firstTs ? ((int) date('n', $firstTs) - 1) : (int) date('n') - 1;
$curYear  = $firstTs ? (int) date('Y', $firstTs) : (int) date('Y');

$pageTitle = 'Program — ' . SITE_NAME;
$activeNav = 'schedule';
$pageExtraStyles = '
    .btn-burgundy { background: #722F37; color: #FFFEF2; transition: all .3s ease; }
    .btn-burgundy:hover { background: #8B3A44; }
    .schedule-row { transition: all .3s ease; border-left: 3px solid transparent; }
    .schedule-row:hover { background: rgba(212,175,55,.05); border-left-color: #D4AF37; }
    .calendar-day { transition: all .2s ease; }
    .calendar-day:hover:not(.empty) { background: rgba(212,175,55,.1); }
    .calendar-day.has-event { cursor: pointer; }
    .calendar-day.has-event::after { content: ""; position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; border-radius: 50%; background: #D4AF37; }
    .calendar-day.selected { background: #722F37; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-8 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Calendar spectacole</p>
        <h1 class="font-display text-5xl lg:text-6xl mb-4">Program</h1>
    </div>
</section>

<section class="pb-24 px-4 lg:px-8" x-data="schedulePage()">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-3 gap-8">
        <!-- Calendar -->
        <div class="lg:col-span-1">
            <div class="bg-charcoal rounded-xl p-6 sticky top-28">
                <div class="flex items-center justify-between mb-6">
                    <button @click="prevMonth()" class="p-2 hover:bg-gold/10 rounded transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
                    <h3 class="font-display text-xl" x-text="monthNames[currentMonth] + ' ' + currentYear"></h3>
                    <button @click="nextMonth()" class="p-2 hover:bg-gold/10 rounded transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
                </div>
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <template x-for="(day,i) in ['L','M','M','J','V','S','D']" :key="i"><div class="text-center text-warm-gray text-xs py-2" x-text="day"></div></template>
                </div>
                <div class="grid grid-cols-7 gap-1">
                    <template x-for="day in calendarDays" :key="day.date + '-' + day.day">
                        <div @click="day.day && selectDate(day.date)"
                            :class="{ 'empty': !day.day, 'has-event': day.hasEvent, 'selected': selectedDate === day.date, 'text-warm-gray/30': day.isOtherMonth }"
                            class="calendar-day relative aspect-square flex items-center justify-center rounded text-sm cursor-default">
                            <span x-text="day.day"></span>
                        </div>
                    </template>
                </div>
                <div class="mt-6 pt-6 border-t border-gold/10">
                    <div class="flex items-center gap-2 text-sm text-warm-gray"><span class="w-2 h-2 rounded-full bg-gold"></span><span>Spectacole disponibile</span></div>
                </div>
            </div>
        </div>

        <!-- Events list -->
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-display text-2xl">
                    <span x-show="!selectedDate">Toate spectacolele din <span x-text="monthNames[currentMonth]"></span></span>
                    <span x-show="selectedDate" x-text="formatSelectedDate()"></span>
                </h2>
                <button x-show="selectedDate" @click="selectedDate = null" class="text-gold text-sm hover:underline">Vezi toată luna</button>
            </div>
            <div class="space-y-3">
                <template x-for="event in filteredEvents" :key="event.id">
                    <div class="schedule-row bg-charcoal/50 rounded-lg p-4 lg:p-5 flex flex-col lg:flex-row gap-4">
                        <div class="lg:w-24 flex-shrink-0 flex lg:flex-col items-center lg:items-start gap-2 lg:gap-0">
                            <p class="text-gold font-display text-2xl lg:text-3xl" x-text="event.day"></p>
                            <p class="text-warm-gray text-sm" x-text="event.weekday + ', ' + event.month"></p>
                        </div>
                        <div class="flex gap-4 flex-1">
                            <img :src="event.image" :alt="event.title" class="w-20 h-20 lg:w-24 lg:h-24 rounded object-cover flex-shrink-0">
                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-gold text-sm mb-1" x-text="event.author"></p>
                                        <h3 class="font-display text-xl lg:text-2xl mb-1" x-text="event.title"></h3>
                                        <p class="text-warm-gray text-sm" x-text="event.time + ' • ' + event.venue"></p>
                                    </div>
                                    <div class="text-right hidden sm:block">
                                        <p class="text-gold font-display text-xl" x-show="event.price" x-text="'de la ' + event.price + ' ' + event.currency"></p>
                                        <p class="text-xs text-warm-gray" x-text="event.availability"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 lg:flex-shrink-0">
                            <a :href="'/spectacol/' + event.slug" class="btn-burgundy px-5 py-2.5 rounded text-sm whitespace-nowrap flex-1 lg:flex-none text-center">
                                <span x-text="event.soldOut ? 'Sold Out' : 'Bilete'"></span>
                            </a>
                        </div>
                    </div>
                </template>
                <div x-show="filteredEvents.length === 0" class="text-center py-16 bg-charcoal/30 rounded-xl">
                    <p class="text-warm-gray text-lg">Nu există spectacole programate în această perioadă.</p>
                    <button @click="selectedDate = null" class="text-gold mt-4 hover:underline">Vezi toată luna</button>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
function schedulePage() {
    return {
        currentMonth: <?= $curMonth ?>,
        currentYear: <?= $curYear ?>,
        selectedDate: null,
        monthNames: ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'],
        weekDays: ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'],
        events: <?= json_encode($jsEvents, JSON_UNESCAPED_UNICODE) ?>,
        get calendarDays() {
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            const startPadding = (firstDay.getDay() + 6) % 7;
            const days = [];
            const prevMonth = new Date(this.currentYear, this.currentMonth, 0);
            for (let i = startPadding - 1; i >= 0; i--) days.push({ day: prevMonth.getDate() - i, date: null, hasEvent: false, isOtherMonth: true });
            for (let i = 1; i <= lastDay.getDate(); i++) {
                const dateStr = `${this.currentYear}-${String(this.currentMonth + 1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
                days.push({ day: i, date: dateStr, hasEvent: this.events.some(e => e.date === dateStr), isOtherMonth: false });
            }
            const remaining = 42 - days.length;
            for (let i = 1; i <= remaining; i++) days.push({ day: i, date: null, hasEvent: false, isOtherMonth: true });
            return days;
        },
        get filteredEvents() {
            if (this.selectedDate) return this.events.filter(e => e.date === this.selectedDate);
            return this.events.filter(e => { const d = new Date(e.date); return d.getMonth() === this.currentMonth && d.getFullYear() === this.currentYear; });
        },
        prevMonth() { if (this.currentMonth === 0) { this.currentMonth = 11; this.currentYear--; } else this.currentMonth--; this.selectedDate = null; },
        nextMonth() { if (this.currentMonth === 11) { this.currentMonth = 0; this.currentYear++; } else this.currentMonth++; this.selectedDate = null; },
        selectDate(date) { if (date) this.selectedDate = this.selectedDate === date ? null : date; },
        formatSelectedDate() { if (!this.selectedDate) return ''; const d = new Date(this.selectedDate); return this.weekDays[d.getDay()] + ', ' + d.getDate() + ' ' + this.monthNames[d.getMonth()]; }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
