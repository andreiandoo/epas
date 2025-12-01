<x-filament-panels::page>
    <div class="flex gap-6 h-[calc(100vh-200px)]" x-data="pageBuilder(@js($blocks), @js($availableBlocks), @js($previewUrl), @js($currentPageSlug))" wire:ignore.self>
        {{-- Sidebar: Page List & Block List --}}
        <div class="w-[320px] flex-shrink-0 flex flex-col gap-4">
            {{-- Page Selector --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Pages</h3>
                    <a
                        href="{{ route('filament.tenant.resources.pages.create') }}"
                        class="text-primary-600 hover:text-primary-700 text-sm font-medium"
                    >
                        + New Page
                    </a>
                </div>

                <div class="space-y-1 max-h-[200px] overflow-y-auto">
                    @forelse($pages as $page)
                        <button
                            wire:click="selectPage({{ $page['id'] }})"
                            @class([
                                'w-full text-left px-3 py-2 rounded-lg text-sm transition flex items-center gap-2',
                                'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400' => $currentPageId === $page['id'],
                                'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' => $currentPageId !== $page['id'],
                            ])
                        >
                            @if($page['isSystem'])
                                <x-heroicon-o-lock-closed class="w-4 h-4 text-gray-400 flex-shrink-0" />
                            @endif
                            <span class="flex-1 truncate">{{ $page['title'] }}</span>
                            @if(!$page['isPublished'])
                                <span class="text-xs text-amber-600 dark:text-amber-400 flex-shrink-0">Draft</span>
                            @endif
                        </button>
                    @empty
                        <div class="text-sm text-gray-500 text-center py-4">
                            No pages yet.
                            <a href="{{ route('filament.tenant.resources.pages.create') }}" class="text-primary-600 hover:underline">Create one</a>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Block List --}}
            <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        Blocks
                        @if($currentPageSlug)
                            <span class="text-gray-400 font-normal text-sm">- {{ $currentPageSlug }}</span>
                        @endif
                    </h3>
                </div>

                <div class="flex-1 overflow-y-auto p-3" x-ref="blockList">
                    @if(!$currentPageId)
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-cursor-arrow-rays class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                            <p class="text-sm">Select a page first</p>
                        </div>
                    @elseif(empty($blocks))
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-cube class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                            <p class="text-sm">No blocks yet</p>
                            <p class="text-xs mt-1">Click "Add Block" below</p>
                        </div>
                    @else
                        <div class="space-y-2" id="sortable-blocks">
                            @foreach($blocks as $index => $block)
                                <div
                                    class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg group"
                                    data-block-id="{{ $block['id'] ?? '' }}"
                                    wire:key="block-{{ $block['id'] ?? $index }}"
                                >
                                    <div class="flex items-center gap-2 p-2">
                                        {{-- Drag Handle --}}
                                        <div class="cursor-move text-gray-400 hover:text-gray-600 sortable-handle">
                                            <x-heroicon-o-bars-3 class="w-4 h-4" />
                                        </div>

                                        {{-- Block Icon --}}
                                        <div class="w-7 h-7 rounded bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 flex-shrink-0">
                                            <x-dynamic-component :component="$this->getBlockIcon($block['type'] ?? 'unknown')" class="w-3.5 h-3.5" />
                                        </div>

                                        {{-- Block Info --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-xs text-gray-900 dark:text-white truncate">
                                                {{ $this->getBlockName($block['type'] ?? 'Unknown') }}
                                            </div>
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                            <button
                                                wire:click="editBlock('{{ $block['id'] ?? '' }}')"
                                                class="p-1 text-gray-400 hover:text-primary-600 rounded"
                                                title="Edit"
                                            >
                                                <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                wire:click="duplicateBlock('{{ $block['id'] ?? '' }}')"
                                                class="p-1 text-gray-400 hover:text-gray-600 rounded"
                                                title="Duplicate"
                                            >
                                                <x-heroicon-o-document-duplicate class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                wire:click="removeBlock('{{ $block['id'] ?? '' }}')"
                                                wire:confirm="Remove this block?"
                                                class="p-1 text-gray-400 hover:text-red-600 rounded"
                                                title="Remove"
                                            >
                                                <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Add Block Button --}}
                @if($currentPageId)
                    <div class="p-3 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="showBlockPicker = true"
                            class="w-full py-2 px-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-500 transition flex items-center justify-center gap-2 text-sm"
                        >
                            <x-heroicon-o-plus class="w-4 h-4" />
                            Add Block
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Preview Panel --}}
        <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl overflow-hidden flex flex-col border border-gray-200 dark:border-gray-700">
            {{-- Preview Header --}}
            <div class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-4 py-2 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Preview</span>
                    @if($currentPageSlug)
                        <span class="text-xs text-gray-400 bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded">
                            /page/{{ $currentPageSlug }}
                        </span>
                    @endif
                </div>

                @if($previewUrl && $currentPageSlug)
                    <a
                        href="{{ $previewUrl }}/page/{{ $currentPageSlug }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded text-xs font-medium transition"
                    >
                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                        Open in New Tab
                    </a>
                @endif
            </div>

            {{-- Preview Frame --}}
            <div class="flex-1 bg-gray-100 dark:bg-gray-900 overflow-hidden">
                @if($previewUrl && $currentPageSlug)
                    <iframe
                        id="preview-frame"
                        src="{{ $previewUrl }}/page/{{ $currentPageSlug }}?preview_mode=1&t={{ time() }}"
                        class="w-full h-full border-0"
                        sandbox="allow-scripts allow-same-origin allow-forms"
                    ></iframe>
                @else
                    <div class="flex items-center justify-center h-full text-gray-500">
                        <div class="text-center">
                            <x-heroicon-o-document class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            <p class="font-medium">Select a page to preview</p>
                            <p class="text-sm mt-1">Choose a page from the sidebar</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Block Picker Modal --}}
        <div
            x-show="showBlockPicker"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showBlockPicker = false"
            @keydown.escape.window="showBlockPicker = false"
        >
            <div
                x-show="showBlockPicker"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col"
            >
                {{-- Modal Header --}}
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-shrink-0">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Add Block</h3>
                    <button @click="showBlockPicker = false" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="flex-1 overflow-y-auto p-4" style="max-height: calc(80vh - 60px);">
                    <template x-for="(category, categoryKey) in availableBlocks.blocks" :key="categoryKey">
                        <div class="mb-5" x-show="category.blocks && category.blocks.length > 0">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2" x-text="category.name"></h4>
                            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-2">
                                <template x-for="block in category.blocks" :key="block.type">
                                    <button
                                        @click="addBlock(block.type)"
                                        type="button"
                                        class="p-2 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-center transition group"
                                    >
                                        <div class="w-8 h-8 mx-auto rounded bg-gray-100 dark:bg-gray-700 group-hover:bg-primary-100 dark:group-hover:bg-primary-800 flex items-center justify-center mb-1.5 transition">
                                            <x-heroicon-o-cube class="w-4 h-4 text-gray-400 group-hover:text-primary-600" />
                                        </div>
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary-700 dark:group-hover:text-primary-400 line-clamp-1" x-text="block.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <template x-if="!availableBlocks.blocks || Object.keys(availableBlocks.blocks).length === 0">
                        <div class="text-center py-8 text-gray-500">
                            <p>No blocks available</p>
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

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    @endpush

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('pageBuilder', (initialBlocks, availableBlocks, previewUrl, currentPageSlug) => ({
                blocks: initialBlocks || [],
                availableBlocks: availableBlocks || { blocks: {} },
                previewUrl: previewUrl || '',
                currentPageSlug: currentPageSlug || '',
                showBlockPicker: false,
                sortable: null,

                init() {
                    this.$nextTick(() => {
                        this.initSortable();
                    });

                    // Re-initialize sortable when blocks change
                    this.$watch('blocks', () => {
                        this.$nextTick(() => {
                            this.initSortable();
                        });
                    });

                    // Listen for Livewire updates
                    Livewire.on('blocks-updated', () => {
                        this.refreshPreview();
                    });
                },

                initSortable() {
                    if (this.sortable) {
                        this.sortable.destroy();
                    }

                    const el = document.getElementById('sortable-blocks');
                    if (!el) return;

                    this.sortable = new Sortable(el, {
                        handle: '.sortable-handle',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => {
                            const items = el.querySelectorAll('[data-block-id]');
                            const orderedIds = Array.from(items).map(item => item.dataset.blockId).filter(id => id);
                            if (orderedIds.length > 0) {
                                @this.reorderBlocks(orderedIds);
                            }
                        }
                    });
                },

                addBlock(type) {
                    console.log('Adding block:', type);
                    this.showBlockPicker = false;
                    @this.call('addBlock', type).then(() => {
                        console.log('Block added successfully');
                        this.refreshPreview();
                    }).catch(err => {
                        console.error('Error adding block:', err);
                    });
                },

                refreshPreview() {
                    const iframe = document.getElementById('preview-frame');
                    if (iframe) {
                        // Add timestamp to force reload
                        const src = iframe.src.split('?')[0];
                        iframe.src = src + '?preview_mode=1&t=' + Date.now();
                    }
                }
            }));
        });
    </script>
</x-filament-panels::page>
