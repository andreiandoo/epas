<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Configuration
            </x-filament::button>
        </div>
    </form>

    @if($activeProcessor === 'stripe')
        {{-- Apple Pay Domain Management Section --}}
        <div class="mt-8 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="fi-section-header-icon flex items-center justify-center rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
                        <x-heroicon-o-device-phone-mobile class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </div>
                    <div class="grid flex-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Apple Pay & Google Pay
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Activează plăți prin Apple Pay și Google Pay pe site-ul tău
                        </p>
                    </div>
                </div>
            </div>

            <div class="fi-section-content px-6 pb-6">
                {{-- Info about wallet payments --}}
                <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                    <div class="flex gap-3">
                        <x-heroicon-o-information-circle class="h-5 w-5 flex-shrink-0 text-blue-500" />
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-medium mb-1">Cum funcționează?</p>
                            <ul class="list-disc list-inside space-y-1 text-blue-700 dark:text-blue-300">
                                <li><strong>Google Pay:</strong> Funcționează automat fără configurare suplimentară</li>
                                <li><strong>Apple Pay:</strong> Necesită verificare domeniu (vezi mai jos)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Apple Pay Domain Registration --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-medium text-gray-950 dark:text-white">Verificare domeniu Apple Pay</h4>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                        <div class="flex gap-3">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 flex-shrink-0 text-amber-500" />
                            <div class="text-sm text-amber-800 dark:text-amber-200">
                                <p class="font-medium mb-1">Pași pentru activare Apple Pay:</p>
                                <ol class="list-decimal list-inside mt-2 space-y-2 text-amber-700 dark:text-amber-300">
                                    <li>Site-ul trebuie să folosească <strong>HTTPS</strong> (certificat SSL valid)</li>
                                    <li>Descarcă fișierul de verificare (butonul de mai jos)</li>
                                    <li>Urcă fișierul pe server la: <code class="bg-amber-100 dark:bg-amber-900 px-1 rounded">/.well-known/apple-developer-merchantid-domain-association</code></li>
                                    <li>Verifică că fișierul este accesibil: <code class="bg-amber-100 dark:bg-amber-900 px-1 rounded">https://domeniu.ro/.well-known/apple-developer-merchantid-domain-association</code></li>
                                    <li>Înregistrează domeniul mai jos</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    {{-- Download verification file --}}
                    <div class="flex items-center gap-4 p-4 rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Fișier verificare Apple Pay</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Descarcă și urcă acest fișier în directorul <code>.well-known</code> de pe server</p>
                        </div>
                        <a
                            href="{{ route('apple-pay.verification') }}"
                            download="apple-developer-merchantid-domain-association"
                            class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500"
                        >
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                            Descarcă fișier
                        </a>
                    </div>

                    {{-- Domain input and register button --}}
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <input
                                type="text"
                                wire:model="domainToRegister"
                                placeholder="ex: bilete.exemplu.ro"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                        </div>
                        <x-filament::button
                            wire:click="registerApplePayDomain"
                            wire:loading.attr="disabled"
                            color="success"
                        >
                            <x-filament::loading-indicator wire:loading wire:target="registerApplePayDomain" class="h-4 w-4 mr-2" />
                            Înregistrează domeniu
                        </x-filament::button>
                    </div>

                    {{-- Registered domains list --}}
                    @if(count($applePayDomains) > 0)
                        <div class="mt-4">
                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Domenii înregistrate:</h5>
                            <div class="space-y-2">
                                @foreach($applePayDomains as $domain)
                                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex items-center gap-2">
                                            <x-heroicon-o-check-circle class="h-5 w-5 text-green-500" />
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $domain['domain_name'] ?? 'Unknown' }}</span>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="deleteApplePayDomain('{{ $domain['id'] ?? '' }}')"
                                            wire:loading.attr="disabled"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <x-heroicon-o-trash class="h-5 w-5" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            Niciun domeniu înregistrat pentru Apple Pay. Adaugă un domeniu mai sus.
                        </div>
                    @endif

                    {{-- Tenant domains suggestion --}}
                    @php
                        $tenantDomains = auth()->user()->tenant?->domains()->where('is_active', true)->get() ?? collect();
                    @endphp
                    @if($tenantDomains->count() > 0)
                        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Domeniile tale active:</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($tenantDomains as $domain)
                                    <button
                                        type="button"
                                        wire:click="$set('domainToRegister', '{{ $domain->domain }}')"
                                        class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:ring-gray-600 dark:hover:bg-gray-600"
                                    >
                                        <x-heroicon-o-plus class="h-4 w-4" />
                                        {{ $domain->domain }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
