<x-filament-panels::page>
    <div class="flex gap-6 h-[calc(100vh-200px)]" x-data="pageBuilder(@js($blocks), @js($availableBlocks), @js($previewUrl), @js($currentPageSlug))">
        {{-- Sidebar: Page List & Block List --}}
        <div class="w-[400px] flex-shrink-0 flex flex-col gap-4">
            {{-- Page Selector --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Pages</h3>
                    <button
                        wire:click="createPage"
                        class="text-primary-600 hover:text-primary-700 text-sm font-medium"
                    >
                        + New Page
                    </button>
                </div>

                <div class="space-y-1">
                    @foreach($pages as $page)
                        <button
                            wire:click="selectPage({{ $page['id'] }})"
                            @class([
                                'w-full text-left px-3 py-2 rounded-lg text-sm transition flex items-center gap-2',
                                'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400' => $currentPageId === $page['id'],
                                'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' => $currentPageId !== $page['id'],
                            ])
                        >
                            @if($page['isSystem'])
                                <x-heroicon-o-lock-closed class="w-4 h-4 text-gray-400" />
                            @endif
                            <span class="flex-1">{{ $page['title'] }}</span>
                            @if(!$page['isPublished'])
                                <span class="text-xs text-amber-600 dark:text-amber-400">Draft</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Block List --}}
            <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        Blocks
                        @if($currentPageSlug)
                            <span class="text-gray-400 font-normal">- {{ $currentPageSlug }}</span>
                        @endif
                    </h3>
                </div>

                <div class="flex-1 overflow-y-auto p-4" x-ref="blockList">
                    @if(empty($blocks))
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-cube class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            <p>No blocks yet</p>
                            <p class="text-sm mt-1">Add blocks to build your page</p>
                        </div>
                    @else
                        <div class="space-y-2" id="sortable-blocks">
                            @foreach($blocks as $index => $block)
                                <div
                                    class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg group"
                                    data-block-id="{{ $block['id'] }}"
                                >
                                    <div class="flex items-center gap-2 p-3">
                                        {{-- Drag Handle --}}
                                        <div class="cursor-move text-gray-400 hover:text-gray-600 sortable-handle">
                                            <x-heroicon-o-bars-3 class="w-5 h-5" />
                                        </div>

                                        {{-- Block Icon --}}
                                        <div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600">
                                            <x-dynamic-component :component="$this->getBlockIcon($block['type'])" class="w-4 h-4" />
                                        </div>

                                        {{-- Block Info --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm text-gray-900 dark:text-white">
                                                {{ $this->getBlockName($block['type']) }}
                                            </div>
                                            @if($preview = $this->getBlockPreviewText($block))
                                                <div class="text-xs text-gray-500 truncate">{{ Str::limit($preview, 40) }}</div>
                                            @endif
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                            <button
                                                wire:click="moveBlock('{{ $block['id'] }}', 'up')"
                                                class="p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                                title="Move Up"
                                                @if($index === 0) disabled @endif
                                            >
                                                <x-heroicon-o-chevron-up class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click="moveBlock('{{ $block['id'] }}', 'down')"
                                                class="p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                                title="Move Down"
                                                @if($index === count($blocks) - 1) disabled @endif
                                            >
                                                <x-heroicon-o-chevron-down class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click="editBlock('{{ $block['id'] }}')"
                                                class="p-1 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/30 rounded"
                                                title="Edit Block"
                                            >
                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click="duplicateBlock('{{ $block['id'] }}')"
                                                class="p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                                title="Duplicate"
                                            >
                                                <x-heroicon-o-document-duplicate class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click="removeBlock('{{ $block['id'] }}')"
                                                wire:confirm="Are you sure you want to remove this block?"
                                                class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                                                title="Remove"
                                            >
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Add Block Button --}}
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        @click="showBlockPicker = true"
                        class="w-full py-3 px-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-500 transition flex items-center justify-center gap-2"
                    >
                        <x-heroicon-o-plus class="w-5 h-5" />
                        Add Block
                    </button>
                </div>
            </div>
        </div>

        {{-- Preview Panel --}}
        <div class="flex-1 bg-gray-100 dark:bg-gray-900 rounded-xl overflow-hidden flex flex-col">
            {{-- Preview Header --}}
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Preview</span>

                @if($previewUrl && $currentPageSlug)
                    <a
                        href="{{ $previewUrl }}/{{ $currentPageSlug === 'home' ? '' : $currentPageSlug }}"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition"
                    >
                        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        Open Website
                    </a>
                @endif
            </div>

            {{-- Preview Content --}}
            <div class="flex-1 flex items-center justify-center p-8">
                @if($previewUrl && $currentPageSlug)
                    <div class="text-center max-w-md">
                        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                            <x-heroicon-o-globe-alt class="w-10 h-10 text-primary-600" />
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Live Preview</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Changes are saved automatically. Click the button above to preview your website in a new tab.
                        </p>
                        <a
                            href="{{ $previewUrl }}/{{ $currentPageSlug === 'home' ? '' : $currentPageSlug }}"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition"
                        >
                            <x-heroicon-o-eye class="w-5 h-5" />
                            Preview Page
                        </a>
                        <p class="text-xs text-gray-400 mt-4">
                            URL: {{ $previewUrl }}/{{ $currentPageSlug === 'home' ? '' : $currentPageSlug }}
                        </p>
                    </div>
                @else
                    <div class="text-center text-gray-500">
                        <x-heroicon-o-document class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                        <p class="text-lg font-medium">Select a page to edit</p>
                        <p class="text-sm mt-1">Choose a page from the sidebar to start building</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Block Picker Modal --}}
        <div
            x-show="showBlockPicker"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showBlockPicker = false"
            @keydown.escape.window="showBlockPicker = false"
        >
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col">
                {{-- Modal Header --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-shrink-0">
                    <div>
                        <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Add Block</h3>
                        <p class="text-sm text-gray-500">Choose a block to add to your page</p>
                    </div>
                    <button @click="showBlockPicker = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Body - Scrollable --}}
                <div class="flex-1 overflow-y-auto p-4">
                    <template x-for="(category, categoryKey) in availableBlocks.blocks" :key="categoryKey">
                        <div class="mb-6" x-show="category.blocks && category.blocks.length > 0">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <span x-text="category.name"></span>
                                <span class="text-gray-300" x-text="'(' + category.blocks.length + ')'"></span>
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                <template x-for="block in category.blocks" :key="block.type">
                                    <button
                                        @click="addBlock(block.type)"
                                        class="p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-left transition group"
                                    >
                                        <div class="flex items-center gap-2 mb-1">
                                            <div class="w-8 h-8 rounded bg-gray-100 dark:bg-gray-700 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/30 flex items-center justify-center transition flex-shrink-0">
                                                <x-heroicon-o-cube class="w-4 h-4 text-gray-500 group-hover:text-primary-600" />
                                            </div>
                                            <span class="font-medium text-sm text-gray-900 dark:text-white truncate" x-text="block.name"></span>
                                        </div>
                                        <p class="text-xs text-gray-500 line-clamp-1 ml-10" x-text="block.description"></p>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Block Settings Modal --}}
    <x-filament::modal id="block-settings-modal" width="2xl">
        <x-slot name="heading">
            Edit Block
        </x-slot>

        <div class="space-y-4">
            {{-- Language Selector --}}
            <div class="flex items-center gap-2 mb-4">
                <span class="text-sm text-gray-600 dark:text-gray-400">Content Language:</span>
                <select
                    wire:model.live="contentLanguage"
                    class="text-sm border-gray-300 dark:border-gray-600 rounded-lg"
                >
                    <option value="en">English</option>
                    <option value="ro">Romanian</option>
                </select>
            </div>

            @if($editingBlockType)
                <form wire:submit="saveBlockSettings">
                    @foreach($this->getBlockSettingsForm() as $field)
                        {{ $field }}
                    @endforeach

                    <div class="mt-6 flex justify-end gap-3">
                        <x-filament::button
                            type="button"
                            color="gray"
                            x-on:click="$dispatch('close-modal', { id: 'block-settings-modal' })"
                        >
                            Cancel
                        </x-filament::button>
                        <x-filament::button type="submit">
                            Save Changes
                        </x-filament::button>
                    </div>
                </form>
            @endif
        </div>
    </x-filament::modal>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('pageBuilder', (initialBlocks, availableBlocks, previewUrl, currentPageSlug) => ({
                blocks: initialBlocks,
                availableBlocks: availableBlocks,
                previewUrl: previewUrl,
                currentPageSlug: currentPageSlug,
                showBlockPicker: false,
                sortable: null,

                init() {
                    this.initSortable();
                },

                initSortable() {
                    const el = document.getElementById('sortable-blocks');
                    if (!el) return;

                    this.sortable = new Sortable(el, {
                        handle: '.sortable-handle',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => {
                            const items = el.querySelectorAll('[data-block-id]');
                            const orderedIds = Array.from(items).map(item => item.dataset.blockId);
                            @this.reorderBlocks(orderedIds);
                        }
                    });
                },

                addBlock(type) {
                    this.showBlockPicker = false;
                    @this.addBlock(type);
                },
            }));
        });
    </script>
</x-filament-panels::page>
