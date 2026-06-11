<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-user-group class="w-8 h-8" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Group Booking</h2>
                    <p class="text-blue-100 text-sm">Manage group reservations and bulk ticket purchases</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="/marketplace/group-bookings/create"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-blue-500" />
                        <span class="text-gray-700 dark:text-gray-300">Create Group Booking</span>
                    </a>
                    <a href="/marketplace/group-bookings"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">View All Bookings</span>
                    </a>
                    <a href="/marketplace/microservices/group-booking/settings"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Configure Settings</span>
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Features</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Group discounts and volume pricing
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Bulk ticket allocation
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Group leader management
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Invoice generation for groups
                    </li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
