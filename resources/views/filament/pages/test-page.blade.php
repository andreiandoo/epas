<x-filament-panels::page>
    <div class="p-6 bg-white rounded-lg shadow">
        <h1 class="text-2xl font-bold text-green-600">SUCCESS! Test Page Works!</h1>
        <p class="mt-4">If you see this, Filament pages CAN load.</p>
        <p class="mt-2">User: {{ auth()->user()->email }}</p>
        <p>Role: {{ auth()->user()->role }}</p>
    </div>
</x-filament-panels::page>
