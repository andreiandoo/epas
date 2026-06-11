<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        @php
            $segments = [
                ['label' => 'Clienți (cu parolă)', 'desc' => 'Conturi înregistrate cu parolă setată', 'count' => $customerCount, 'campaign' => $customerCampaign, 'icon' => 'heroicon-o-users', 'color' => 'danger'],
                ['label' => 'Guests (fără parolă)', 'desc' => 'Au cumpărat bilete fără cont — primesc invitație să-și seteze parola', 'count' => $guestCount, 'campaign' => $guestCampaign, 'icon' => 'heroicon-o-user', 'color' => 'gray'],
                ['label' => 'Useri WordPress', 'desc' => 'Importați din WP cu hash phpass — primesc link de setare parolă nouă', 'count' => $wpUserCount, 'campaign' => $wpUserCampaign, 'icon' => 'heroicon-o-arrow-path', 'color' => 'warning'],
                ['label' => 'Organizatori', 'desc' => 'Conturi organizatori cu parolă', 'count' => $organizerCount, 'campaign' => $organizerCampaign, 'icon' => 'heroicon-o-building-office', 'color' => 'info'],
            ];
        @endphp

        @foreach($segments as $seg)
        <div class="p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div @class([
                    'flex items-center justify-center w-10 h-10 rounded-lg',
                    'bg-danger-50 dark:bg-danger-500/10' => $seg['color'] === 'danger',
                    'bg-gray-100 dark:bg-gray-700' => $seg['color'] === 'gray',
                    'bg-warning-50 dark:bg-warning-500/10' => $seg['color'] === 'warning',
                    'bg-info-50 dark:bg-info-500/10' => $seg['color'] === 'info',
                ])>
                    <x-dynamic-component :component="$seg['icon']" @class([
                        'w-5 h-5',
                        'text-danger-600 dark:text-danger-400' => $seg['color'] === 'danger',
                        'text-gray-500 dark:text-gray-400' => $seg['color'] === 'gray',
                        'text-warning-600 dark:text-warning-400' => $seg['color'] === 'warning',
                        'text-info-600 dark:text-info-400' => $seg['color'] === 'info',
                    ]) />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $seg['label'] }}</h3>
                    <p class="text-sm text-gray-500">{{ number_format($seg['count']) }} conturi</p>
                </div>
            </div>

            <p class="mb-3 text-xs text-gray-400">{{ $seg['desc'] }}</p>

            @if($seg['campaign'])
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                        <span @class([
                            'px-2 py-0.5 rounded-full text-xs font-semibold',
                            'bg-blue-100 text-blue-700' => $seg['campaign']->status === 'sending',
                            'bg-yellow-100 text-yellow-700' => $seg['campaign']->status === 'paused',
                            'bg-green-100 text-green-700' => $seg['campaign']->status === 'completed',
                            'bg-red-100 text-red-700' => $seg['campaign']->status === 'failed',
                            'bg-gray-100 text-gray-700' => $seg['campaign']->status === 'draft',
                        ])>
                            {{ match($seg['campaign']->status) {
                                'sending' => 'Se trimite...',
                                'paused' => 'Pauză',
                                'completed' => 'Finalizat',
                                'failed' => 'Eșuat',
                                default => 'Ciornă',
                            } }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Trimise:</span>
                        <span class="font-mono font-semibold">{{ number_format($seg['campaign']->sent_count) }} / {{ number_format($seg['campaign']->total_recipients) }}</span>
                    </div>

                    @if($seg['campaign']->failed_count > 0)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-red-500">Eșuate:</span>
                        <span class="font-mono font-semibold text-red-600">{{ number_format($seg['campaign']->failed_count) }}</span>
                    </div>
                    @endif

                    @php
                        $total = max($seg['campaign']->total_recipients, 1);
                        $progress = round(($seg['campaign']->sent_count + $seg['campaign']->failed_count) / $total * 100, 1);
                    @endphp
                    <div class="w-full h-2 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-700">
                        <div @class([
                            'h-full transition-all duration-500 rounded-full',
                            'bg-danger-500' => $seg['color'] === 'danger',
                            'bg-gray-500' => $seg['color'] === 'gray',
                            'bg-warning-500' => $seg['color'] === 'warning',
                            'bg-info-500' => $seg['color'] === 'info',
                        ]) style="width: {{ $progress }}%"></div>
                    </div>
                    <p class="text-xs text-right text-gray-400">{{ $progress }}%</p>

                    @if($seg['campaign']->started_at)
                    <p class="text-xs text-gray-400">Pornit: {{ \Carbon\Carbon::parse($seg['campaign']->started_at)->format('d M Y H:i') }}</p>
                    @endif
                    @if($seg['campaign']->completed_at)
                    <p class="text-xs text-gray-400">Finalizat: {{ \Carbon\Carbon::parse($seg['campaign']->completed_at)->format('d M Y H:i') }}</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500">Nicio campanie pornită încă.</p>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Template links --}}
    <div class="p-4 mt-6 text-sm bg-gray-50 rounded-xl dark:bg-gray-800">
        <p class="font-medium text-gray-700 dark:text-gray-300">Template-uri email:</p>
        <div class="flex gap-4 mt-2">
            <a href="/marketplace/email-templates" class="text-primary-600 hover:underline">Editează template-urile de email →</a>
        </div>
        <p class="mt-2 text-xs text-gray-400">Toate segmentele de clienți folosesc template-ul <code>bulk_password_reset_customer</code>. Organizatorii: <code>bulk_password_reset_organizer</code>.</p>
    </div>
</x-filament-panels::page>
