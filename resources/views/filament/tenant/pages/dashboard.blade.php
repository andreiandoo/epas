<x-filament-panels::page>
    @if(!$tenant)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
            <p class="text-yellow-800">No tenant account found. Please contact support.</p>
        </div>
    @else
        <!-- Welcome Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                Welcome, {{ $tenant->public_name ?? $tenant->name }}!
            </h2>
            <p class="text-gray-600 mt-1">
                Manage your account, domains, and microservices from this dashboard.
            </p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Domains -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Domains</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['active_domains'] }}</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <x-heroicon-o-globe-alt class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
                <a href="{{ route('filament.tenant.pages.domains') }}" class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block">
                    View all &rarr;
                </a>
            </div>

            <!-- Microservices -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Microservices</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['microservices'] }}</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <x-heroicon-o-puzzle-piece class="w-6 h-6 text-purple-600" />
                    </div>
                </div>
                <a href="{{ route('filament.tenant.pages.microservices') }}" class="text-sm text-purple-600 hover:text-purple-800 mt-2 inline-block">
                    Manage &rarr;
                </a>
            </div>

            <!-- Invoices -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Invoices</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['invoices'] }}</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <x-heroicon-o-document-text class="w-6 h-6 text-green-600" />
                    </div>
                </div>
                <a href="{{ route('filament.tenant.pages.invoices') }}" class="text-sm text-green-600 hover:text-green-800 mt-2 inline-block">
                    View all &rarr;
                </a>
            </div>

            <!-- Unpaid -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Unpaid Invoices</p>
                        <p class="text-3xl font-bold {{ $stats['unpaid_invoices'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $stats['unpaid_invoices'] }}
                        </p>
                    </div>
                    <div class="p-3 {{ $stats['unpaid_invoices'] > 0 ? 'bg-red-100' : 'bg-gray-100' }} rounded-lg">
                        <x-heroicon-o-exclamation-circle class="w-6 h-6 {{ $stats['unpaid_invoices'] > 0 ? 'text-red-600' : 'text-gray-400' }}" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Account Info -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Account Information</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Company</dt>
                        <dd class="font-medium">{{ $tenant->company_name ?? $tenant->name }}</dd>
                    </div>
                    @if($tenant->cui)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">CUI</dt>
                            <dd class="font-medium">{{ $tenant->cui }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Email</dt>
                        <dd class="font-medium">{{ $tenant->contact_email ?? auth()->user()->email }}</dd>
                    </div>
                    @if($tenant->plan)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Plan</dt>
                            <dd class="font-medium">{{ ucfirst($tenant->plan) }}</dd>
                        </div>
                    @endif
                </dl>
                <a href="{{ route('filament.tenant.pages.profile') }}" class="text-sm text-indigo-600 hover:text-indigo-800 mt-4 inline-block">
                    Edit profile &rarr;
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('store.index') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <x-heroicon-o-shopping-cart class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Browse Store</p>
                            <p class="text-sm text-gray-500">Add new microservices</p>
                        </div>
                    </a>
                    <a href="{{ route('filament.tenant.pages.activity-log') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <x-heroicon-o-clock class="w-5 h-5 text-gray-600" />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Activity Log</p>
                            <p class="text-sm text-gray-500">View recent actions</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
