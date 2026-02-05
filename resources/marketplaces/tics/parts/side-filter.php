<?php
/**
 * TICS.ro - Side Filter Component
 * Desktop sidebar and mobile drawer filters
 *
 * Variables:
 * - $filterCategory, $filterCity, $filterDate, $filterPrice, $filterSort (from parent page)
 * - $FEATURED_CITIES, $DATE_FILTERS, $PRICE_FILTERS (from config)
 */
?>

<!-- Sidebar Filters - Desktop -->
<aside class="hidden lg:block w-72 flex-shrink-0">
    <div class="sticky top-40 bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Filtre</h2>
                <button onclick="TicsEventsPage.clearFilters()" class="text-sm text-indigo-600 font-medium hover:underline">Resetează</button>
            </div>
        </div>

        <div class="p-5 max-h-[calc(100vh-220px)] overflow-y-auto">
            <!-- AI Toggle -->
            <div class="pb-5 mb-5 border-b border-gray-100">
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 text-sm">AI Suggestions</p>
                            <p class="text-xs text-gray-500">Recomandări personalizate</p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked id="aiToggle">
                        <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                    </label>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="pb-5 mb-5 border-b border-gray-100">
                <h3 class="font-medium text-gray-900 mb-3 text-sm">Când</h3>
                <div class="space-y-2.5">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-date="today" <?= ($filterDate ?? '') === 'today' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Astăzi</span>
                        <span class="ml-auto text-xs text-gray-400" id="countToday">-</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-date="tomorrow" <?= ($filterDate ?? '') === 'tomorrow' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Mâine</span>
                        <span class="ml-auto text-xs text-gray-400" id="countTomorrow">-</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-date="weekend" <?= ($filterDate ?? '') === 'weekend' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Weekendul acesta</span>
                        <span class="ml-auto text-xs text-gray-400" id="countWeekend">-</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-date="week" <?= ($filterDate ?? '') === 'week' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Săptămâna viitoare</span>
                        <span class="ml-auto text-xs text-gray-400" id="countWeek">-</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-date="month" <?= ($filterDate ?? '') === 'month' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Luna aceasta</span>
                        <span class="ml-auto text-xs text-gray-400" id="countMonth">-</span>
                    </label>
                </div>
                <button class="mt-3 text-sm text-indigo-600 font-medium hover:underline flex items-center gap-1" onclick="openDatePicker()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Alege interval
                </button>
            </div>

            <!-- Price Filter -->
            <div class="pb-5 mb-5 border-b border-gray-100">
                <h3 class="font-medium text-gray-900 mb-3 text-sm">Preț</h3>
                <input type="range" id="priceRange" min="0" max="1000" value="<?= ($filterPrice ?? '') === '500+' ? '1000' : '500' ?>" class="w-full mb-2" oninput="updatePriceLabel(this.value)">
                <div class="flex justify-between text-xs text-gray-500 mb-3">
                    <span>0 RON</span>
                    <span class="font-medium text-gray-900" id="priceLabel">până la 500 RON</span>
                    <span>1000+</span>
                </div>
                <div class="flex gap-2">
                    <button onclick="setQuickPrice('free')" class="flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Gratuit</button>
                    <button onclick="setQuickPrice('0-100')" class="flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">&lt;100</button>
                    <button onclick="setQuickPrice('100-300')" class="flex-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">&lt;300</button>
                </div>
            </div>

            <!-- Location Filter -->
            <div class="pb-5 mb-5 border-b border-gray-100">
                <h3 class="font-medium text-gray-900 mb-3 text-sm">Oraș</h3>
                <div class="relative mb-3">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    </svg>
                    <input type="text" id="citySearch" placeholder="Caută oraș..." class="w-full pl-9 pr-3 py-2 text-sm bg-gray-100 border-0 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900">
                </div>
                <div class="space-y-2.5" id="cityList">
                    <?php foreach ($FEATURED_CITIES as $city): ?>
                    <label class="flex items-center gap-3 cursor-pointer group city-option" data-city="<?= e($city['slug']) ?>">
                        <input type="checkbox" class="cb city-cb" value="<?= e($city['slug']) ?>" <?= ($filterCity ?? '') === $city['slug'] ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900"><?= e($city['name']) ?></span>
                        <span class="ml-auto text-xs text-gray-400"><?= $city['count'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button class="mt-3 text-sm text-indigo-600 font-medium hover:underline" onclick="showAllCities()">Vezi toate (<?= count($FEATURED_CITIES) ?> orașe)</button>
            </div>

            <!-- Features Filter -->
            <div class="pb-5 mb-5 border-b border-gray-100">
                <h3 class="font-medium text-gray-900 mb-3 text-sm">Caracteristici</h3>
                <div class="space-y-2.5">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-feature="sold_out_soon">
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Sold out aproape</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-feature="early_bird">
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Early Bird activ</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-feature="vip">
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">VIP disponibil</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" class="cb" data-feature="accessible">
                        <span class="text-sm text-gray-600 group-hover:text-gray-900">Acces persoane cu dizabilități</span>
                    </label>
                </div>
            </div>

            <!-- AI Match Filter -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <h3 class="font-medium text-gray-900 text-sm">AI Match minim</h3>
                    <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                </div>
                <div class="flex gap-2">
                    <button data-ai-match="0" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Orice</button>
                    <button data-ai-match="80" class="flex-1 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg">80%+</button>
                    <button data-ai-match="90" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">90%+</button>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Filter Drawer -->
<div id="filterOverlay" class="overlay fixed inset-0 bg-black/50 z-50 lg:hidden"></div>
<div id="filterDrawer" class="drawer fixed top-0 left-0 bottom-0 w-80 max-w-[85vw] bg-white z-50 overflow-y-auto lg:hidden">
    <div class="sticky top-0 bg-white border-b border-gray-200 px-5 py-4 flex items-center justify-between">
        <h2 class="font-semibold text-lg">Filtre</h2>
        <button onclick="closeFiltersDrawer()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-5">
        <!-- Mobile AI Toggle -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="font-medium text-sm">AI Suggestions</span>
                </div>
                <label class="relative inline-flex cursor-pointer">
                    <input type="checkbox" class="sr-only peer" checked id="aiToggleMobile">
                    <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>
        </div>

        <!-- Mobile Date Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Când</h3>
            <div class="space-y-2.5">
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="today"><span class="text-sm text-gray-600">Astăzi</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="tomorrow"><span class="text-sm text-gray-600">Mâine</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="weekend" checked><span class="text-sm text-gray-600">Weekend</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" class="cb" data-date="month"><span class="text-sm text-gray-600">Luna aceasta</span></label>
            </div>
        </div>

        <!-- Mobile Price Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Preț maxim</h3>
            <input type="range" id="priceRangeMobile" min="0" max="1000" value="500" class="w-full mb-2">
            <div class="flex justify-between text-xs text-gray-500">
                <span>0</span><span class="font-medium text-gray-900" id="priceLabelMobile">500 RON</span><span>1000+</span>
            </div>
        </div>

        <!-- Mobile City Filter -->
        <div class="pb-5 mb-5 border-b border-gray-100">
            <h3 class="font-medium text-sm mb-3">Oraș</h3>
            <select id="cityFilterMobile" class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl">
                <option value="">Toate orașele</option>
                <?php foreach ($FEATURED_CITIES as $city): ?>
                <option value="<?= e($city['slug']) ?>" <?= ($filterCity ?? '') === $city['slug'] ? 'selected' : '' ?>><?= e($city['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Mobile AI Match -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <h3 class="font-medium text-sm">AI Match minim</h3>
                <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
            </div>
            <div class="flex gap-2">
                <button data-ai-match="0" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">Orice</button>
                <button data-ai-match="80" class="flex-1 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg">80%+</button>
                <button data-ai-match="90" class="flex-1 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg">90%+</button>
            </div>
        </div>
    </div>

    <div class="sticky bottom-0 bg-white border-t border-gray-200 p-5 flex gap-3">
        <button onclick="TicsEventsPage.clearFilters(); closeFiltersDrawer();" class="flex-1 py-3 border border-gray-200 rounded-xl font-medium hover:bg-gray-50 transition-colors">Resetează</button>
        <button onclick="TicsEventsPage.applyFilters(); closeFiltersDrawer();" class="flex-1 py-3 bg-gray-900 text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">Aplică (<span id="filterResultCount">324</span>)</button>
    </div>
</div>
