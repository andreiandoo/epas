<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Visual Editor - {{ $template->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .layer-element { cursor: move; user-select: none; }
        .layer-element:hover { outline: 2px solid #3b82f6; }
        .layer-element.selected { outline: 2px solid #2563eb; }
        .resize-handle { width: 8px; height: 8px; background: #2563eb; position: absolute; border-radius: 2px; }
        .resize-handle.nw { top: -4px; left: -4px; cursor: nw-resize; }
        .resize-handle.ne { top: -4px; right: -4px; cursor: ne-resize; }
        .resize-handle.sw { bottom: -4px; left: -4px; cursor: sw-resize; }
        .resize-handle.se { bottom: -4px; right: -4px; cursor: se-resize; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-900 text-white overflow-hidden">
    <div x-data="ticketCustomizer()" x-init="init()" class="flex h-screen flex-col">
        <!-- Top Bar -->
        <header class="bg-gray-800 border-b border-gray-700 px-4 py-2 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('filament.admin.resources.ticket-templates.edit', $template) }}" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h1 class="text-lg font-semibold">{{ $template->name }}</h1>
                <span class="px-2 py-1 text-xs rounded bg-gray-700 text-gray-300">{{ $template->tenant->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span x-show="hasUnsavedChanges" class="text-yellow-400 text-sm">Unsaved changes</span>
                <button @click="validateTemplate()" class="px-3 py-1.5 text-sm bg-yellow-600 hover:bg-yellow-700 rounded">
                    Validate
                </button>
                <button @click="generatePreview()" class="px-3 py-1.5 text-sm bg-green-600 hover:bg-green-700 rounded">
                    Preview
                </button>
                <button @click="saveTemplate()" :disabled="saving" class="px-4 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 rounded disabled:opacity-50">
                    <span x-show="!saving">Save</span>
                    <span x-show="saving">Saving...</span>
                </button>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <!-- Left Sidebar - Tools & Layers -->
            <aside class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
                <!-- Tools -->
                <div class="p-4 border-b border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-400 mb-3">Add Elements</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="addLayer('text')" class="flex flex-col items-center p-3 bg-gray-700 hover:bg-gray-600 rounded text-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
                            </svg>
                            Text
                        </button>
                        <button @click="addLayer('image')" class="flex flex-col items-center p-3 bg-gray-700 hover:bg-gray-600 rounded text-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Image
                        </button>
                        <button @click="addLayer('qr')" class="flex flex-col items-center p-3 bg-gray-700 hover:bg-gray-600 rounded text-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                            QR Code
                        </button>
                        <button @click="addLayer('barcode')" class="flex flex-col items-center p-3 bg-gray-700 hover:bg-gray-600 rounded text-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Barcode
                        </button>
                        <button @click="addLayer('shape')" class="flex flex-col items-center p-3 bg-gray-700 hover:bg-gray-600 rounded text-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/>
                            </svg>
                            Shape
                        </button>
                        <button @click="addLayer('line')" class="flex flex-col items-center p-3 bg-gray-700 hover:bg-gray-600 rounded text-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20L20 4"/>
                            </svg>
                            Line
                        </button>
                    </div>
                </div>

                <!-- Layers List -->
                <div class="flex-1 overflow-y-auto p-4">
                    <h3 class="text-sm font-semibold text-gray-400 mb-3">Layers</h3>
                    <div class="space-y-1">
                        <template x-for="layer in sortedLayers" :key="layer.id">
                            <div @click="selectLayer(layer.id)"
                                 :class="{'bg-blue-600': selectedLayerId === layer.id, 'bg-gray-700 hover:bg-gray-600': selectedLayerId !== layer.id}"
                                 class="p-2 rounded cursor-pointer flex items-center justify-between group">
                                <div class="flex items-center gap-2 truncate">
                                    <button @click.stop="toggleLayerVisibility(layer.id)" class="text-gray-400 hover:text-white">
                                        <svg x-show="layer.visible !== false" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg x-show="layer.visible === false" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                    <span class="text-sm truncate" x-text="layer.name"></span>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100">
                                    <button @click.stop="moveLayerUp(layer.id)" class="p-1 hover:bg-gray-500 rounded" title="Move up">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                        </svg>
                                    </button>
                                    <button @click.stop="moveLayerDown(layer.id)" class="p-1 hover:bg-gray-500 rounded" title="Move down">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <button @click.stop="deleteLayer(layer.id)" class="p-1 hover:bg-red-500 rounded" title="Delete">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div x-show="templateData.layers.length === 0" class="text-gray-500 text-sm text-center py-4">
                            No layers yet. Add elements using the tools above.
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Center - Canvas -->
            <main class="flex-1 bg-gray-900 overflow-auto p-8 flex flex-col items-center">
                <!-- Zoom Controls -->
                <div class="mb-4 flex items-center gap-4 bg-gray-800 rounded-lg px-4 py-2">
                    <button @click="zoom = Math.max(25, zoom - 25)" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                        </svg>
                    </button>
                    <span class="text-sm w-16 text-center" x-text="zoom + '%'"></span>
                    <button @click="zoom = Math.min(400, zoom + 25)" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                    <button @click="zoom = 100" class="text-xs text-gray-400 hover:text-white px-2">Reset</button>
                </div>

                <!-- Canvas Area -->
                <div class="bg-white shadow-2xl relative" :style="canvasStyle" @click="selectLayer(null)">
                    <!-- Grid overlay (optional) -->
                    <div class="absolute inset-0 pointer-events-none opacity-10" style="background-image: linear-gradient(#000 1px, transparent 1px), linear-gradient(90deg, #000 1px, transparent 1px); background-size: 10px 10px;"></div>

                    <!-- Layers -->
                    <template x-for="layer in templateData.layers" :key="layer.id">
                        <div x-show="layer.visible !== false"
                             @click.stop="selectLayer(layer.id)"
                             @mousedown="startDrag($event, layer)"
                             :class="{'selected': selectedLayerId === layer.id}"
                             class="layer-element absolute"
                             :style="getLayerStyle(layer)">
                            <!-- Text layer -->
                            <template x-if="layer.type === 'text'">
                                <div class="w-full h-full flex items-center overflow-hidden"
                                     :style="getTextStyle(layer)"
                                     x-text="layer.content || 'Text'"></div>
                            </template>
                            <!-- Image layer -->
                            <template x-if="layer.type === 'image'">
                                <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                    <template x-if="layer.src">
                                        <img :src="layer.src" class="w-full h-full object-contain" />
                                    </template>
                                    <template x-if="!layer.src">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </template>
                                </div>
                            </template>
                            <!-- QR Code layer -->
                            <template x-if="layer.type === 'qr'">
                                <div class="w-full h-full bg-white flex items-center justify-center border border-gray-300">
                                    <svg class="w-3/4 h-3/4 text-gray-600" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5zm13-2h3v2h-3v-2zm-3 0h2v3h-2v-3zm3 3h3v2h-3v-2zm-3 2h2v3h-2v-3zm3 1h3v2h-3v-2z"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- Barcode layer -->
                            <template x-if="layer.type === 'barcode'">
                                <div class="w-full h-full bg-white flex items-center justify-center border border-gray-300">
                                    <div class="w-full h-3/4 flex items-end justify-center gap-px px-2">
                                        <template x-for="i in 30">
                                            <div class="bg-gray-800" :style="'width: ' + (Math.random() > 0.5 ? '2px' : '1px') + '; height: ' + (60 + Math.random() * 40) + '%'"></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <!-- Shape layer -->
                            <template x-if="layer.type === 'shape'">
                                <div class="w-full h-full" :style="getShapeStyle(layer)"></div>
                            </template>
                            <!-- Line layer -->
                            <template x-if="layer.type === 'line'">
                                <div class="w-full h-full flex items-center">
                                    <div class="w-full" :style="getLineStyle(layer)"></div>
                                </div>
                            </template>
                            <!-- Resize handles -->
                            <template x-if="selectedLayerId === layer.id">
                                <div>
                                    <div class="resize-handle nw" @mousedown.stop="startResize($event, layer, 'nw')"></div>
                                    <div class="resize-handle ne" @mousedown.stop="startResize($event, layer, 'ne')"></div>
                                    <div class="resize-handle sw" @mousedown.stop="startResize($event, layer, 'sw')"></div>
                                    <div class="resize-handle se" @mousedown.stop="startResize($event, layer, 'se')"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="mt-4 text-sm text-gray-500">
                    <span x-text="templateData.meta.size_mm.w"></span> x <span x-text="templateData.meta.size_mm.h"></span> mm @ <span x-text="templateData.meta.dpi"></span> DPI
                </div>
            </main>

            <!-- Right Sidebar - Properties -->
            <aside class="w-80 bg-gray-800 border-l border-gray-700 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto">
                    <!-- Layer Properties -->
                    <div x-show="selectedLayer" class="p-4 border-b border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-400 mb-4">Layer Properties</h3>

                        <div class="space-y-4">
                            <!-- Name -->
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Name</label>
                                <input type="text" x-model="selectedLayer.name" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>

                            <!-- Position -->
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Position (mm)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-xs text-gray-500">X</span>
                                        <input type="number" x-model.number="selectedLayer.frame.x" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Y</span>
                                        <input type="number" x-model.number="selectedLayer.frame.y" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                </div>
                            </div>

                            <!-- Size -->
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Size (mm)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-xs text-gray-500">Width</span>
                                        <input type="number" x-model.number="selectedLayer.frame.w" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Height</span>
                                        <input type="number" x-model.number="selectedLayer.frame.h" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                </div>
                            </div>

                            <!-- Rotation -->
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Rotation</label>
                                <input type="range" x-model.number="selectedLayer.rotation" @input="markChanged()" min="0" max="360" class="w-full" />
                                <span class="text-xs text-gray-500" x-text="(selectedLayer.rotation || 0) + '°'"></span>
                            </div>

                            <!-- Opacity -->
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Opacity</label>
                                <input type="range" x-model.number="selectedLayer.opacity" @input="markChanged()" min="0" max="1" step="0.1" class="w-full" />
                                <span class="text-xs text-gray-500" x-text="Math.round((selectedLayer.opacity || 1) * 100) + '%'"></span>
                            </div>

                            <!-- Text-specific properties -->
                            <template x-if="selectedLayer.type === 'text'">
                                <div class="space-y-4 pt-4 border-t border-gray-700">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Content</label>
                                        <textarea x-model="selectedLayer.content" @input="markChanged()" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Font Size</label>
                                            <input type="number" x-model.number="selectedLayer.fontSize" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Font Weight</label>
                                            <select x-model="selectedLayer.fontWeight" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm">
                                                <option value="normal">Normal</option>
                                                <option value="bold">Bold</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Color</label>
                                        <input type="color" x-model="selectedLayer.color" @input="markChanged()" class="w-full h-8 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Text Align</label>
                                        <div class="flex gap-1">
                                            <button @click="selectedLayer.textAlign = 'left'; markChanged()" :class="{'bg-blue-600': selectedLayer.textAlign === 'left'}" class="flex-1 p-2 bg-gray-700 hover:bg-gray-600 rounded">
                                                <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14"/></svg>
                                            </button>
                                            <button @click="selectedLayer.textAlign = 'center'; markChanged()" :class="{'bg-blue-600': selectedLayer.textAlign === 'center'}" class="flex-1 p-2 bg-gray-700 hover:bg-gray-600 rounded">
                                                <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14"/></svg>
                                            </button>
                                            <button @click="selectedLayer.textAlign = 'right'; markChanged()" :class="{'bg-blue-600': selectedLayer.textAlign === 'right'}" class="flex-1 p-2 bg-gray-700 hover:bg-gray-600 rounded">
                                                <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Shape-specific properties -->
                            <template x-if="selectedLayer.type === 'shape'">
                                <div class="space-y-4 pt-4 border-t border-gray-700">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Fill Color</label>
                                        <input type="color" x-model="selectedLayer.fillColor" @input="markChanged()" class="w-full h-8 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Border Color</label>
                                        <input type="color" x-model="selectedLayer.borderColor" @input="markChanged()" class="w-full h-8 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Border Width</label>
                                        <input type="number" x-model.number="selectedLayer.borderWidth" @input="markChanged()" min="0" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Border Radius</label>
                                        <input type="number" x-model.number="selectedLayer.borderRadius" @input="markChanged()" min="0" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                </div>
                            </template>

                            <!-- Line-specific properties -->
                            <template x-if="selectedLayer.type === 'line'">
                                <div class="space-y-4 pt-4 border-t border-gray-700">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Line Color</label>
                                        <input type="color" x-model="selectedLayer.lineColor" @input="markChanged()" class="w-full h-8 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Line Width</label>
                                        <input type="number" x-model.number="selectedLayer.lineWidth" @input="markChanged()" min="1" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Line Style</label>
                                        <select x-model="selectedLayer.lineStyle" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm">
                                            <option value="solid">Solid</option>
                                            <option value="dashed">Dashed</option>
                                            <option value="dotted">Dotted</option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <!-- Image-specific properties -->
                            <template x-if="selectedLayer.type === 'image'">
                                <div class="space-y-4 pt-4 border-t border-gray-700">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Image URL</label>
                                        <input type="text" x-model="selectedLayer.src" @input="markChanged()" placeholder="https://..." class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Fit Mode</label>
                                        <select x-model="selectedLayer.objectFit" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm">
                                            <option value="contain">Contain</option>
                                            <option value="cover">Cover</option>
                                            <option value="fill">Fill</option>
                                        </select>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- No selection -->
                    <div x-show="!selectedLayer" class="p-4 border-b border-gray-700">
                        <p class="text-gray-500 text-sm">Select a layer to edit its properties</p>
                    </div>

                    <!-- Variables Panel -->
                    <div class="p-4">
                        <h3 class="text-sm font-semibold text-gray-400 mb-3">Available Variables</h3>
                        <p class="text-xs text-gray-500 mb-3">Click to copy. Use in text layers.</p>
                        <div class="space-y-3">
                            @foreach($variables as $category)
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500 mb-1">{{ $category['category'] }}</h4>
                                    <div class="space-y-1">
                                        @foreach($category['variables'] as $variable)
                                            <div @click="copyVariable('{{ $variable['placeholder'] }}')"
                                                 class="text-xs p-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 flex items-center justify-between"
                                                 title="{{ $variable['description'] }}">
                                                <code class="text-blue-400">{{ $variable['placeholder'] }}</code>
                                                <span class="text-gray-400 truncate ml-2">{{ $variable['label'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Validation/Error Messages -->
        <div x-show="messages.length > 0" class="fixed bottom-4 right-4 w-96 space-y-2 z-50">
            <template x-for="(msg, index) in messages" :key="index">
                <div :class="{'bg-red-600': msg.type === 'error', 'bg-yellow-600': msg.type === 'warning', 'bg-green-600': msg.type === 'success'}"
                     class="p-3 rounded shadow-lg flex items-start justify-between">
                    <span class="text-sm" x-text="msg.text"></span>
                    <button @click="messages.splice(index, 1)" class="ml-2 hover:opacity-75">&times;</button>
                </div>
            </template>
        </div>

        <!-- Preview Modal -->
        <div x-show="previewUrl" x-cloak class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click.self="previewUrl = null">
            <div class="bg-gray-800 rounded-lg p-4 max-w-4xl max-h-[90vh] overflow-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Preview</h3>
                    <button @click="previewUrl = null" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <img :src="previewUrl" class="max-w-full" />
            </div>
        </div>
    </div>

    <script>
        function ticketCustomizer() {
            return {
                templateId: {{ $template->id }},
                templateData: {!! json_encode($template->template_data ?: [
                    'meta' => ['dpi' => 300, 'size_mm' => ['w' => 80, 'h' => 200], 'orientation' => 'portrait', 'bleed_mm' => 3, 'safe_area_mm' => 5],
                    'assets' => [],
                    'layers' => []
                ]) !!},
                selectedLayerId: null,
                zoom: 100,
                saving: false,
                hasUnsavedChanges: false,
                messages: [],
                previewUrl: null,
                dragState: null,
                resizeState: null,

                init() {
                    // Ensure layers array exists
                    if (!this.templateData.layers) {
                        this.templateData.layers = [];
                    }

                    // Add keyboard shortcuts
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Delete' && this.selectedLayerId) {
                            this.deleteLayer(this.selectedLayerId);
                        }
                        if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
                            e.preventDefault();
                            this.saveTemplate();
                        }
                    });

                    // Mouse move/up for drag and resize
                    document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
                    document.addEventListener('mouseup', () => this.handleMouseUp());

                    // Warn before leaving with unsaved changes
                    window.addEventListener('beforeunload', (e) => {
                        if (this.hasUnsavedChanges) {
                            e.preventDefault();
                            e.returnValue = '';
                        }
                    });
                },

                get selectedLayer() {
                    return this.templateData.layers.find(l => l.id === this.selectedLayerId);
                },

                get sortedLayers() {
                    return [...this.templateData.layers].sort((a, b) => (b.z || 0) - (a.z || 0));
                },

                get canvasStyle() {
                    const scale = this.zoom / 100;
                    const w = this.templateData.meta.size_mm.w * scale;
                    const h = this.templateData.meta.size_mm.h * scale;
                    return `width: ${w}px; height: ${h}px;`;
                },

                generateId() {
                    return 'layer_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                },

                addLayer(type) {
                    const id = this.generateId();
                    const maxZ = Math.max(0, ...this.templateData.layers.map(l => l.z || 0));

                    const defaults = {
                        text: { name: 'Text', content: 'New Text', fontSize: 12, fontWeight: 'normal', color: '#000000', textAlign: 'left' },
                        image: { name: 'Image', src: '', objectFit: 'contain' },
                        qr: { name: 'QR Code', variable: '{{ticket.qr_code}}' },
                        barcode: { name: 'Barcode', variable: '{{ticket.barcode}}' },
                        shape: { name: 'Shape', fillColor: '#e5e7eb', borderColor: '#9ca3af', borderWidth: 1, borderRadius: 0 },
                        line: { name: 'Line', lineColor: '#000000', lineWidth: 1, lineStyle: 'solid' },
                    };

                    const layer = {
                        id,
                        type,
                        z: maxZ + 1,
                        frame: { x: 10, y: 10, w: type === 'line' ? 50 : 30, h: type === 'line' ? 2 : 20 },
                        rotation: 0,
                        opacity: 1,
                        visible: true,
                        ...defaults[type]
                    };

                    this.templateData.layers.push(layer);
                    this.selectedLayerId = id;
                    this.markChanged();
                },

                selectLayer(id) {
                    this.selectedLayerId = id;
                },

                deleteLayer(id) {
                    this.templateData.layers = this.templateData.layers.filter(l => l.id !== id);
                    if (this.selectedLayerId === id) {
                        this.selectedLayerId = null;
                    }
                    this.markChanged();
                },

                toggleLayerVisibility(id) {
                    const layer = this.templateData.layers.find(l => l.id === id);
                    if (layer) {
                        layer.visible = layer.visible === false ? true : false;
                        this.markChanged();
                    }
                },

                moveLayerUp(id) {
                    const layer = this.templateData.layers.find(l => l.id === id);
                    if (layer) {
                        layer.z = (layer.z || 0) + 1;
                        this.markChanged();
                    }
                },

                moveLayerDown(id) {
                    const layer = this.templateData.layers.find(l => l.id === id);
                    if (layer) {
                        layer.z = Math.max(0, (layer.z || 0) - 1);
                        this.markChanged();
                    }
                },

                getLayerStyle(layer) {
                    const scale = this.zoom / 100;
                    return {
                        left: (layer.frame.x * scale) + 'px',
                        top: (layer.frame.y * scale) + 'px',
                        width: (layer.frame.w * scale) + 'px',
                        height: (layer.frame.h * scale) + 'px',
                        opacity: layer.opacity || 1,
                        transform: `rotate(${layer.rotation || 0}deg)`,
                        zIndex: layer.z || 0,
                    };
                },

                getTextStyle(layer) {
                    const scale = this.zoom / 100;
                    return {
                        fontSize: ((layer.fontSize || 12) * scale) + 'px',
                        fontWeight: layer.fontWeight || 'normal',
                        color: layer.color || '#000000',
                        textAlign: layer.textAlign || 'left',
                        justifyContent: layer.textAlign === 'center' ? 'center' : layer.textAlign === 'right' ? 'flex-end' : 'flex-start',
                    };
                },

                getShapeStyle(layer) {
                    return {
                        backgroundColor: layer.fillColor || '#e5e7eb',
                        border: `${layer.borderWidth || 1}px solid ${layer.borderColor || '#9ca3af'}`,
                        borderRadius: (layer.borderRadius || 0) + 'px',
                    };
                },

                getLineStyle(layer) {
                    return {
                        height: (layer.lineWidth || 1) + 'px',
                        backgroundColor: layer.lineColor || '#000000',
                        borderStyle: layer.lineStyle || 'solid',
                    };
                },

                startDrag(event, layer) {
                    if (event.target.classList.contains('resize-handle')) return;

                    this.dragState = {
                        layerId: layer.id,
                        startX: event.clientX,
                        startY: event.clientY,
                        initialX: layer.frame.x,
                        initialY: layer.frame.y,
                    };
                },

                startResize(event, layer, handle) {
                    this.resizeState = {
                        layerId: layer.id,
                        handle,
                        startX: event.clientX,
                        startY: event.clientY,
                        initialFrame: { ...layer.frame },
                    };
                },

                handleMouseMove(event) {
                    const scale = this.zoom / 100;

                    if (this.dragState) {
                        const layer = this.templateData.layers.find(l => l.id === this.dragState.layerId);
                        if (layer) {
                            const dx = (event.clientX - this.dragState.startX) / scale;
                            const dy = (event.clientY - this.dragState.startY) / scale;
                            layer.frame.x = Math.round(this.dragState.initialX + dx);
                            layer.frame.y = Math.round(this.dragState.initialY + dy);
                        }
                    }

                    if (this.resizeState) {
                        const layer = this.templateData.layers.find(l => l.id === this.resizeState.layerId);
                        if (layer) {
                            const dx = (event.clientX - this.resizeState.startX) / scale;
                            const dy = (event.clientY - this.resizeState.startY) / scale;
                            const { handle, initialFrame } = this.resizeState;

                            if (handle.includes('e')) {
                                layer.frame.w = Math.max(5, Math.round(initialFrame.w + dx));
                            }
                            if (handle.includes('w')) {
                                layer.frame.x = Math.round(initialFrame.x + dx);
                                layer.frame.w = Math.max(5, Math.round(initialFrame.w - dx));
                            }
                            if (handle.includes('s')) {
                                layer.frame.h = Math.max(5, Math.round(initialFrame.h + dy));
                            }
                            if (handle.includes('n')) {
                                layer.frame.y = Math.round(initialFrame.y + dy);
                                layer.frame.h = Math.max(5, Math.round(initialFrame.h - dy));
                            }
                        }
                    }
                },

                handleMouseUp() {
                    if (this.dragState || this.resizeState) {
                        this.markChanged();
                    }
                    this.dragState = null;
                    this.resizeState = null;
                },

                markChanged() {
                    this.hasUnsavedChanges = true;
                },

                copyVariable(placeholder) {
                    navigator.clipboard.writeText(placeholder);
                    this.showMessage('Copied to clipboard', 'success');
                },

                showMessage(text, type = 'info') {
                    this.messages.push({ text, type });
                    setTimeout(() => {
                        this.messages = this.messages.filter(m => m.text !== text);
                    }, 5000);
                },

                async validateTemplate() {
                    try {
                        const response = await fetch('/api/tickets/templates/validate', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ template_json: this.templateData }),
                        });
                        const data = await response.json();

                        if (data.ok) {
                            this.showMessage('Template is valid', 'success');
                        } else {
                            data.errors.forEach(err => this.showMessage(err, 'error'));
                        }
                        data.warnings?.forEach(warn => this.showMessage(warn, 'warning'));
                    } catch (error) {
                        this.showMessage('Validation failed: ' + error.message, 'error');
                    }
                },

                async generatePreview() {
                    try {
                        const response = await fetch('/api/tickets/templates/preview', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                template_json: this.templateData,
                                scale: 2,
                            }),
                        });
                        const data = await response.json();

                        if (data.success && data.preview?.url) {
                            this.previewUrl = data.preview.url;
                        } else {
                            this.showMessage(data.error || 'Preview generation failed', 'error');
                        }
                    } catch (error) {
                        this.showMessage('Preview failed: ' + error.message, 'error');
                    }
                },

                async saveTemplate() {
                    this.saving = true;
                    try {
                        const response = await fetch(`/admin/ticket-customizer/${this.templateId}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ template_data: this.templateData }),
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.hasUnsavedChanges = false;
                            this.showMessage('Template saved successfully', 'success');
                        } else {
                            data.errors?.forEach(err => this.showMessage(err, 'error'));
                        }
                    } catch (error) {
                        this.showMessage('Save failed: ' + error.message, 'error');
                    } finally {
                        this.saving = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
