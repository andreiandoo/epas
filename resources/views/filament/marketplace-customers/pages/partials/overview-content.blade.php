
            {{-- Profile Narrative --}}
            @if(!empty($profileNarrative))
                <div class="p-4 mb-6 rounded-xl bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 dark:from-indigo-950/40 dark:via-purple-950/40 dark:to-pink-950/40 ring-1 ring-indigo-200/60 dark:ring-indigo-700/40">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                            <x-filament::icon icon="heroicon-o-user-circle" class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-indigo-700 dark:text-indigo-300">Profil Client Generat</h3>
                            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $profileNarrative }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-6 lg:grid-cols-2">

                {{-- LEFT COLUMN: Stat Cards (2-col grid) --}}
                <div class="space-y-6">
                    <x-filament::section>
                        <div class="grid grid-cols-3 gap-3">
                            @php
                                $topGenre = !empty($artistGenres) ? $artistGenres[0]['label'] : '-';
                                $top3Artists = !empty($topArtists) ? collect($topArtists)->take(3)->pluck('name')->implode(', ') : '-';
                                $topEventGenre = !empty($eventGenres) ? $eventGenres[0]['label'] : '-';
                                $topCity = !empty($preferredCities) ? $preferredCities[0]['label'] : '-';
                                $topDay = !empty($preferredDays) ? $preferredDays[0]['label'] : '-';
                                $topMonth = !empty($preferredMonths) ? $preferredMonths[0]['label'] : '-';
                            @endphp

                            @foreach([
                                ['Lifetime Value (Plătit)', number_format($lifetimeStats['lifetime_value'] ?? 0, 2) . ' RON', 'heroicon-o-banknotes', 'text-green-600 dark:text-green-400'],
                                ['Total Comenzi (Toate)', number_format($lifetimeStats['all_orders_value'] ?? $lifetimeStats['lifetime_value'] ?? 0, 2) . ' RON', 'heroicon-o-banknotes', 'text-gray-600 dark:text-gray-400'],
                                ['Client din', ($lifetimeStats['customer_since'] ?? 'N/A') . ' (' . number_format($lifetimeStats['lifetime_days'] ?? 0) . ' zile)', 'heroicon-o-calendar', 'text-blue-600 dark:text-blue-400'],
                                ['Comenzi', number_format($lifetimeStats['total_orders'] ?? 0), 'heroicon-o-shopping-cart', 'text-amber-600 dark:text-amber-400'],
                                ['Bilete', number_format($lifetimeStats['total_tickets'] ?? 0), 'heroicon-o-ticket', 'text-cyan-600 dark:text-cyan-400'],
                                ['Evenimente', number_format($lifetimeStats['total_events'] ?? 0), 'heroicon-o-calendar-days', 'text-rose-600 dark:text-rose-400'],
                                ['Top Gen Muzical', $topGenre, 'heroicon-o-musical-note', 'text-violet-600 dark:text-violet-400'],
                                ['Top 3 Artiști', $top3Artists, 'heroicon-o-microphone', 'text-fuchsia-600 dark:text-fuchsia-400'],
                                ['Top Gen Eveniment', $topEventGenre, 'heroicon-o-sparkles', 'text-indigo-600 dark:text-indigo-400'],
                                ['Oraș Preferat', $topCity, 'heroicon-o-map-pin', 'text-emerald-600 dark:text-emerald-400'],
                                ['Zi Preferată', $topDay, 'heroicon-o-clock', 'text-orange-600 dark:text-orange-400'],
                                ['Lună Preferată', $topMonth, 'heroicon-o-sun', 'text-yellow-600 dark:text-yellow-400'],
                                ['Medie / Comandă', number_format($orderStatusBreakdown['avg_per_order'] ?? 0, 2) . ' RON', 'heroicon-o-calculator', 'text-teal-600 dark:text-teal-400'],
                                ['Medie / Bilet', number_format($orderStatusBreakdown['avg_per_ticket'] ?? 0, 2) . ' RON', 'heroicon-o-tag', 'text-sky-600 dark:text-sky-400'],
                                ['Pending', number_format($orderStatusBreakdown['pending_value'] ?? 0, 2) . ' RON', 'heroicon-o-clock', 'text-yellow-600 dark:text-yellow-400'],
                                ['Anulate', number_format($orderStatusBreakdown['cancelled_value'] ?? 0, 2) . ' RON', 'heroicon-o-x-circle', 'text-red-600 dark:text-red-400'],
                                ['Failed / Expirate', number_format($orderStatusBreakdown['failed_value'] ?? 0, 2) . ' RON', 'heroicon-o-exclamation-triangle', 'text-red-500 dark:text-red-400'],
                                ['Rambursări', number_format($orderStatusBreakdown['refund_value'] ?? 0, 2) . ' RON', 'heroicon-o-arrow-uturn-left', 'text-gray-600 dark:text-gray-400'],
                            ] as [$label, $value, $icon, $color])
                                <div class="p-3 bg-white shadow-sm rounded-xl dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="text-[10px] font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</span>
                                        <x-filament::icon :icon="$icon" class="w-4 h-4 {{ $color }}" />
                                    </div>
                                    <div class="text-sm font-bold {{ $color }} truncate" title="{{ $value }}">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>

                    {{-- Price Range --}}
                    @if(($priceRange['max'] ?? 0) > 0)
                        <x-filament::section>
                            <x-slot name="heading">Range de Preț (per bilet)</x-slot>
                            <div class="grid grid-cols-4 gap-3">
                                @foreach([
                                    ['Min', $priceRange['min'], 'text-blue-600'],
                                    ['Max', $priceRange['max'], 'text-red-600'],
                                    ['Medie', $priceRange['avg'], 'text-green-600'],
                                    ['Median', $priceRange['median'], 'text-purple-600'],
                                ] as [$prLabel, $prValue, $prColor])
                                    <div class="text-center">
                                        <span class="text-[10px] text-gray-500 uppercase tracking-wide">{{ $prLabel }}</span>
                                        <div class="text-sm font-bold {{ $prColor }}">{{ number_format($prValue, 2) }} RON</div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif

                    
                </div>

                {{-- RIGHT COLUMN: Personal Info --}}
                <div>
                    <x-filament::section>
                        <div class="divide-y divide-gray-100">
                            @foreach([
                                'Nume' => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: '-',
                                'Email' => $record->email ?? '-',
                                'Telefon' => $record->phone ?? '-',
                                'Gen' => match($record->gender) { 'male' => 'Masculin', 'female' => 'Feminin', 'other' => 'Altul', default => '-' },
                                'Data nașterii' => $record->birth_date?->format('d.m.Y') ?? '-',
                                'Oraș' => $record->city ?? '-',
                                'Țară' => $record->country ?? '-',
                                'Adresă' => $record->address ?? '-',
                                'Limbă' => match($record->locale) { 'ro' => 'Română', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', default => $record->locale ?? '-' },
                                'Status' => $record->status ?? '-',
                                'Email verificat' => $emailVerifiedDisplay ?? 'Nu',
                                'Accepts Marketing' => $acceptsMarketingDisplay ? 'Da' : 'Nu',
                                'Ultimul login' => $record->last_login_at?->format('d.m.Y H:i') ?? 'Niciodată',
                                'Creat la' => $record->created_at?->format('d.m.Y H:i') ?? '-',
                                'Actualizat la' => $record->updated_at?->format('d.m.Y H:i') ?? '-',
                            ] as $fieldLabel => $fieldValue)
                                @php $isEmpty = in_array($fieldValue, ['-', 'Nu', 'Niciodată']); @endphp
                                <div class="flex justify-between px-1 py-2">
                                    <span class="text-sm font-medium {{ $isEmpty ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $fieldLabel }}</span>
                                    <span class="text-sm {{ $isEmpty ? 'text-red-400 dark:text-red-500' : 'text-gray-900 dark:text-gray-100' }}">{{ $fieldValue }}</span>
                                </div>
                            @endforeach

                            {{-- Notification Preferences --}}
                            @if(!empty($notificationPreferences))
                                <div class="px-1 pt-3 pb-1">
                                    <span class="text-xs font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Preferințe Notificări</span>
                                </div>
                                @foreach($notificationPreferences as $prefLabel => $prefValue)
                                    <div class="flex justify-between px-1 py-2">
                                        <span class="text-sm font-medium {{ $prefValue ? 'text-gray-500 dark:text-gray-400' : 'text-red-500 dark:text-red-400' }}">{{ $prefLabel }}</span>
                                        <span class="text-sm font-medium {{ $prefValue ? 'text-green-600 dark:text-green-400' : 'text-red-400 dark:text-red-500' }}">{{ $prefValue ? 'Da' : 'Nu' }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </x-filament::section>

                    {{-- Beneficiaries / Attendees --}}
                    @if(!empty($attendees))
                        <x-filament::section>
                            <x-slot name="heading">Beneficiari ({{ count($attendees) }})</x-slot>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Nume</th>
                                            <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($attendees as $att)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $att->attendee_name }}</td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $att->attendee_email ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-filament::section>
                    @endif
                </div>
            </div>

            {{-- Orders by Month Chart --}}
            @if(!empty($monthlyChart['labels']))
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Comenzi pe luni - {{ $monthlyChart['year'] }}</x-slot>
                        <div style="position:relative; height:280px;">
                            <canvas id="mpProfileMonthlyChart"></canvas>
                        </div>
                    </x-filament::section>
                </div>
            @endif
        </div>
