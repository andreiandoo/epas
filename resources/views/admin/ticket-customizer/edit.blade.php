<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Visual Editor - {{ $template->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|roboto:400,500,700|open+sans:400,600,700|lato:400,700|montserrat:400,500,600,700|poppins:400,500,600,700|playfair+display:400,700|oswald:400,500,700&display=swap" rel="stylesheet" />
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
        .rotate-handle { width: 20px; height: 20px; background: #2563eb; position: absolute; border-radius: 50%; top: -30px; left: 50%; transform: translateX(-50%); cursor: grab; display: flex; align-items: center; justify-content: center; }
        .rotate-handle svg { width: 12px; height: 12px; color: white; }
        .rotate-handle:active { cursor: grabbing; }
        .drop-zone { border: 2px dashed #4b5563; border-radius: 8px; transition: all 0.2s; }
        .drop-zone.drag-over { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .layer-item { transition: transform 0.15s, background 0.15s; }
        .layer-item.dragging { opacity: 0.5; background: #1f2937; }
        .layer-item.drag-over-top { border-top: 2px solid #3b82f6; }
        .layer-item.drag-over-bottom { border-bottom: 2px solid #3b82f6; }
        .collapsible-header { cursor: pointer; user-select: none; }
        .collapsible-header:hover { background: rgba(255,255,255,0.05); }
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
                <button @click="showTemplatesModal = true" class="px-3 py-1.5 text-sm bg-purple-600 hover:bg-purple-700 rounded flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    Templates
                </button>
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
            <aside class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col overflow-y-auto">
                <!-- Ticket Settings (Collapsible) -->
                <div class="border-b border-gray-700">
                    <div @click="showTicketSettings = !showTicketSettings" class="collapsible-header p-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-300">Ticket Settings</h3>
                        <svg :class="{'rotate-180': showTicketSettings}" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div x-show="showTicketSettings" x-collapse class="p-3 space-y-4">
                        <!-- Background Color -->
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Background Color</label>
                            <input type="color" x-model="templateData.meta.background.color" @input="markChanged()" class="w-full h-8 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                        </div>
                        <!-- Background Image -->
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Background Image</label>
                            <div class="drop-zone p-2 text-center cursor-pointer"
                                 :class="{'drag-over': bgDragOver}"
                                 @click="$refs.bgImageInput.click()"
                                 @dragover.prevent="bgDragOver = true"
                                 @dragleave="bgDragOver = false"
                                 @drop.prevent="handleBgImageDrop($event)">
                                <template x-if="templateData.meta.background.image">
                                    <div class="relative">
                                        <img :src="templateData.meta.background.image" class="max-h-12 mx-auto rounded" />
                                        <button @click.stop="templateData.meta.background.image = ''; markChanged()" class="absolute -top-1 -right-1 bg-red-500 rounded-full w-4 h-4 text-xs leading-none">&times;</button>
                                    </div>
                                </template>
                                <template x-if="!templateData.meta.background.image">
                                    <div class="text-xs text-gray-500">
                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Drop or click
                                    </div>
                                </template>
                            </div>
                            <input type="file" x-ref="bgImageInput" @change="handleBgImageSelect($event)" accept="image/*" class="hidden" />
                        </div>
                        <!-- Background Position -->
                        <template x-if="templateData.meta.background.image">
                            <div class="space-y-2">
                                <label class="block text-xs text-gray-400">Image Position</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-xs text-gray-500">X%</span>
                                        <input type="number" x-model.number="templateData.meta.background.positionX" @input="markChanged()" min="0" max="100" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-xs" placeholder="50" />
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Y%</span>
                                        <input type="number" x-model.number="templateData.meta.background.positionY" @input="markChanged()" min="0" max="100" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-xs" placeholder="50" />
                                    </div>
                                </div>
                                <div class="flex gap-1 flex-wrap">
                                    <button @click="templateData.meta.background.positionX = 0; templateData.meta.background.positionY = 50; markChanged()" class="px-1.5 py-0.5 text-xs bg-gray-700 hover:bg-gray-600 rounded">L</button>
                                    <button @click="templateData.meta.background.positionX = 50; templateData.meta.background.positionY = 50; markChanged()" class="px-1.5 py-0.5 text-xs bg-gray-700 hover:bg-gray-600 rounded">C</button>
                                    <button @click="templateData.meta.background.positionX = 100; templateData.meta.background.positionY = 50; markChanged()" class="px-1.5 py-0.5 text-xs bg-gray-700 hover:bg-gray-600 rounded">R</button>
                                    <button @click="templateData.meta.background.positionX = 50; templateData.meta.background.positionY = 0; markChanged()" class="px-1.5 py-0.5 text-xs bg-gray-700 hover:bg-gray-600 rounded">T</button>
                                    <button @click="templateData.meta.background.positionX = 50; templateData.meta.background.positionY = 100; markChanged()" class="px-1.5 py-0.5 text-xs bg-gray-700 hover:bg-gray-600 rounded">B</button>
                                </div>
                            </div>
                        </template>
                        <!-- Base Text Color -->
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Base Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="templateData.meta.baseTextColor" @input="markChanged()" class="w-8 h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                <span class="text-xs text-gray-500" x-text="templateData.meta.baseTextColor || '#000000'"></span>
                                <button @click="applyBaseTextColor()" class="ml-auto px-1.5 py-0.5 text-xs bg-blue-600 hover:bg-blue-700 rounded">Apply All</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Elements (Collapsible) -->
                <div class="border-b border-gray-700">
                    <div @click="showTicketElements = !showTicketElements" class="collapsible-header p-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-300">Ticket Elements</h3>
                        <svg :class="{'rotate-180': showTicketElements}" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div x-show="showTicketElements" x-collapse class="p-3">
                        <div class="grid grid-cols-3 gap-1">
                            <button @click="addLayer('text')" class="flex flex-col items-center p-2 bg-gray-700 hover:bg-gray-600 rounded text-xs">
                                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
                                </svg>
                                Text
                            </button>
                            <button @click="addLayer('image')" class="flex flex-col items-center p-2 bg-gray-700 hover:bg-gray-600 rounded text-xs">
                                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Image
                            </button>
                            <button @click="addLayer('qr')" class="flex flex-col items-center p-2 bg-gray-700 hover:bg-gray-600 rounded text-xs">
                                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                                QR
                            </button>
                            <button @click="addLayer('barcode')" class="flex flex-col items-center p-2 bg-gray-700 hover:bg-gray-600 rounded text-xs">
                                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Barcode
                            </button>
                            <button @click="addLayer('shape')" class="flex flex-col items-center p-2 bg-gray-700 hover:bg-gray-600 rounded text-xs">
                                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/>
                                </svg>
                                Shape
                            </button>
                            <button @click="addLayer('shape', 'line')" class="flex flex-col items-center p-2 bg-gray-700 hover:bg-gray-600 rounded text-xs">
                                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20L20 4"/>
                                </svg>
                                Line
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Layers List (Drag & Drop) -->
                <div class="flex-1 overflow-y-auto p-3">
                    <h3 class="text-sm font-semibold text-gray-400 mb-2">Layers</h3>
                    <div class="space-y-1">
                        <template x-for="(layer, index) in sortedLayers" :key="layer.id">
                            <div @click="selectLayer(layer.id)"
                                 draggable="true"
                                 @dragstart="startLayerDrag($event, layer)"
                                 @dragend="endLayerDrag()"
                                 @dragover.prevent="handleLayerDragOver($event, layer)"
                                 @dragleave="handleLayerDragLeave($event)"
                                 @drop.prevent="handleLayerDrop($event, layer)"
                                 :class="{'bg-blue-600': selectedLayerId === layer.id, 'bg-gray-700 hover:bg-gray-600': selectedLayerId !== layer.id, 'dragging': draggingLayerId === layer.id}"
                                 class="layer-item p-2 rounded cursor-grab flex items-center justify-between group">
                                <div class="flex items-center gap-2 truncate">
                                    <svg class="w-3 h-3 text-gray-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 6h2v2H8V6zm6 0h2v2h-2V6zM8 11h2v2H8v-2zm6 0h2v2h-2v-2zm-6 5h2v2H8v-2zm6 0h2v2h-2v-2z"/>
                                    </svg>
                                    <button @click.stop="toggleLayerVisibility(layer.id)" class="text-gray-400 hover:text-white flex-shrink-0">
                                        <svg x-show="layer.visible !== false" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg x-show="layer.visible === false" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                    <span class="text-xs truncate" x-text="layer.name"></span>
                                </div>
                                <button @click.stop="deleteLayer(layer.id)" class="p-1 hover:bg-red-500 rounded opacity-0 group-hover:opacity-100" title="Delete">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <div x-show="templateData.layers.length === 0" class="text-gray-500 text-xs text-center py-3">
                            No layers yet
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
                    <button @click="zoom = Math.min(600, zoom + 25)" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                    <button @click="zoom = 100" class="text-xs text-gray-400 hover:text-white px-2">Reset</button>
                </div>

                <!-- Canvas Area -->
                <div class="shadow-2xl relative" :style="canvasStyle" @click="selectLayer(null)">
                    <!-- Background -->
                    <div class="absolute inset-0" :style="canvasBackgroundStyle"></div>
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
                                     x-text="getDisplayContent(layer)"></div>
                            </template>
                            <!-- Image layer -->
                            <template x-if="layer.type === 'image'">
                                <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                    <template x-if="layer.src">
                                        <img :src="layer.src" class="w-full h-full" :style="'object-fit: ' + (layer.objectFit || 'contain')" />
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
                                <div class="w-full h-full flex items-center justify-center" :style="'background-color: ' + (layer.qrBackground || '#ffffff')">
                                    <svg class="w-3/4 h-3/4" viewBox="0 0 24 24" :fill="layer.qrForeground || '#000000'">
                                        <path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5zm13-2h3v2h-3v-2zm-3 0h2v3h-2v-3zm3 3h3v2h-3v-2zm-3 2h2v3h-2v-3zm3 1h3v2h-3v-2z"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- Barcode layer -->
                            <template x-if="layer.type === 'barcode'">
                                <div class="w-full h-full flex items-center justify-center" :style="'background-color: ' + (layer.barcodeBackground || '#ffffff')">
                                    <div class="w-full h-3/4 flex items-end justify-center gap-px px-2">
                                        <template x-for="i in 30" :key="i">
                                            <div :style="'background-color: ' + (layer.barcodeForeground || '#000000') + '; width: ' + (i % 3 === 0 ? '2px' : '1px') + '; height: ' + (60 + (i * 2) % 40) + '%'"></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <!-- Shape layer -->
                            <template x-if="layer.type === 'shape'">
                                <div class="w-full h-full" :style="getShapeStyle(layer)"></div>
                            </template>
                            <!-- Handles for selected layer -->
                            <template x-if="selectedLayerId === layer.id">
                                <div>
                                    <!-- Resize handles -->
                                    <div class="resize-handle nw" @mousedown.stop="startResize($event, layer, 'nw')"></div>
                                    <div class="resize-handle ne" @mousedown.stop="startResize($event, layer, 'ne')"></div>
                                    <div class="resize-handle sw" @mousedown.stop="startResize($event, layer, 'sw')"></div>
                                    <div class="resize-handle se" @mousedown.stop="startResize($event, layer, 'se')"></div>
                                    <!-- Rotate handle -->
                                    <div class="rotate-handle" @mousedown.stop="startRotate($event, layer)">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </div>
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
            <aside class="w-72 bg-gray-800 border-l border-gray-700 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto">
                    <!-- Available Variables (Collapsible) - First -->
                    <div class="border-b border-gray-700">
                        <div @click="showVariables = !showVariables" class="collapsible-header p-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-300">Available Variables</h3>
                            <svg :class="{'rotate-180': showVariables}" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <div x-show="showVariables" x-collapse class="p-3">
                            <p class="text-xs text-gray-500 mb-2" x-text="selectedLayer && selectedLayer.type === 'text' ? 'Click to insert' : 'Click to copy'"></p>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @foreach($variables as $groupKey => $group)
                                    <div>
                                        <h4 class="text-xs font-medium text-gray-500 mb-1">{{ $group['label'] }}</h4>
                                        <div class="space-y-0.5">
                                            @foreach($group['variables'] as $variable)
                                                @php $placeholder = '{{' . $variable['path'] . '}}'; @endphp
                                                <div @click="copyVariable('{{ $placeholder }}')"
                                                     class="text-xs py-1 px-2 bg-gray-700 rounded cursor-pointer hover:bg-gray-600 flex items-center justify-between"
                                                     title="{{ $variable['description'] }}">
                                                    <code class="text-blue-400 text-xs">{{ $placeholder }}</code>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Layer Properties (Collapsible) -->
                    <div class="border-b border-gray-700">
                        <div @click="showLayerProperties = !showLayerProperties" class="collapsible-header p-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-300">Layer Properties</h3>
                            <svg :class="{'rotate-180': showLayerProperties}" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <div x-show="showLayerProperties" x-collapse class="p-3">
                            <template x-if="selectedLayer">
                                <div class="space-y-3">
                                    <!-- Name -->
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Name</label>
                                        <input type="text" x-model="selectedLayer.name" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm" />
                                    </div>

                                    <!-- Position & Size in grid -->
                                    <div class="grid grid-cols-4 gap-1">
                                        <div>
                                            <span class="text-xs text-gray-500">X</span>
                                            <input type="number" x-model.number="selectedLayer.frame.x" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">Y</span>
                                            <input type="number" x-model.number="selectedLayer.frame.y" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">W</span>
                                            <input type="number" x-model.number="selectedLayer.frame.w" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">H</span>
                                            <input type="number" x-model.number="selectedLayer.frame.h" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                        </div>
                                    </div>

                                    <!-- Rotation & Opacity on same row -->
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <span class="text-xs text-gray-500">Rotation °</span>
                                            <input type="number" x-model.number="selectedLayer.rotation" @input="markChanged()" min="0" max="360" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">Opacity %</span>
                                            <input type="number" :value="Math.round((selectedLayer.opacity || 1) * 100)" @input="selectedLayer.opacity = $event.target.value / 100; markChanged()" min="0" max="100" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                        </div>
                                    </div>

                                    <!-- Text-specific properties -->
                                    <template x-if="selectedLayer.type === 'text'">
                                        <div class="space-y-2 pt-2 border-t border-gray-700">
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <label class="text-xs text-gray-400">Content</label>
                                                    <button @click="showVariables = true" type="button" class="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                        Add variable
                                                    </button>
                                                </div>
                                                <!-- Formatting toolbar -->
                                                <div class="flex gap-1 mb-1">
                                                    <button @click="wrapSelectedText('**', '**')" type="button" class="px-2 py-0.5 bg-gray-700 hover:bg-gray-600 rounded text-xs font-bold" title="Bold">B</button>
                                                    <button @click="wrapSelectedText('_', '_')" type="button" class="px-2 py-0.5 bg-gray-700 hover:bg-gray-600 rounded text-xs italic" title="Italic">I</button>
                                                    <button @click="wrapSelectedText('<u>', '</u>')" type="button" class="px-2 py-0.5 bg-gray-700 hover:bg-gray-600 rounded text-xs underline" title="Underline">U</button>
                                                </div>
                                                <textarea x-ref="contentTextarea" x-model="selectedLayer.content" @input="markChanged()" rows="4" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1.5 text-sm" placeholder="Text or @{{variable}}"></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <span class="text-xs text-gray-500">Font</span>
                                                    <select x-model="selectedLayer.fontFamily" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs">
                                                        <option value="Inter">Inter</option>
                                                        <option value="Roboto">Roboto</option>
                                                        <option value="Open Sans">Open Sans</option>
                                                        <option value="Lato">Lato</option>
                                                        <option value="Montserrat">Montserrat</option>
                                                        <option value="Poppins">Poppins</option>
                                                        <option value="Playfair Display">Playfair</option>
                                                        <option value="Oswald">Oswald</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <span class="text-xs text-gray-500">Weight</span>
                                                    <select x-model="selectedLayer.fontWeight" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs">
                                                        <option value="normal">Normal</option>
                                                        <option value="500">Medium</option>
                                                        <option value="600">SemiBold</option>
                                                        <option value="bold">Bold</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <span class="text-xs text-gray-500">Size</span>
                                                    <input type="number" x-model.number="selectedLayer.fontSize" @input="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                                </div>
                                                <div>
                                                    <span class="text-xs text-gray-500">Color</span>
                                                    <input type="color" x-model="selectedLayer.color" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                </div>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">Align</span>
                                                <div class="flex gap-1 mt-1">
                                                    <button @click="selectedLayer.textAlign = 'left'; markChanged()" :class="{'bg-blue-600': selectedLayer.textAlign === 'left'}" class="flex-1 p-1 bg-gray-700 hover:bg-gray-600 rounded">
                                                        <svg class="w-3 h-3 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14"/></svg>
                                                    </button>
                                                    <button @click="selectedLayer.textAlign = 'center'; markChanged()" :class="{'bg-blue-600': selectedLayer.textAlign === 'center'}" class="flex-1 p-1 bg-gray-700 hover:bg-gray-600 rounded">
                                                        <svg class="w-3 h-3 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14"/></svg>
                                                    </button>
                                                    <button @click="selectedLayer.textAlign = 'right'; markChanged()" :class="{'bg-blue-600': selectedLayer.textAlign === 'right'}" class="flex-1 p-1 bg-gray-700 hover:bg-gray-600 rounded">
                                                        <svg class="w-3 h-3 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Shape-specific properties -->
                                    <template x-if="selectedLayer.type === 'shape'">
                                        <div class="space-y-2 pt-2 border-t border-gray-700">
                                            <div>
                                                <span class="text-xs text-gray-500">Shape</span>
                                                <select x-model="selectedLayer.shapeKind" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs">
                                                    <option value="rect">Rectangle</option>
                                                    <option value="line">Line</option>
                                                    <option value="circle">Circle</option>
                                                    <option value="ellipse">Ellipse</option>
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <template x-if="selectedLayer.shapeKind !== 'line'">
                                                    <div>
                                                        <span class="text-xs text-gray-500">Fill</span>
                                                        <input type="color" x-model="selectedLayer.fillColor" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                    </div>
                                                </template>
                                                <div>
                                                    <span class="text-xs text-gray-500">Border</span>
                                                    <input type="color" x-model="selectedLayer.borderColor" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <span class="text-xs text-gray-500">Border W</span>
                                                    <input type="number" x-model.number="selectedLayer.borderWidth" @input="markChanged()" min="0" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                                </div>
                                                <template x-if="selectedLayer.shapeKind === 'rect'">
                                                    <div>
                                                        <span class="text-xs text-gray-500">Radius</span>
                                                        <input type="number" x-model.number="selectedLayer.borderRadius" @input="markChanged()" min="0" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs" />
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Image-specific properties -->
                                    <template x-if="selectedLayer.type === 'image'">
                                        <div class="space-y-2 pt-2 border-t border-gray-700">
                                            <div>
                                                <span class="text-xs text-gray-500">Image</span>
                                                <div class="drop-zone p-2 text-center cursor-pointer mt-1"
                                                     :class="{'drag-over': imageDragOver}"
                                                     @click="$refs.layerImageInput.click()"
                                                     @dragover.prevent="imageDragOver = true"
                                                     @dragleave="imageDragOver = false"
                                                     @drop.prevent="handleLayerImageDrop($event)">
                                                    <template x-if="selectedLayer.src">
                                                        <div class="relative">
                                                            <img :src="selectedLayer.src" class="max-h-16 mx-auto rounded" />
                                                            <button @click.stop="selectedLayer.src = ''; markChanged()" class="absolute -top-1 -right-1 bg-red-500 rounded-full w-4 h-4 text-xs">&times;</button>
                                                        </div>
                                                    </template>
                                                    <template x-if="!selectedLayer.src">
                                                        <div class="text-xs text-gray-500">
                                                            <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                            Drop or click
                                                        </div>
                                                    </template>
                                                </div>
                                                <input type="file" x-ref="layerImageInput" @change="handleLayerImageSelect($event)" accept="image/*" class="hidden" />
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">URL</span>
                                                <input type="text" x-model="selectedLayer.src" @input="markChanged()" placeholder="https://..." class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-xs" />
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">Fit</span>
                                                <select x-model="selectedLayer.objectFit" @change="markChanged()" class="w-full bg-gray-700 border border-gray-600 rounded px-1 py-1 text-xs">
                                                    <option value="contain">Contain</option>
                                                    <option value="cover">Cover</option>
                                                    <option value="fill">Fill</option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- QR Code-specific properties -->
                                    <template x-if="selectedLayer.type === 'qr'">
                                        <div class="space-y-2 pt-2 border-t border-gray-700">
                                            <div>
                                                <span class="text-xs text-gray-500">QR Data</span>
                                                <input type="text" x-model="selectedLayer.qrData" @input="markChanged()" placeholder="@{{qrcode}}" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-xs" />
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <span class="text-xs text-gray-500">Foreground</span>
                                                    <input type="color" x-model="selectedLayer.qrForeground" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                </div>
                                                <div>
                                                    <span class="text-xs text-gray-500">Background</span>
                                                    <input type="color" x-model="selectedLayer.qrBackground" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Barcode-specific properties -->
                                    <template x-if="selectedLayer.type === 'barcode'">
                                        <div class="space-y-2 pt-2 border-t border-gray-700">
                                            <div>
                                                <span class="text-xs text-gray-500">Barcode Data</span>
                                                <input type="text" x-model="selectedLayer.barcodeData" @input="markChanged()" placeholder="@{{barcode}}" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-xs" />
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <span class="text-xs text-gray-500">Foreground</span>
                                                    <input type="color" x-model="selectedLayer.barcodeForeground" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                </div>
                                                <div>
                                                    <span class="text-xs text-gray-500">Background</span>
                                                    <input type="color" x-model="selectedLayer.barcodeBackground" @input="markChanged()" class="w-full h-6 bg-gray-700 border border-gray-600 rounded cursor-pointer" />
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!selectedLayer">
                                <p class="text-gray-500 text-xs">Select a layer to edit</p>
                            </template>
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

        <!-- Templates Modal -->
        <div x-show="showTemplatesModal" x-cloak class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click.self="showTemplatesModal = false">
            <div class="bg-gray-800 rounded-lg p-6 max-w-5xl w-full mx-4 max-h-[90vh] overflow-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">Choose a Template</h3>
                    <button @click="showTemplatesModal = false" class="text-gray-400 hover:text-white text-2xl">&times;</button>
                </div>
                <p class="text-gray-400 text-sm mb-6">Select a template to start with. This will replace your current design.</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <template x-for="(preset, index) in templatePresets" :key="index">
                        <div @click="loadTemplate(preset)" class="bg-gray-700 rounded-lg p-3 cursor-pointer hover:bg-gray-600 transition border-2 border-transparent hover:border-blue-500">
                            <div class="aspect-[2/5] bg-gray-900 rounded mb-3 overflow-hidden flex items-center justify-center">
                                <div class="w-full h-full p-2" :style="'background-color: ' + preset.preview.bg">
                                    <!-- Mini preview representation -->
                                    <div class="w-full h-full relative" style="transform: scale(0.8);">
                                        <template x-for="(elem, i) in preset.preview.elements" :key="i">
                                            <div class="absolute" :style="elem.style" x-html="elem.content || ''"></div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <h4 class="font-medium text-sm" x-text="preset.name"></h4>
                            <p class="text-xs text-gray-400" x-text="preset.description"></p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sample data for variable preview
        const sampleData = @json($sampleData);

        function ticketCustomizer() {
            return {
                templateId: {{ $template->id }},
                templateData: {!! json_encode($template->template_data ?: [
                    'meta' => [
                        'version' => '1.0',
                        'dpi' => 300,
                        'size_mm' => ['w' => 80, 'h' => 200],
                        'orientation' => 'portrait',
                        'bleed_mm' => ['top' => 3, 'right' => 3, 'bottom' => 3, 'left' => 3],
                        'safe_area_mm' => 5,
                        'background' => ['color' => '#ffffff', 'image' => '']
                    ],
                    'assets' => [],
                    'layers' => []
                ]) !!},
                selectedLayerId: null,
                zoom: 300,
                saving: false,
                hasUnsavedChanges: false,
                messages: [],
                previewUrl: null,
                dragState: null,
                resizeState: null,
                rotateState: null,
                bgDragOver: false,
                imageDragOver: false,
                showTicketSettings: false,
                showTicketElements: true,
                showVariables: false,
                showLayerProperties: true,
                draggingLayerId: null,
                dragOverLayerId: null,
                showTemplatesModal: false,
                templatePresets: [
                    {
                        name: 'Classic Concert',
                        description: 'Traditional concert ticket layout',
                        preview: { bg: '#1a1a2e', elements: [
                            { style: 'top: 5%; left: 10%; right: 10%; height: 15%; background: linear-gradient(135deg, #e94560, #0f3460); border-radius: 4px;' },
                            { style: 'top: 25%; left: 10%; width: 60%; height: 8%; background: #fff; border-radius: 2px;' },
                            { style: 'top: 35%; left: 10%; width: 40%; height: 5%; background: rgba(255,255,255,0.5); border-radius: 2px;' },
                            { style: 'top: 45%; left: 10%; width: 50%; height: 4%; background: rgba(255,255,255,0.3); border-radius: 2px;' },
                            { style: 'bottom: 10%; right: 10%; width: 25%; height: 20%; background: #fff; border-radius: 4px;' },
                        ]},
                        data: {
                            meta: { version: '1.0', dpi: 300, size_mm: { w: 80, h: 200 }, orientation: 'portrait', bleed_mm: { top: 3, right: 3, bottom: 3, left: 3 }, safe_area_mm: 5, background: { color: '#1a1a2e', image: '' }, baseTextColor: '#ffffff' },
                            assets: [], layers: [
                                { id: 'header_shape', type: 'shape', z: 1, frame: { x: 5, y: 5, w: 70, h: 25 }, rotation: 0, opacity: 1, visible: true, name: 'Header BG', shapeKind: 'rect', fillColor: '#e94560', borderColor: '#e94560', borderWidth: 0, borderRadius: 8 },
                                { id: 'event_name', type: 'text', z: 10, frame: { x: 5, y: 35, w: 70, h: 15 }, rotation: 0, opacity: 1, visible: true, name: 'Event Name', content: '@{{event.name}}', fontSize: 16, fontWeight: 'bold', fontFamily: 'Montserrat', color: '#ffffff', textAlign: 'left' },
                                { id: 'venue', type: 'text', z: 9, frame: { x: 5, y: 52, w: 70, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Venue', content: '@{{event.venue}}', fontSize: 10, fontWeight: 'normal', fontFamily: 'Inter', color: '#cccccc', textAlign: 'left' },
                                { id: 'date', type: 'text', z: 8, frame: { x: 5, y: 65, w: 35, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Date', content: '@{{event.date}}', fontSize: 11, fontWeight: '600', fontFamily: 'Inter', color: '#e94560', textAlign: 'left' },
                                { id: 'time', type: 'text', z: 7, frame: { x: 40, y: 65, w: 35, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Time', content: '@{{event.time}}', fontSize: 11, fontWeight: '600', fontFamily: 'Inter', color: '#e94560', textAlign: 'right' },
                                { id: 'divider', type: 'shape', z: 2, frame: { x: 5, y: 80, w: 70, h: 1 }, rotation: 0, opacity: 0.3, visible: true, name: 'Divider', shapeKind: 'rect', fillColor: '#ffffff', borderColor: '#ffffff', borderWidth: 0, borderRadius: 0 },
                                { id: 'ticket_holder', type: 'text', z: 6, frame: { x: 5, y: 90, w: 70, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket Holder', content: '@{{ticket.holder_name}}', fontSize: 12, fontWeight: '500', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                                { id: 'ticket_type', type: 'text', z: 5, frame: { x: 5, y: 105, w: 40, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket Type', content: '@{{ticket.type}}', fontSize: 9, fontWeight: 'normal', fontFamily: 'Inter', color: '#888888', textAlign: 'left' },
                                { id: 'price', type: 'text', z: 4, frame: { x: 45, y: 105, w: 30, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Price', content: '@{{ticket.price}}', fontSize: 11, fontWeight: 'bold', fontFamily: 'Inter', color: '#e94560', textAlign: 'right' },
                                { id: 'qr', type: 'qr', z: 3, frame: { x: 50, y: 160, w: 28, h: 28 }, rotation: 0, opacity: 1, visible: true, name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#000000', qrBackground: '#ffffff' },
                                { id: 'ticket_id', type: 'text', z: 2, frame: { x: 5, y: 175, w: 40, h: 6 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket ID', content: '#@{{ticket.id}}', fontSize: 7, fontWeight: 'normal', fontFamily: 'Inter', color: '#666666', textAlign: 'left' },
                            ]
                        }
                    },
                    {
                        name: 'Modern Minimal',
                        description: 'Clean and simple design',
                        preview: { bg: '#ffffff', elements: [
                            { style: 'top: 8%; left: 10%; width: 70%; height: 10%; background: #111; border-radius: 2px;' },
                            { style: 'top: 22%; left: 10%; width: 50%; height: 6%; background: #333; border-radius: 2px;' },
                            { style: 'top: 32%; left: 10%; width: 35%; height: 4%; background: #666; border-radius: 2px;' },
                            { style: 'bottom: 15%; left: 50%; transform: translateX(-50%); width: 30%; height: 25%; background: #000; border-radius: 4px;' },
                        ]},
                        data: {
                            meta: { version: '1.0', dpi: 300, size_mm: { w: 80, h: 200 }, orientation: 'portrait', bleed_mm: { top: 3, right: 3, bottom: 3, left: 3 }, safe_area_mm: 5, background: { color: '#ffffff', image: '' }, baseTextColor: '#000000' },
                            assets: [], layers: [
                                { id: 'event_name', type: 'text', z: 10, frame: { x: 8, y: 15, w: 64, h: 18 }, rotation: 0, opacity: 1, visible: true, name: 'Event Name', content: '@{{event.name}}', fontSize: 18, fontWeight: 'bold', fontFamily: 'Playfair Display', color: '#000000', textAlign: 'left' },
                                { id: 'venue', type: 'text', z: 9, frame: { x: 8, y: 38, w: 64, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Venue', content: '@{{event.venue}}', fontSize: 10, fontWeight: 'normal', fontFamily: 'Inter', color: '#333333', textAlign: 'left' },
                                { id: 'date_time', type: 'text', z: 8, frame: { x: 8, y: 52, w: 64, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Date & Time', content: '@{{event.date}} • @{{event.time}}', fontSize: 9, fontWeight: '500', fontFamily: 'Inter', color: '#666666', textAlign: 'left' },
                                { id: 'line1', type: 'shape', z: 2, frame: { x: 8, y: 68, w: 64, h: 0.5 }, rotation: 0, opacity: 1, visible: true, name: 'Line', shapeKind: 'rect', fillColor: '#e0e0e0', borderColor: '#e0e0e0', borderWidth: 0, borderRadius: 0 },
                                { id: 'holder', type: 'text', z: 7, frame: { x: 8, y: 78, w: 64, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Holder', content: '@{{ticket.holder_name}}', fontSize: 11, fontWeight: '600', fontFamily: 'Inter', color: '#000000', textAlign: 'left' },
                                { id: 'type_price', type: 'text', z: 6, frame: { x: 8, y: 92, w: 64, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Type & Price', content: '@{{ticket.type}} — @{{ticket.price}}', fontSize: 9, fontWeight: 'normal', fontFamily: 'Inter', color: '#666666', textAlign: 'left' },
                                { id: 'qr', type: 'qr', z: 5, frame: { x: 25, y: 140, w: 30, h: 30 }, rotation: 0, opacity: 1, visible: true, name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#000000', qrBackground: '#ffffff' },
                                { id: 'ticket_id', type: 'text', z: 4, frame: { x: 8, y: 180, w: 64, h: 6 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket ID', content: '@{{ticket.id}}', fontSize: 7, fontWeight: 'normal', fontFamily: 'Inter', color: '#999999', textAlign: 'center' },
                            ]
                        }
                    },
                    {
                        name: 'Bold Festival',
                        description: 'Vibrant colors for festivals',
                        preview: { bg: '#ff6b35', elements: [
                            { style: 'top: 0; left: 0; right: 0; height: 40%; background: linear-gradient(180deg, #004e89, #1a659e);' },
                            { style: 'top: 10%; left: 10%; width: 60%; height: 12%; background: #fff; border-radius: 2px;' },
                            { style: 'top: 25%; left: 10%; width: 40%; height: 6%; background: rgba(255,255,255,0.7); border-radius: 2px;' },
                            { style: 'bottom: 8%; right: 8%; width: 28%; height: 22%; background: #fff; border-radius: 4px;' },
                        ]},
                        data: {
                            meta: { version: '1.0', dpi: 300, size_mm: { w: 80, h: 200 }, orientation: 'portrait', bleed_mm: { top: 3, right: 3, bottom: 3, left: 3 }, safe_area_mm: 5, background: { color: '#ff6b35', image: '' }, baseTextColor: '#ffffff' },
                            assets: [], layers: [
                                { id: 'header_bg', type: 'shape', z: 1, frame: { x: 0, y: 0, w: 80, h: 70 }, rotation: 0, opacity: 1, visible: true, name: 'Header BG', shapeKind: 'rect', fillColor: '#004e89', borderColor: '#004e89', borderWidth: 0, borderRadius: 0 },
                                { id: 'event_name', type: 'text', z: 10, frame: { x: 8, y: 20, w: 64, h: 22 }, rotation: 0, opacity: 1, visible: true, name: 'Event Name', content: '@{{event.name}}', fontSize: 20, fontWeight: 'bold', fontFamily: 'Oswald', color: '#ffffff', textAlign: 'left' },
                                { id: 'venue', type: 'text', z: 9, frame: { x: 8, y: 45, w: 64, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Venue', content: '@{{event.venue}}', fontSize: 10, fontWeight: '500', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                                { id: 'date', type: 'text', z: 8, frame: { x: 8, y: 80, w: 64, h: 14 }, rotation: 0, opacity: 1, visible: true, name: 'Date', content: '@{{event.date}}', fontSize: 14, fontWeight: 'bold', fontFamily: 'Oswald', color: '#ffffff', textAlign: 'left' },
                                { id: 'time', type: 'text', z: 7, frame: { x: 8, y: 96, w: 64, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Time', content: '@{{event.time}}', fontSize: 10, fontWeight: 'normal', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                                { id: 'holder', type: 'text', z: 6, frame: { x: 8, y: 120, w: 64, h: 12 }, rotation: 0, opacity: 1, visible: true, name: 'Holder', content: '@{{ticket.holder_name}}', fontSize: 12, fontWeight: '600', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                                { id: 'ticket_type', type: 'text', z: 5, frame: { x: 8, y: 135, w: 40, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket Type', content: '@{{ticket.type}}', fontSize: 10, fontWeight: '500', fontFamily: 'Inter', color: '#004e89', textAlign: 'left' },
                                { id: 'qr', type: 'qr', z: 4, frame: { x: 48, y: 155, w: 28, h: 28 }, rotation: 0, opacity: 1, visible: true, name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#004e89', qrBackground: '#ffffff' },
                                { id: 'ticket_id', type: 'text', z: 3, frame: { x: 8, y: 165, w: 38, h: 6 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket ID', content: '#@{{ticket.id}}', fontSize: 7, fontWeight: 'normal', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                            ]
                        }
                    },
                    {
                        name: 'Elegant Dark',
                        description: 'Sophisticated dark theme',
                        preview: { bg: '#0d0d0d', elements: [
                            { style: 'top: 8%; left: 15%; right: 15%; height: 1px; background: linear-gradient(90deg, transparent, #c9a227, transparent);' },
                            { style: 'top: 15%; left: 10%; width: 65%; height: 10%; background: #c9a227; border-radius: 2px;' },
                            { style: 'top: 28%; left: 10%; width: 45%; height: 5%; background: #333; border-radius: 2px;' },
                            { style: 'top: 36%; left: 10%; width: 30%; height: 4%; background: #222; border-radius: 2px;' },
                            { style: 'bottom: 10%; left: 50%; transform: translateX(-50%); width: 25%; height: 20%; background: #c9a227; border-radius: 4px;' },
                        ]},
                        data: {
                            meta: { version: '1.0', dpi: 300, size_mm: { w: 80, h: 200 }, orientation: 'portrait', bleed_mm: { top: 3, right: 3, bottom: 3, left: 3 }, safe_area_mm: 5, background: { color: '#0d0d0d', image: '' }, baseTextColor: '#ffffff' },
                            assets: [], layers: [
                                { id: 'gold_line_top', type: 'shape', z: 1, frame: { x: 10, y: 12, w: 60, h: 0.5 }, rotation: 0, opacity: 1, visible: true, name: 'Gold Line Top', shapeKind: 'rect', fillColor: '#c9a227', borderColor: '#c9a227', borderWidth: 0, borderRadius: 0 },
                                { id: 'event_name', type: 'text', z: 10, frame: { x: 8, y: 22, w: 64, h: 18 }, rotation: 0, opacity: 1, visible: true, name: 'Event Name', content: '@{{event.name}}', fontSize: 16, fontWeight: 'bold', fontFamily: 'Playfair Display', color: '#c9a227', textAlign: 'center' },
                                { id: 'venue', type: 'text', z: 9, frame: { x: 8, y: 45, w: 64, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Venue', content: '@{{event.venue}}', fontSize: 9, fontWeight: 'normal', fontFamily: 'Inter', color: '#888888', textAlign: 'center' },
                                { id: 'gold_line_mid', type: 'shape', z: 1, frame: { x: 25, y: 60, w: 30, h: 0.5 }, rotation: 0, opacity: 0.5, visible: true, name: 'Gold Line Mid', shapeKind: 'rect', fillColor: '#c9a227', borderColor: '#c9a227', borderWidth: 0, borderRadius: 0 },
                                { id: 'date', type: 'text', z: 8, frame: { x: 8, y: 70, w: 64, h: 12 }, rotation: 0, opacity: 1, visible: true, name: 'Date', content: '@{{event.date}}', fontSize: 12, fontWeight: '600', fontFamily: 'Inter', color: '#ffffff', textAlign: 'center' },
                                { id: 'time', type: 'text', z: 7, frame: { x: 8, y: 84, w: 64, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Time', content: '@{{event.time}}', fontSize: 9, fontWeight: 'normal', fontFamily: 'Inter', color: '#666666', textAlign: 'center' },
                                { id: 'holder', type: 'text', z: 6, frame: { x: 8, y: 105, w: 64, h: 12 }, rotation: 0, opacity: 1, visible: true, name: 'Holder', content: '@{{ticket.holder_name}}', fontSize: 11, fontWeight: '500', fontFamily: 'Inter', color: '#ffffff', textAlign: 'center' },
                                { id: 'type_price', type: 'text', z: 5, frame: { x: 8, y: 120, w: 64, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Type & Price', content: '@{{ticket.type}} • @{{ticket.price}}', fontSize: 8, fontWeight: 'normal', fontFamily: 'Inter', color: '#c9a227', textAlign: 'center' },
                                { id: 'qr', type: 'qr', z: 4, frame: { x: 25, y: 145, w: 30, h: 30 }, rotation: 0, opacity: 1, visible: true, name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#c9a227', qrBackground: '#0d0d0d' },
                                { id: 'ticket_id', type: 'text', z: 3, frame: { x: 8, y: 182, w: 64, h: 6 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket ID', content: '@{{ticket.id}}', fontSize: 6, fontWeight: 'normal', fontFamily: 'Inter', color: '#444444', textAlign: 'center' },
                            ]
                        }
                    },
                    {
                        name: 'Sports Event',
                        description: 'Dynamic layout for sports',
                        preview: { bg: '#1e3a5f', elements: [
                            { style: 'top: 0; left: 0; width: 40%; height: 35%; background: #e63946; clip-path: polygon(0 0, 100% 0, 70% 100%, 0 100%);' },
                            { style: 'top: 8%; left: 8%; width: 50%; height: 10%; background: #fff; border-radius: 2px;' },
                            { style: 'top: 40%; left: 8%; width: 60%; height: 6%; background: rgba(255,255,255,0.8); border-radius: 2px;' },
                            { style: 'bottom: 8%; right: 8%; width: 28%; height: 22%; background: #fff; border-radius: 4px;' },
                        ]},
                        data: {
                            meta: { version: '1.0', dpi: 300, size_mm: { w: 80, h: 200 }, orientation: 'portrait', bleed_mm: { top: 3, right: 3, bottom: 3, left: 3 }, safe_area_mm: 5, background: { color: '#1e3a5f', image: '' }, baseTextColor: '#ffffff' },
                            assets: [], layers: [
                                { id: 'accent_shape', type: 'shape', z: 1, frame: { x: 0, y: 0, w: 50, h: 60 }, rotation: 0, opacity: 1, visible: true, name: 'Accent Shape', shapeKind: 'rect', fillColor: '#e63946', borderColor: '#e63946', borderWidth: 0, borderRadius: 0 },
                                { id: 'event_name', type: 'text', z: 10, frame: { x: 8, y: 18, w: 64, h: 18 }, rotation: 0, opacity: 1, visible: true, name: 'Event Name', content: '@{{event.name}}', fontSize: 16, fontWeight: 'bold', fontFamily: 'Oswald', color: '#ffffff', textAlign: 'left' },
                                { id: 'venue', type: 'text', z: 9, frame: { x: 8, y: 40, w: 64, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Venue', content: '@{{event.venue}}', fontSize: 10, fontWeight: 'normal', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                                { id: 'date', type: 'text', z: 8, frame: { x: 8, y: 70, w: 35, h: 14 }, rotation: 0, opacity: 1, visible: true, name: 'Date', content: '@{{event.date}}', fontSize: 13, fontWeight: 'bold', fontFamily: 'Oswald', color: '#e63946', textAlign: 'left' },
                                { id: 'time', type: 'text', z: 7, frame: { x: 45, y: 70, w: 30, h: 14 }, rotation: 0, opacity: 1, visible: true, name: 'Time', content: '@{{event.time}}', fontSize: 13, fontWeight: 'bold', fontFamily: 'Oswald', color: '#ffffff', textAlign: 'right' },
                                { id: 'holder', type: 'text', z: 6, frame: { x: 8, y: 100, w: 64, h: 12 }, rotation: 0, opacity: 1, visible: true, name: 'Holder', content: '@{{ticket.holder_name}}', fontSize: 12, fontWeight: '600', fontFamily: 'Inter', color: '#ffffff', textAlign: 'left' },
                                { id: 'seat_info', type: 'text', z: 5, frame: { x: 8, y: 115, w: 40, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Seat Info', content: '@{{ticket.type}}', fontSize: 10, fontWeight: '500', fontFamily: 'Inter', color: '#e63946', textAlign: 'left' },
                                { id: 'price', type: 'text', z: 4, frame: { x: 50, y: 115, w: 25, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Price', content: '@{{ticket.price}}', fontSize: 11, fontWeight: 'bold', fontFamily: 'Inter', color: '#ffffff', textAlign: 'right' },
                                { id: 'qr', type: 'qr', z: 3, frame: { x: 48, y: 155, w: 28, h: 28 }, rotation: 0, opacity: 1, visible: true, name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#1e3a5f', qrBackground: '#ffffff' },
                                { id: 'ticket_id', type: 'text', z: 2, frame: { x: 8, y: 165, w: 38, h: 6 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket ID', content: '#@{{ticket.id}}', fontSize: 7, fontWeight: 'normal', fontFamily: 'Inter', color: '#8899aa', textAlign: 'left' },
                            ]
                        }
                    },
                    {
                        name: 'Theater Classic',
                        description: 'Elegant theater ticket',
                        preview: { bg: '#2c1810', elements: [
                            { style: 'top: 5%; left: 5%; right: 5%; bottom: 5%; border: 2px solid #d4a574; border-radius: 4px;' },
                            { style: 'top: 12%; left: 15%; width: 55%; height: 10%; background: #d4a574; border-radius: 2px;' },
                            { style: 'top: 26%; left: 15%; width: 40%; height: 5%; background: rgba(212,165,116,0.5); border-radius: 2px;' },
                            { style: 'bottom: 12%; left: 50%; transform: translateX(-50%); width: 25%; height: 20%; background: #f5f0e8; border-radius: 4px;' },
                        ]},
                        data: {
                            meta: { version: '1.0', dpi: 300, size_mm: { w: 80, h: 200 }, orientation: 'portrait', bleed_mm: { top: 3, right: 3, bottom: 3, left: 3 }, safe_area_mm: 5, background: { color: '#2c1810', image: '' }, baseTextColor: '#d4a574' },
                            assets: [], layers: [
                                { id: 'border', type: 'shape', z: 1, frame: { x: 4, y: 8, w: 72, h: 184 }, rotation: 0, opacity: 1, visible: true, name: 'Border', shapeKind: 'rect', fillColor: 'transparent', borderColor: '#d4a574', borderWidth: 2, borderRadius: 8 },
                                { id: 'event_name', type: 'text', z: 10, frame: { x: 10, y: 20, w: 60, h: 18 }, rotation: 0, opacity: 1, visible: true, name: 'Event Name', content: '@{{event.name}}', fontSize: 15, fontWeight: 'bold', fontFamily: 'Playfair Display', color: '#d4a574', textAlign: 'center' },
                                { id: 'venue', type: 'text', z: 9, frame: { x: 10, y: 42, w: 60, h: 10 }, rotation: 0, opacity: 1, visible: true, name: 'Venue', content: '@{{event.venue}}', fontSize: 9, fontWeight: 'normal', fontFamily: 'Inter', color: '#a08060', textAlign: 'center' },
                                { id: 'ornament', type: 'text', z: 8, frame: { x: 30, y: 55, w: 20, h: 8 }, rotation: 0, opacity: 0.5, visible: true, name: 'Ornament', content: '✦ ✦ ✦', fontSize: 8, fontWeight: 'normal', fontFamily: 'Inter', color: '#d4a574', textAlign: 'center' },
                                { id: 'date', type: 'text', z: 7, frame: { x: 10, y: 70, w: 60, h: 12 }, rotation: 0, opacity: 1, visible: true, name: 'Date', content: '@{{event.date}}', fontSize: 11, fontWeight: '600', fontFamily: 'Inter', color: '#f5f0e8', textAlign: 'center' },
                                { id: 'time', type: 'text', z: 6, frame: { x: 10, y: 84, w: 60, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Time', content: '@{{event.time}}', fontSize: 9, fontWeight: 'normal', fontFamily: 'Inter', color: '#a08060', textAlign: 'center' },
                                { id: 'holder', type: 'text', z: 5, frame: { x: 10, y: 105, w: 60, h: 12 }, rotation: 0, opacity: 1, visible: true, name: 'Holder', content: '@{{ticket.holder_name}}', fontSize: 11, fontWeight: '500', fontFamily: 'Inter', color: '#f5f0e8', textAlign: 'center' },
                                { id: 'type_price', type: 'text', z: 4, frame: { x: 10, y: 120, w: 60, h: 8 }, rotation: 0, opacity: 1, visible: true, name: 'Type & Price', content: '@{{ticket.type}} — @{{ticket.price}}', fontSize: 8, fontWeight: 'normal', fontFamily: 'Inter', color: '#d4a574', textAlign: 'center' },
                                { id: 'qr', type: 'qr', z: 3, frame: { x: 25, y: 145, w: 30, h: 30 }, rotation: 0, opacity: 1, visible: true, name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#2c1810', qrBackground: '#f5f0e8' },
                                { id: 'ticket_id', type: 'text', z: 2, frame: { x: 10, y: 180, w: 60, h: 6 }, rotation: 0, opacity: 1, visible: true, name: 'Ticket ID', content: '@{{ticket.id}}', fontSize: 6, fontWeight: 'normal', fontFamily: 'Inter', color: '#6b5040', textAlign: 'center' },
                            ]
                        }
                    },
                ],

                init() {
                    // Ensure required structures exist
                    if (!this.templateData.layers) {
                        this.templateData.layers = [];
                    }
                    if (!this.templateData.meta.version) {
                        this.templateData.meta.version = '1.0';
                    }
                    if (!this.templateData.meta.background) {
                        this.templateData.meta.background = { color: '#ffffff', image: '', positionX: 50, positionY: 50 };
                    }
                    if (this.templateData.meta.background.positionX === undefined) {
                        this.templateData.meta.background.positionX = 50;
                    }
                    if (this.templateData.meta.background.positionY === undefined) {
                        this.templateData.meta.background.positionY = 50;
                    }
                    if (!this.templateData.meta.baseTextColor) {
                        this.templateData.meta.baseTextColor = '#000000';
                    }
                    if (typeof this.templateData.meta.bleed_mm === 'number') {
                        const b = this.templateData.meta.bleed_mm;
                        this.templateData.meta.bleed_mm = { top: b, right: b, bottom: b, left: b };
                    }
                    if (!this.templateData.assets) {
                        this.templateData.assets = [];
                    }

                    // Add keyboard shortcuts
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Delete' && this.selectedLayerId && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                            this.deleteLayer(this.selectedLayerId);
                        }
                        if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
                            e.preventDefault();
                            this.saveTemplate();
                        }
                    });

                    // Mouse move/up for drag, resize, and rotate
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

                get canvasBackgroundStyle() {
                    const bg = this.templateData.meta.background || {};
                    let style = `background-color: ${bg.color || '#ffffff'};`;
                    if (bg.image) {
                        const posX = bg.positionX ?? 50;
                        const posY = bg.positionY ?? 50;
                        style += ` background-image: url('${bg.image}'); background-size: cover; background-position: ${posX}% ${posY}%;`;
                    }
                    return style;
                },

                generateId() {
                    return 'layer_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                },

                getNextZIndex() {
                    const usedIndexes = this.templateData.layers.map(l => l.z || 0);
                    let next = 1;
                    while (usedIndexes.includes(next)) {
                        next++;
                    }
                    return next;
                },

                addLayer(type, shapeKind = null) {
                    const id = this.generateId();
                    const z = this.getNextZIndex();
                    const baseColor = this.templateData.meta.baseTextColor || '#000000';

                    const defaults = {
                        text: { name: 'Text', content: 'New Text', fontSize: 12, fontWeight: 'normal', fontFamily: 'Inter', color: baseColor, textAlign: 'left' },
                        image: { name: 'Image', src: '', objectFit: 'contain' },
                        qr: { name: 'QR Code', qrData: '@{{qrcode}}', qrForeground: '#000000', qrBackground: '#ffffff' },
                        barcode: { name: 'Barcode', barcodeData: '{{barcode}}', barcodeForeground: '#000000', barcodeBackground: '#ffffff' },
                        shape: { name: shapeKind === 'line' ? 'Line' : 'Shape', shapeKind: shapeKind || 'rect', fillColor: shapeKind === 'line' ? 'transparent' : '#e5e7eb', borderColor: '#000000', borderWidth: shapeKind === 'line' ? 2 : 1, borderRadius: 0 },
                    };

                    const layer = {
                        id,
                        type,
                        z,
                        frame: { x: 10, y: 10, w: shapeKind === 'line' ? 50 : 30, h: shapeKind === 'line' ? 2 : 20 },
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

                // Layer drag-and-drop handlers
                startLayerDrag(event, layer) {
                    this.draggingLayerId = layer.id;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', layer.id);
                },

                endLayerDrag() {
                    this.draggingLayerId = null;
                    this.dragOverLayerId = null;
                    // Remove all drag-over classes
                    document.querySelectorAll('.layer-item').forEach(el => {
                        el.classList.remove('drag-over-top', 'drag-over-bottom');
                    });
                },

                handleLayerDragOver(event, layer) {
                    if (this.draggingLayerId === layer.id) return;
                    this.dragOverLayerId = layer.id;
                    const rect = event.target.closest('.layer-item').getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    const target = event.target.closest('.layer-item');
                    target.classList.remove('drag-over-top', 'drag-over-bottom');
                    if (event.clientY < midY) {
                        target.classList.add('drag-over-top');
                    } else {
                        target.classList.add('drag-over-bottom');
                    }
                },

                handleLayerDragLeave(event) {
                    const target = event.target.closest('.layer-item');
                    if (target) {
                        target.classList.remove('drag-over-top', 'drag-over-bottom');
                    }
                },

                handleLayerDrop(event, targetLayer) {
                    if (this.draggingLayerId === targetLayer.id) return;

                    const draggedLayer = this.templateData.layers.find(l => l.id === this.draggingLayerId);
                    if (!draggedLayer) return;

                    const rect = event.target.closest('.layer-item').getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    const insertAbove = event.clientY < midY;

                    // Get sorted layers (by z descending - top layer first)
                    const sorted = [...this.templateData.layers].sort((a, b) => (b.z || 0) - (a.z || 0));
                    const targetIdx = sorted.findIndex(l => l.id === targetLayer.id);
                    const draggedIdx = sorted.findIndex(l => l.id === this.draggingLayerId);

                    // Calculate new z-index based on drop position
                    if (insertAbove) {
                        // Insert before target (higher z-index)
                        if (targetIdx === 0) {
                            draggedLayer.z = (targetLayer.z || 0) + 1;
                        } else {
                            const above = sorted[targetIdx - 1];
                            draggedLayer.z = Math.floor(((above.z || 0) + (targetLayer.z || 0)) / 2) || (targetLayer.z || 0) + 1;
                        }
                    } else {
                        // Insert after target (lower z-index)
                        if (targetIdx === sorted.length - 1) {
                            draggedLayer.z = Math.max(0, (targetLayer.z || 0) - 1);
                        } else {
                            const below = sorted[targetIdx + 1];
                            draggedLayer.z = Math.floor(((targetLayer.z || 0) + (below.z || 0)) / 2);
                        }
                    }

                    // Normalize z-indexes to prevent collisions
                    this.normalizeZIndexes();
                    this.markChanged();
                    this.endLayerDrag();
                },

                normalizeZIndexes() {
                    const sorted = [...this.templateData.layers].sort((a, b) => (b.z || 0) - (a.z || 0));
                    sorted.forEach((layer, idx) => {
                        layer.z = sorted.length - idx;
                    });
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
                        fontFamily: layer.fontFamily || 'Inter',
                        color: layer.color || '#000000',
                        textAlign: layer.textAlign || 'left',
                        justifyContent: layer.textAlign === 'center' ? 'center' : layer.textAlign === 'right' ? 'flex-end' : 'flex-start',
                    };
                },

                getShapeStyle(layer) {
                    const kind = layer.shapeKind || 'rect';
                    let style = {};

                    if (kind === 'line') {
                        style = {
                            backgroundColor: 'transparent',
                            borderTop: `${layer.borderWidth || 2}px solid ${layer.borderColor || '#000000'}`,
                            height: '0',
                            marginTop: '50%',
                        };
                    } else if (kind === 'circle' || kind === 'ellipse') {
                        style = {
                            backgroundColor: layer.fillColor || '#e5e7eb',
                            border: `${layer.borderWidth || 1}px solid ${layer.borderColor || '#9ca3af'}`,
                            borderRadius: '50%',
                        };
                    } else {
                        style = {
                            backgroundColor: layer.fillColor || '#e5e7eb',
                            border: `${layer.borderWidth || 1}px solid ${layer.borderColor || '#9ca3af'}`,
                            borderRadius: (layer.borderRadius || 0) + 'px',
                        };
                    }

                    return style;
                },

                getDisplayContent(layer) {
                    const content = layer.content || 'Text';
                    // Replace variables with sample data
                    return content.replace(/\{\{([^}]+)\}\}/g, (match, path) => {
                        const keys = path.trim().split('.');
                        let value = sampleData;
                        for (const key of keys) {
                            if (value && typeof value === 'object' && key in value) {
                                value = value[key];
                            } else {
                                return match; // Keep original if not found
                            }
                        }
                        return value || match;
                    });
                },

                startDrag(event, layer) {
                    if (event.target.classList.contains('resize-handle') || event.target.classList.contains('rotate-handle')) return;

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

                startRotate(event, layer) {
                    const rect = event.target.closest('.layer-element').getBoundingClientRect();
                    const centerX = rect.left + rect.width / 2;
                    const centerY = rect.top + rect.height / 2;

                    this.rotateState = {
                        layerId: layer.id,
                        centerX,
                        centerY,
                        initialRotation: layer.rotation || 0,
                        startAngle: Math.atan2(event.clientY - centerY, event.clientX - centerX) * 180 / Math.PI,
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

                    if (this.rotateState) {
                        const layer = this.templateData.layers.find(l => l.id === this.rotateState.layerId);
                        if (layer) {
                            const { centerX, centerY, initialRotation, startAngle } = this.rotateState;
                            const currentAngle = Math.atan2(event.clientY - centerY, event.clientX - centerX) * 180 / Math.PI;
                            let newRotation = initialRotation + (currentAngle - startAngle);
                            // Normalize to 0-360
                            newRotation = ((newRotation % 360) + 360) % 360;
                            layer.rotation = Math.round(newRotation);
                        }
                    }
                },

                handleMouseUp() {
                    if (this.dragState || this.resizeState || this.rotateState) {
                        this.markChanged();
                    }
                    this.dragState = null;
                    this.resizeState = null;
                    this.rotateState = null;
                },

                // Image upload handlers
                handleBgImageDrop(event) {
                    this.bgDragOver = false;
                    const file = event.dataTransfer.files[0];
                    if (file && file.type.startsWith('image/')) {
                        this.uploadImage(file, 'background');
                    }
                },

                handleBgImageSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.uploadImage(file, 'background');
                    }
                    event.target.value = '';
                },

                handleLayerImageDrop(event) {
                    this.imageDragOver = false;
                    const file = event.dataTransfer.files[0];
                    if (file && file.type.startsWith('image/') && this.selectedLayer) {
                        this.uploadImage(file, 'layer');
                    }
                },

                handleLayerImageSelect(event) {
                    const file = event.target.files[0];
                    if (file && this.selectedLayer) {
                        this.uploadImage(file, 'layer');
                    }
                    event.target.value = '';
                },

                async uploadImage(file, target) {
                    // For now, convert to base64 (in production, upload to server)
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        if (target === 'background') {
                            this.templateData.meta.background.image = e.target.result;
                        } else if (target === 'layer' && this.selectedLayer) {
                            this.selectedLayer.src = e.target.result;
                        }
                        this.markChanged();
                    };
                    reader.readAsDataURL(file);
                },

                markChanged() {
                    this.hasUnsavedChanges = true;
                },

                applyBaseTextColor() {
                    const baseColor = this.templateData.meta.baseTextColor || '#000000';
                    this.templateData.layers.forEach(layer => {
                        if (layer.type === 'text') {
                            layer.color = baseColor;
                        }
                    });
                    this.markChanged();
                    this.showMessage('Base text color applied to all text layers', 'success');
                },

                loadTemplate(preset) {
                    if (this.hasUnsavedChanges) {
                        if (!confirm('You have unsaved changes. Are you sure you want to load a new template?')) {
                            return;
                        }
                    }

                    // Deep clone the preset data to avoid reference issues
                    this.templateData = JSON.parse(JSON.stringify(preset.data));
                    this.selectedLayerId = null;
                    this.showTemplatesModal = false;
                    this.hasUnsavedChanges = true;
                    this.showMessage(`Template "${preset.name}" loaded`, 'success');
                },

                copyVariable(placeholder) {
                    // If a text layer is selected, insert at cursor position
                    if (this.selectedLayer && this.selectedLayer.type === 'text') {
                        const textarea = this.$refs.contentTextarea;
                        if (textarea) {
                            const start = textarea.selectionStart || 0;
                            const end = textarea.selectionEnd || 0;
                            const text = this.selectedLayer.content || '';
                            this.selectedLayer.content = text.substring(0, start) + placeholder + text.substring(end);
                            this.markChanged();

                            this.$nextTick(() => {
                                textarea.focus();
                                const newPos = start + placeholder.length;
                                textarea.setSelectionRange(newPos, newPos);
                            });
                            this.showMessage('Variable inserted', 'success');
                            return;
                        }
                    }
                    // Fallback: copy to clipboard
                    navigator.clipboard.writeText(placeholder);
                    this.showMessage('Copied to clipboard', 'success');
                },

                wrapSelectedText(prefix, suffix) {
                    const textarea = this.$refs.contentTextarea;
                    if (!textarea || !this.selectedLayer) return;

                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const text = this.selectedLayer.content || '';
                    const selectedText = text.substring(start, end);

                    if (selectedText) {
                        // Wrap selected text
                        const newText = text.substring(0, start) + prefix + selectedText + suffix + text.substring(end);
                        this.selectedLayer.content = newText;
                        this.markChanged();

                        // Restore cursor position after the wrapped text
                        this.$nextTick(() => {
                            textarea.focus();
                            textarea.setSelectionRange(start + prefix.length, end + prefix.length);
                        });
                    } else {
                        // Insert placeholder at cursor
                        const newText = text.substring(0, start) + prefix + suffix + text.substring(end);
                        this.selectedLayer.content = newText;
                        this.markChanged();

                        // Position cursor between prefix and suffix
                        this.$nextTick(() => {
                            textarea.focus();
                            textarea.setSelectionRange(start + prefix.length, start + prefix.length);
                        });
                    }
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
