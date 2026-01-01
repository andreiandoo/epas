<div class="checkout-upsell-widget">
    @if($showVipUpgrade || $showMerchandise || $showParking)
        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/10 dark:to-orange-900/10">
            <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                Enhance Your Experience
            </h3>

            <div class="space-y-3">
                {{-- VIP Upgrade --}}
                @if($showVipUpgrade)
                    <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-primary-500 transition-colors group">
                        <input type="checkbox" name="upsell_vip" value="1"
                               class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">
                                    VIP Upgrade
                                </span>
                                <span class="text-sm font-semibold text-primary-600">+49.00 RON</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Priority entry, exclusive lounge access, complimentary drink
                            </p>
                            @if($upgradeScore > 0.6)
                                <span class="inline-flex items-center gap-1 mt-2 text-xs text-amber-600 dark:text-amber-400">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd" />
                                    </svg>
                                    Popular with fans like you
                                </span>
                            @endif
                        </div>
                    </label>
                @endif

                {{-- Merchandise --}}
                @if($showMerchandise)
                    <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-primary-500 transition-colors group">
                        <input type="checkbox" name="upsell_merch" value="1"
                               class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">
                                    Official Merchandise Pack
                                </span>
                                <span class="text-sm font-semibold text-primary-600">+79.00 RON</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                T-shirt + poster bundle, pick up at venue
                            </p>
                        </div>
                    </label>
                @endif

                {{-- Parking --}}
                @if($showParking)
                    <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-primary-500 transition-colors group">
                        <input type="checkbox" name="upsell_parking" value="1"
                               class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">
                                    Reserved Parking
                                </span>
                                <span class="text-sm font-semibold text-primary-600">+25.00 RON</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Guaranteed parking spot near the venue entrance
                            </p>
                        </div>
                    </label>
                @endif
            </div>
        </div>
    @endif
</div>
