<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $locale === 'ro'
                        ? 'Ghid pas-cu-pas pentru utilizarea platformei. Alege un modul pentru a incepe.'
                        : 'Step-by-step guide for using the platform. Choose a module to get started.' }}
                </p>
            </div>
            <div class="flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                <button
                    wire:click="switchLocale('ro')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $locale === 'ro' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    RO
                </button>
                <button
                    wire:click="switchLocale('en')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $locale === 'en' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    EN
                </button>
            </div>
        </div>

        {{-- Module Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($modules as $module)
                <a
                    href="{{ $module['class']::getUrl() }}?lang={{ $locale }}"
                    class="group bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-lg hover:border-primary-300 dark:hover:border-primary-600 transition-all duration-200"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center group-hover:bg-primary-100 dark:group-hover:bg-primary-500/20 transition">
                            @php
                                $iconName = str_replace('heroicon-o-', '', $module['icon']);
                            @endphp
                            <x-dynamic-component :component="'heroicon-o-' . $iconName" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                                {{ $this->t($module['title']) }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                {{ $this->t($module['description']) }}
                            </p>
                            <div class="mt-3 flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 font-medium opacity-0 group-hover:opacity-100 transition">
                                {{ $locale === 'ro' ? 'Deschide ghidul' : 'Open guide' }}
                                <x-heroicon-m-arrow-right class="w-3 h-3" />
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Footer note --}}
        <div class="text-center py-4">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                {{ $locale === 'ro'
                    ? 'Ai nevoie de ajutor suplimentar? Contacteaza echipa de suport.'
                    : 'Need more help? Contact the support team.' }}
            </p>
        </div>
    </div>
</x-filament-panels::page>
