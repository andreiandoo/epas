<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-gradient-to-r from-orange-500 to-amber-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-ticket class="w-8 h-8" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Ticket Customizer</h2>
                    <p class="text-orange-100 text-sm">Design custom ticket templates with drag-and-drop editor</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('filament.tenant.microservice-settings', ['slug' => 'ticket-customizer']) }}"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Configure Settings</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Create New Template</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-document-duplicate class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Browse Templates</span>
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Features</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        WYSIWYG visual editor with drag-and-drop
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        QR codes, barcodes, and variable placeholders
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Print-ready output with bleed and safe areas
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Multiple preset dimensions (A4, A6, standard tickets)
                    </li>
                </ul>
            </div>
        </div>

        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
            <div class="flex gap-3">
                <x-heroicon-o-light-bulb class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-medium text-amber-900 dark:text-amber-100">Coming Soon</p>
                    <p class="text-amber-700 dark:text-amber-300 mt-1">
                        The full visual ticket editor is being finalized. In the meantime, you can configure your template settings.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
