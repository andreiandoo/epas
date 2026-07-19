<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Sub-module active</x-slot>
        <x-slot name="description">Activează sau dezactivează metodele de plată flexibilă pentru marketplace-ul tău.</x-slot>

        <div class="space-y-4">
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="enable_installments" class="rounded" />
                <span>Plată în rate</span>
            </label>
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="enable_bnpl" class="rounded" />
                <span>BNPL (Buy Now, Pay Later)</span>
            </label>
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="enable_delegated_pay" class="rounded" />
                <span>Plată delegată (plătește altcineva)</span>
            </label>
        </div>

        <div class="mt-4">
            <x-filament::button wire:click="save">Salvează</x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Informații & status</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Comision platformă (rate)</span><strong>{{ $info['platform_fee'] }}%</strong></div>
            <div class="flex justify-between"><span class="text-gray-500">Procesator</span><strong>{{ $info['provider'] }}</strong></div>
            <div class="flex justify-between">
                <span class="text-gray-500">Tokenizare (auto-debit)</span>
                <strong class="{{ $info['tokenizable'] ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $info['tokenizable'] ? 'Disponibilă' : 'Indisponibilă — rate/BNPL ascunse' }}
                </strong>
            </div>
            <div class="flex justify-between"><span class="text-gray-500">Reminder (zile înainte)</span><strong>{{ $info['reminder_days'] }}</strong></div>
            <div class="flex justify-between"><span class="text-gray-500">Durată max rate</span><strong>{{ $info['max_days'] }} zile</strong></div>
            <div class="flex justify-between"><span class="text-gray-500">BNPL max</span><strong>{{ $info['bnpl_days'] }} zile</strong></div>
            <div class="flex justify-between"><span class="text-gray-500">Hold plată delegată</span><strong>{{ $info['delegated_hours'] }} ore</strong></div>
        </div>
        @unless($info['tokenizable'])
            <div class="mt-3 text-sm text-danger-600">
                Procesatorul tău nu suportă debitarea automată (off-session). Rate și BNPL nu vor apărea în checkout până când nu configurezi Stripe sau Netopia cu tokenizare.
            </div>
        @endunless
    </x-filament::section>
</x-filament-panels::page>
