<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-chat-bubble-left-right class="w-8 h-8" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold">WhatsApp Notifications</h2>
                    <p class="text-green-100 text-sm">Send order confirmations, reminders, and updates via WhatsApp</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('filament.tenant.microservice-settings', ['slug' => 'whatsapp-notifications']) }}"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Configure WhatsApp API</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-document-text class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Message Templates</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-clock class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Delivery History</span>
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Features</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Order confirmation messages
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Event reminders (1 day, 3 hours before)
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Ticket delivery via WhatsApp
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Rich media templates with images
                    </li>
                </ul>
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4">
            <div class="flex gap-3">
                <x-heroicon-o-information-circle class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-medium text-green-900 dark:text-green-100">Setup Required</p>
                    <p class="text-green-700 dark:text-green-300 mt-1">
                        To use WhatsApp notifications, you need to configure your WhatsApp Business API credentials in the settings.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
