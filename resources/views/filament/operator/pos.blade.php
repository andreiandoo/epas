<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center gap-3">
                <label class="text-sm">Canal vânzare:</label>
                <select wire:model.live="channel" class="fi-input rounded-lg">
                    @foreach ($channels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @forelse ($catalog as $item)
                    <button
                        wire:click="addToCart({{ $item['id'] }})"
                        class="p-4 rounded-xl border-2 border-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition text-left"
                    >
                        <div class="text-xs uppercase text-gray-500">{{ $item['category'] }}</div>
                        <div class="font-semibold mt-1">{{ $item['name'] }}</div>
                        <div class="text-xl font-bold mt-2 text-emerald-600 dark:text-emerald-400">
                            {{ number_format($item['price_cents'] / 100, 2) }} RON
                        </div>
                    </button>
                @empty
                    <div class="col-span-3 text-center text-gray-500 py-12">Niciun bilet activ.</div>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <div class="p-4 rounded-xl border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 sticky top-4">
                <h3 class="font-semibold text-lg mb-3">Coș ({{ count($cart) }} produse)</h3>

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach ($cart as $item)
                        <div class="flex items-center justify-between p-2 rounded border border-gray-100 dark:border-gray-700">
                            <div class="flex-1">
                                <div class="text-sm font-medium">{{ $item['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ number_format($item['unit_cents']/100, 2) }} RON × {{ $item['qty'] }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="removeFromCart({{ $item['ticket_type_id'] }})" class="px-2 py-1 text-xs rounded bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300">−</button>
                                <button wire:click="addToCart({{ $item['ticket_type_id'] }})" class="px-2 py-1 text-xs rounded bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">+</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
                    <div class="flex justify-between text-lg font-bold">
                        <span>TOTAL</span>
                        <span>{{ number_format($cartTotal / 100, 2) }} RON</span>
                    </div>

                    <input type="email" wire:model="customerEmail" placeholder="Email client (opțional)" class="fi-input rounded-lg w-full mt-3">

                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <button wire:click="$set('paymentMethod', 'cash')" class="p-3 rounded-lg border-2 @if($paymentMethod === 'cash') border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30 @else border-gray-200 @endif">
                            💰 Cash
                        </button>
                        <button wire:click="$set('paymentMethod', 'card')" class="p-3 rounded-lg border-2 @if($paymentMethod === 'card') border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30 @else border-gray-200 @endif">
                            💳 Card
                        </button>
                    </div>

                    <button
                        wire:click="checkout"
                        @disabled(empty($cart))
                        class="w-full mt-3 fi-btn bg-violet-600 hover:bg-violet-700 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ✅ Finalizează comandă
                    </button>

                    <button wire:click="clearCart" class="w-full text-xs text-gray-500 hover:text-gray-700 mt-1">Golește coș</button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
