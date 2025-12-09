<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-gradient-to-r from-purple-500 to-violet-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-users class="w-8 h-8" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Customer Relationship Management</h2>
                    <p class="text-purple-100 text-sm">Manage customer data, segments, and communications</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="/tenant/microservices/crm/settings"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">CRM Settings</span>
                    </a>
                    <a href="{{ route('filament.tenant.resources.customers.index') }}"
                       class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-user-group class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">View Customers</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-funnel class="w-5 h-5 text-gray-500" />
                        <span class="text-gray-700 dark:text-gray-300">Customer Segments</span>
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Features</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Auto-create customer profiles
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Customer segmentation (VIP, repeat, etc.)
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Purchase history tracking
                    </li>
                    <li class="flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                        Email open tracking
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Customer Insights</h3>
                <div class="text-center py-4">
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">--</div>
                    <div class="text-sm text-gray-500">Total Customers</div>
                </div>
                <div class="border-t dark:border-gray-700 pt-4 mt-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Repeat buyers</span>
                        <span class="font-medium text-gray-900 dark:text-white">--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
