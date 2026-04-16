<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Customer Campaign --}}
        <div class="p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-danger-50 dark:bg-danger-500/10">
                    <x-heroicon-o-users class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Clienți</h3>
                    <p class="text-sm text-gray-500">{{ number_format($customerCount) }} conturi cu parolă</p>
                </div>
            </div>

            @if($customerCampaign)
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                        <span @class([
                            'px-2 py-0.5 rounded-full text-xs font-semibold',
                            'bg-blue-100 text-blue-700' => $customerCampaign->status === 'sending',
                            'bg-yellow-100 text-yellow-700' => $customerCampaign->status === 'paused',
                            'bg-green-100 text-green-700' => $customerCampaign->status === 'completed',
                            'bg-red-100 text-red-700' => $customerCampaign->status === 'failed',
                            'bg-gray-100 text-gray-700' => $customerCampaign->status === 'draft',
                        ])>
                            {{ match($customerCampaign->status) {
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
                        <span class="font-mono font-semibold">{{ number_format($customerCampaign->sent_count) }} / {{ number_format($customerCampaign->total_recipients) }}</span>
                    </div>

                    @if($customerCampaign->failed_count > 0)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-red-500">Eșuate:</span>
                        <span class="font-mono font-semibold text-red-600">{{ number_format($customerCampaign->failed_count) }}</span>
                    </div>
                    @endif

                    {{-- Progress bar --}}
                    @php
                        $total = max($customerCampaign->total_recipients, 1);
                        $progress = round(($customerCampaign->sent_count + $customerCampaign->failed_count) / $total * 100, 1);
                    @endphp
                    <div class="w-full h-2 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-700">
                        <div class="h-full transition-all duration-500 rounded-full bg-danger-500" style="width: {{ $progress }}%"></div>
                    </div>
                    <p class="text-xs text-right text-gray-400">{{ $progress }}%</p>

                    @if($customerCampaign->started_at)
                    <p class="text-xs text-gray-400">Pornit: {{ \Carbon\Carbon::parse($customerCampaign->started_at)->format('d M Y H:i') }}</p>
                    @endif
                    @if($customerCampaign->completed_at)
                    <p class="text-xs text-gray-400">Finalizat: {{ \Carbon\Carbon::parse($customerCampaign->completed_at)->format('d M Y H:i') }}</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500">Nicio campanie pornită încă.</p>
            @endif
        </div>

        {{-- Organizer Campaign --}}
        <div class="p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-info-50 dark:bg-info-500/10">
                    <x-heroicon-o-building-office class="w-5 h-5 text-info-600 dark:text-info-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Organizatori</h3>
                    <p class="text-sm text-gray-500">{{ number_format($organizerCount) }} conturi cu parolă</p>
                </div>
            </div>

            @if($organizerCampaign)
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                        <span @class([
                            'px-2 py-0.5 rounded-full text-xs font-semibold',
                            'bg-blue-100 text-blue-700' => $organizerCampaign->status === 'sending',
                            'bg-yellow-100 text-yellow-700' => $organizerCampaign->status === 'paused',
                            'bg-green-100 text-green-700' => $organizerCampaign->status === 'completed',
                            'bg-red-100 text-red-700' => $organizerCampaign->status === 'failed',
                            'bg-gray-100 text-gray-700' => $organizerCampaign->status === 'draft',
                        ])>
                            {{ match($organizerCampaign->status) {
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
                        <span class="font-mono font-semibold">{{ number_format($organizerCampaign->sent_count) }} / {{ number_format($organizerCampaign->total_recipients) }}</span>
                    </div>

                    @if($organizerCampaign->failed_count > 0)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-red-500">Eșuate:</span>
                        <span class="font-mono font-semibold text-red-600">{{ number_format($organizerCampaign->failed_count) }}</span>
                    </div>
                    @endif

                    @php
                        $total = max($organizerCampaign->total_recipients, 1);
                        $progress = round(($organizerCampaign->sent_count + $organizerCampaign->failed_count) / $total * 100, 1);
                    @endphp
                    <div class="w-full h-2 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-700">
                        <div class="h-full transition-all duration-500 rounded-full bg-info-500" style="width: {{ $progress }}%"></div>
                    </div>
                    <p class="text-xs text-right text-gray-400">{{ $progress }}%</p>

                    @if($organizerCampaign->started_at)
                    <p class="text-xs text-gray-400">Pornit: {{ \Carbon\Carbon::parse($organizerCampaign->started_at)->format('d M Y H:i') }}</p>
                    @endif
                    @if($organizerCampaign->completed_at)
                    <p class="text-xs text-gray-400">Finalizat: {{ \Carbon\Carbon::parse($organizerCampaign->completed_at)->format('d M Y H:i') }}</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500">Nicio campanie pornită încă.</p>
            @endif
        </div>
    </div>

    {{-- Template links --}}
    <div class="p-4 mt-6 text-sm bg-gray-50 rounded-xl dark:bg-gray-800">
        <p class="font-medium text-gray-700 dark:text-gray-300">Template-uri email:</p>
        <div class="flex gap-4 mt-2">
            <a href="/marketplace/email-templates" class="text-primary-600 hover:underline">Editează template-urile de email →</a>
        </div>
        <p class="mt-2 text-xs text-gray-400">Slug-uri: <code>bulk_password_reset_customer</code> și <code>bulk_password_reset_organizer</code></p>
    </div>
</x-filament-panels::page>
