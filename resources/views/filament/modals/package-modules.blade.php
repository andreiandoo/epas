<div class="space-y-4">
    @if($package->enabled_modules && count($package->enabled_modules) > 0)
        <div class="grid grid-cols-2 gap-2">
            @foreach($package->enabled_modules as $module)
                <div class="flex items-center p-2 bg-gray-100 dark:bg-gray-800 rounded">
                    <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm">{{ $module }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">No modules enabled for this package.</p>
    @endif

    @if($package->theme_config)
        <div class="mt-4">
            <h4 class="font-medium mb-2">Theme Configuration</h4>
            <pre class="p-3 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-x-auto">{{ json_encode($package->theme_config, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</div>
