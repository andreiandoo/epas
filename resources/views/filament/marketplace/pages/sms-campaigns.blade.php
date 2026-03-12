<x-filament-panels::page>
    @if ($activeView === 'list')
        {{-- Campaign List --}}
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-white">Campaniile tale SMS</h2>
                <x-filament::button wire:click="switchToCreate" icon="heroicon-o-plus">
                    Campanie nouă
                </x-filament::button>
            </div>

            @if ($campaigns->isEmpty())
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-8 text-center">
                    <div class="text-gray-400 mb-2">
                        <x-heroicon-o-megaphone class="w-12 h-12 mx-auto" />
                    </div>
                    <p class="text-gray-500 dark:text-gray-400">Nu ai nicio campanie SMS încă.</p>
                    <p class="text-sm text-gray-400 mt-1">Creează prima campanie pentru a trimite SMS-uri promoționale.</p>
                </div>
            @else
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Nume</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Audiență</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Trimise</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Cost</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Programat</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Creat</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">Acțiuni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($campaigns as $campaign)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $campaign->name }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusColors = [
                                                'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                                'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
                                                'sending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
                                                'sent' => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
                                                'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
                                                'cancelled' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                                            ];
                                            $statusLabels = [
                                                'draft' => 'Ciornă',
                                                'scheduled' => 'Programat',
                                                'sending' => 'Se trimite...',
                                                'sent' => 'Trimis',
                                                'failed' => 'Eșuat',
                                                'cancelled' => 'Anulat',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$campaign->status] ?? $statusColors['draft'] }}">
                                            {{ $statusLabels[$campaign->status] ?? $campaign->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                        {{ $campaign->audience_with_phone ?? 0 }} / {{ $campaign->total_audience ?? 0 }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                        @if ($campaign->status === 'sent')
                                            <span class="text-green-600 dark:text-green-400">{{ $campaign->sms_sent ?? 0 }}</span>
                                            @if ($campaign->sms_failed > 0)
                                                / <span class="text-red-500">{{ $campaign->sms_failed }} eșuate</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                        @if ($campaign->total_cost > 0)
                                            {{ number_format($campaign->total_cost, 2) }} €
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $campaign->scheduled_at ? $campaign->scheduled_at->format('d.m.Y H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $campaign->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($campaign->status === 'draft')
                                            <div class="flex items-center justify-center gap-2">
                                                <button wire:click="editCampaign({{ $campaign->id }})" class="text-primary-500 hover:text-primary-400 text-xs font-medium">
                                                    Editează
                                                </button>
                                                <button wire:click="cancelCampaign({{ $campaign->id }})" class="text-red-500 hover:text-red-400 text-xs font-medium" onclick="return confirm('Sigur anulezi această campanie?')">
                                                    Anulează
                                                </button>
                                            </div>
                                        @elseif ($campaign->status === 'scheduled')
                                            <button wire:click="cancelCampaign({{ $campaign->id }})" class="text-red-500 hover:text-red-400 text-xs font-medium" onclick="return confirm('Sigur anulezi această campanie programată?')">
                                                Anulează
                                            </button>
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @else
        {{-- Create / Edit Campaign --}}
        <div x-data="{
            msg: @entangle('messageText'),
            get charCount() { return this.msg.length },
            get smsCount() {
                if (this.charCount === 0) return 0;
                if (this.charCount <= 160) return 1;
                return Math.ceil(this.charCount / 153);
            },
            get isOverSingle() { return this.charCount > 160 },
        }" class="space-y-6">

            <div class="flex items-center gap-4">
                <button wire:click="switchToList" class="text-gray-400 hover:text-white transition">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                </button>
                <h2 class="text-lg font-semibold text-white">
                    {{ $editingCampaignId ? 'Editează campania' : 'Campanie SMS nouă' }}
                </h2>
            </div>

            {{-- Campaign Name --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-4">Detalii campanie</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Nume campanie *</label>
                        <input type="text" wire:model="campaignName" class="fi-input block w-full rounded-lg border-gray-300 bg-white/5 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600" placeholder="ex: Promo festival vara 2026">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Mesaj SMS *</label>
                        <textarea x-model="msg" rows="4" class="fi-input block w-full rounded-lg border-gray-300 bg-white/5 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600" placeholder="Scrie mesajul SMS..."></textarea>

                        <div class="mt-2 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-400">
                                    <span x-text="charCount" :class="isOverSingle ? 'text-yellow-400 font-semibold' : ''">0</span> caractere
                                </span>
                                <span class="text-gray-400">
                                    <span x-text="smsCount" :class="smsCount > 1 ? 'text-yellow-400 font-semibold' : ''">0</span> SMS / destinatar
                                </span>
                            </div>
                            <div x-show="isOverSingle" x-cloak class="text-yellow-400 flex items-center gap-1">
                                <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                                <span>Mesaj lung → mai multe SMS-uri per destinatar (153 car/SMS)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Audience Filters --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-4">Filtre audiență</h3>
                <p class="text-xs text-gray-500 mb-4">Filtrele se aplică cumulativ (intersecție). Lasă gol pentru toți clienții activi.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Organizer --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Organizator</label>
                        <select wire:model.live="filterOrganizer" class="fi-input block w-full rounded-lg border-gray-300 bg-white/5 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600">
                            <option value="">— Toți organizatorii —</option>
                            @foreach ($organizerOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Event --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Eveniment</label>
                        <select wire:model="filterEvent" class="fi-input block w-full rounded-lg border-gray-300 bg-white/5 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600" {{ empty($filterOrganizer) ? 'disabled' : '' }}>
                            <option value="">— Toate evenimentele —</option>
                            @foreach ($eventOptions as $id => $title)
                                <option value="{{ $id }}">{{ $title }}</option>
                            @endforeach
                        </select>
                        @if (empty($filterOrganizer))
                            <p class="text-xs text-gray-500 mt-1">Selectează un organizator mai întâi</p>
                        @endif
                    </div>

                    {{-- Cities --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Orașe</label>
                        <div class="space-y-1 max-h-40 overflow-y-auto rounded-lg border border-gray-600 bg-white/5 p-2">
                            @forelse ($cityOptions as $id => $name)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white/5 cursor-pointer text-sm text-gray-300">
                                    <input type="checkbox" wire:model="filterCities" value="{{ $id }}" class="rounded border-gray-500 bg-white/10 text-primary-500 focus:ring-primary-500">
                                    {{ $name }}
                                </label>
                            @empty
                                <p class="text-xs text-gray-500 px-2 py-1">Niciun oraș disponibil</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Artists --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Artiști</label>
                        <div class="space-y-1 max-h-40 overflow-y-auto rounded-lg border border-gray-600 bg-white/5 p-2">
                            @forelse ($artistOptions as $id => $name)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white/5 cursor-pointer text-sm text-gray-300">
                                    <input type="checkbox" wire:model="filterArtists" value="{{ $id }}" class="rounded border-gray-500 bg-white/10 text-primary-500 focus:ring-primary-500">
                                    {{ $name }}
                                </label>
                            @empty
                                <p class="text-xs text-gray-500 px-2 py-1">Niciun artist disponibil</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Genres --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Genuri muzicale</label>
                        <div class="space-y-1 max-h-40 overflow-y-auto rounded-lg border border-gray-600 bg-white/5 p-2">
                            @forelse ($genreOptions as $id => $name)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white/5 cursor-pointer text-sm text-gray-300">
                                    <input type="checkbox" wire:model="filterGenres" value="{{ $id }}" class="rounded border-gray-500 bg-white/10 text-primary-500 focus:ring-primary-500">
                                    {{ $name }}
                                </label>
                            @empty
                                <p class="text-xs text-gray-500 px-2 py-1">Niciun gen disponibil</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Venues --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Locații</label>
                        <div class="space-y-1 max-h-40 overflow-y-auto rounded-lg border border-gray-600 bg-white/5 p-2">
                            @forelse ($venueOptions as $id => $name)
                                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white/5 cursor-pointer text-sm text-gray-300">
                                    <input type="checkbox" wire:model="filterVenues" value="{{ $id }}" class="rounded border-gray-500 bg-white/10 text-primary-500 focus:ring-primary-500">
                                    {{ $name }}
                                </label>
                            @empty
                                <p class="text-xs text-gray-500 px-2 py-1">Nicio locație disponibilă</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Calculate Audience Button --}}
                <div class="mt-4">
                    <x-filament::button wire:click="calculateAudience" color="gray" icon="heroicon-o-calculator" size="sm">
                        Calculează audiența
                    </x-filament::button>
                </div>
            </div>

            {{-- Audience Preview & Credit Check --}}
            @php
                $smsPerRecipient = $messageText ? \App\Models\SmsCampaign::calculateSmsCount($messageText) : 0;
                $totalSmsNeeded = $audienceWithPhone * $smsPerRecipient;
                $hasEnoughCredits = $promotionalCredits >= $totalSmsNeeded;
                $deficit = $totalSmsNeeded - $promotionalCredits;
                $costEur = $totalSmsNeeded * $promotionalPrice;
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Audience Stats --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                    <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">Audiență estimată</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Total clienți potriviți:</span>
                            <span class="font-semibold text-white">{{ number_format($totalAudience) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Cu nr. de telefon:</span>
                            <span class="font-semibold text-white">{{ number_format($audienceWithPhone) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">SMS / destinatar:</span>
                            <span class="font-semibold text-white" x-text="smsCount">{{ $smsPerRecipient }}</span>
                        </div>
                        <hr class="border-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Total SMS necesare:</span>
                            <span class="font-bold text-lg {{ $hasEnoughCredits ? 'text-green-400' : 'text-red-400' }}">
                                <span x-text="{{ $audienceWithPhone }} * smsCount">{{ $totalSmsNeeded }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Credits Check --}}
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                    <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">Credite promoționale</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Credite disponibile:</span>
                            <span class="font-semibold text-white">{{ number_format($promotionalCredits) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Credite necesare:</span>
                            <span class="font-semibold {{ $hasEnoughCredits ? 'text-white' : 'text-red-400' }}">
                                <span x-text="{{ $audienceWithPhone }} * smsCount">{{ $totalSmsNeeded }}</span>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Preț / SMS:</span>
                            <span class="text-white">
                                {{ number_format($promotionalPrice, 5) }} EUR
                                @if ($clientCurrency === 'RON' && $eurToRon)
                                    <span class="text-gray-500">({{ number_format($promotionalPrice * $eurToRon, 4) }} RON)</span>
                                @endif
                            </span>
                        </div>
                        <hr class="border-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Cost estimat:</span>
                            <span class="font-bold text-white">
                                <span x-text="({{ $audienceWithPhone }} * smsCount * {{ $promotionalPrice }}).toFixed(2)">{{ number_format($costEur, 2) }}</span> EUR
                                @if ($clientCurrency === 'RON' && $eurToRon)
                                    <span class="text-gray-500">
                                        (<span x-text="({{ $audienceWithPhone }} * smsCount * {{ $promotionalPrice }} * {{ $eurToRon }}).toFixed(2)">{{ number_format($costEur * $eurToRon, 2) }}</span> RON)
                                    </span>
                                @endif
                            </span>
                        </div>

                        @if (!$hasEnoughCredits && $audienceWithPhone > 0 && $smsPerRecipient > 0)
                            <div class="mt-3 p-3 rounded-lg bg-red-900/30 border border-red-700/50">
                                <div class="flex items-start gap-2">
                                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" />
                                    <div class="text-sm">
                                        <p class="text-red-300 font-medium">Credite insuficiente!</p>
                                        <p class="text-red-400 mt-1">
                                            Îți lipsesc <strong>{{ number_format($deficit) }}</strong> credite.
                                            Poți trimite maxim <strong>{{ number_format(floor($promotionalCredits / max($smsPerRecipient, 1))) }}</strong> destinatari cu creditele actuale.
                                        </p>
                                        <a href="{{ url('/marketplace/sms-notifications') }}" class="inline-flex items-center gap-1 mt-2 text-primary-400 hover:text-primary-300 font-medium">
                                            <x-heroicon-o-shopping-cart class="w-4 h-4" />
                                            Cumpără credite
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Schedule --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-4">Programare</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Data și ora trimiterii (opțional)</label>
                    <input type="datetime-local" wire:model="scheduledAt" class="fi-input block w-full max-w-xs rounded-lg border-gray-300 bg-white/5 text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600">
                    <p class="text-xs text-gray-500 mt-1">Lasă gol dacă vrei să trimiți manual sau să salvezi ca ciornă.</p>
                </div>
            </div>

            {{-- Actions --}}
            @php
                $sendDisabled = !$hasEnoughCredits && $audienceWithPhone > 0 && $smsPerRecipient > 0;
            @endphp
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="saveDraft" color="gray" icon="heroicon-o-document">
                    Salvează ciornă
                </x-filament::button>

                @if ($scheduledAt)
                    <x-filament::button wire:click="saveAndSchedule" color="warning" icon="heroicon-o-clock">
                        Programează trimiterea
                    </x-filament::button>
                @endif

                <button
                    wire:click="sendNow"
                    wire:confirm="Ești sigur că vrei să trimiți {{ $audienceWithPhone }} SMS-uri ACUM?"
                    class="fi-btn fi-btn-size-md gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold inline-flex items-center justify-center transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $sendDisabled ? 'bg-gray-400 cursor-not-allowed opacity-50' : 'bg-success-600 hover:bg-success-500 text-white focus:ring-success-500' }}"
                    {{ $sendDisabled ? 'disabled' : '' }}
                >
                    <x-heroicon-o-paper-airplane class="w-5 h-5" />
                    Trimite acum
                </button>

                <x-filament::button wire:click="switchToList" color="gray" outlined icon="heroicon-o-x-mark">
                    Anulează
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
