<x-filament-panels::page>
    <div class="flex gap-6 h-[calc(100vh-200px)]" x-data="themeEditor(@js($this->previewUrl))">
        {{-- Settings Panel --}}
        <div class="w-[480px] flex-shrink-0 overflow-y-auto pr-4">
            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-6 flex gap-3">
                    <x-filament::button type="submit" class="flex-1">
                        Save Theme
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="resetToDefaults"
                    >
                        Reset
                    </x-filament::button>
                </div>
            </form>
        </div>

        {{-- Preview Panel --}}
        <div class="flex-1 bg-gray-100 dark:bg-gray-900 rounded-xl overflow-hidden flex flex-col">
            {{-- Viewport Controls --}}
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-2 flex items-center gap-4">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Preview:</span>

                <div class="flex gap-1">
                    <button
                        @click="viewport = 'desktop'"
                        :class="viewport === 'desktop' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                        class="p-2 rounded-lg transition"
                        title="Desktop"
                    >
                        <x-heroicon-o-computer-desktop class="w-5 h-5" />
                    </button>
                    <button
                        @click="viewport = 'tablet'"
                        :class="viewport === 'tablet' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                        class="p-2 rounded-lg transition"
                        title="Tablet"
                    >
                        <x-heroicon-o-device-tablet class="w-5 h-5" />
                    </button>
                    <button
                        @click="viewport = 'mobile'"
                        :class="viewport === 'mobile' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                        class="p-2 rounded-lg transition"
                        title="Mobile"
                    >
                        <x-heroicon-o-device-phone-mobile class="w-5 h-5" />
                    </button>
                </div>

                <button
                    @click="refreshPreview"
                    class="ml-auto p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                    title="Refresh Preview"
                >
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                </button>
            </div>

            {{-- Preview Frame --}}
            <div class="flex-1 p-4 flex items-start justify-center overflow-auto">
                @if($this->previewUrl)
                    <iframe
                        x-ref="previewFrame"
                        :src="previewUrl"
                        :style="getViewportStyle()"
                        @load="onIframeLoad"
                        class="bg-white rounded-lg shadow-xl transition-all duration-300"
                    ></iframe>
                @else
                    <div class="flex flex-col items-center justify-center h-full text-gray-500">
                        <x-heroicon-o-globe-alt class="w-16 h-16 mb-4" />
                        <p class="text-lg font-medium">No Preview Available</p>
                        <p class="text-sm mt-2">You need a verified domain to preview your theme.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('themeEditor', (initialPreviewUrl) => ({
                viewport: 'desktop',
                previewUrl: initialPreviewUrl,
                iframe: null,

                getViewportStyle() {
                    const sizes = {
                        desktop: { width: '100%', maxWidth: '100%', height: '100%' },
                        tablet: { width: '768px', maxWidth: '768px', height: '100%' },
                        mobile: { width: '375px', maxWidth: '375px', height: '100%' },
                    };
                    return sizes[this.viewport];
                },

                onIframeLoad(event) {
                    this.iframe = event.target.contentWindow;
                    // Send initial ready message
                    this.iframe.postMessage({ type: 'PREVIEW_READY' }, '*');
                },

                updatePreview(theme) {
                    if (this.iframe) {
                        this.iframe.postMessage({
                            type: 'THEME_UPDATE',
                            theme: theme
                        }, '*');
                    }
                },

                refreshPreview() {
                    if (this.$refs.previewFrame) {
                        this.$refs.previewFrame.src = this.previewUrl + '&t=' + Date.now();
                    }
                },

                init() {
                    // Listen for Livewire theme change events
                    Livewire.on('theme-changed', (data) => {
                        if (data && data.theme) {
                            this.updatePreview(data.theme);
                        }
                    });
                }
            }));
        });
    </script>
</x-filament-panels::page>
