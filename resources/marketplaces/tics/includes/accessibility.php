<!-- Accessibility Widget -->
<!-- Floating Button -->
<button
    id="accessibilityToggle"
    class="fixed bottom-6 right-6 z-[9999] w-14 h-14 bg-gray-900 hover:bg-gray-800 text-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110 focus:outline-none focus:ring-4 focus:ring-gray-900/30"
    aria-label="Deschide meniul de accesibilitate"
    title="Accesibilitate"
>
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
    </svg>
</button>

<!-- Accessibility Modal -->
<div id="accessibilityModal" class="fixed inset-0 z-[10000] hidden" role="dialog" aria-modal="true" aria-labelledby="accessibilityTitle">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" id="accessibilityOverlay"></div>

    <!-- Modal Content -->
    <div class="absolute right-0 top-0 bottom-0 w-full max-w-md bg-white shadow-2xl overflow-y-auto transform translate-x-full transition-transform duration-300" id="accessibilityPanel">
        <!-- Header -->
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 id="accessibilityTitle" class="font-semibold text-gray-900">Accesibilitate</h2>
                    <p class="text-xs text-gray-500">Personalizează experiența ta</p>
                </div>
            </div>
            <button id="accessibilityClose" class="p-2 hover:bg-gray-100 rounded-full transition-colors" aria-label="Închide">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Settings -->
        <div class="p-6 space-y-6">
            <!-- Font Size -->
            <section>
                <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>
                    Dimensiune font
                </h3>
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-4">
                    <button id="fontDecrease" class="w-10 h-10 bg-white border border-gray-200 rounded-lg flex items-center justify-center hover:bg-gray-100 transition-colors text-lg font-bold" aria-label="Micșorează fontul">
                        A-
                    </button>
                    <div class="flex-1 text-center">
                        <span id="fontSizeLabel" class="text-lg font-semibold text-gray-900">100%</span>
                    </div>
                    <button id="fontIncrease" class="w-10 h-10 bg-white border border-gray-200 rounded-lg flex items-center justify-center hover:bg-gray-100 transition-colors text-lg font-bold" aria-label="Mărește fontul">
                        A+
                    </button>
                </div>
                <input type="range" id="fontSizeRange" min="80" max="150" value="100" step="10" class="w-full mt-3 accent-gray-900">
            </section>

            <!-- Text Formatting -->
            <section>
                <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Formatare text
                </h3>
                <div class="space-y-3">
                    <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-sm underline font-medium">A</span>
                            <span class="text-sm text-gray-700">Subliniază linkurile</span>
                        </div>
                        <div class="relative">
                            <input type="checkbox" id="underlineLinks" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-gray-900 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                        </div>
                    </label>
                    <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-sm font-bold">H</span>
                            <span class="text-sm text-gray-700">Subliniază titlurile</span>
                        </div>
                        <div class="relative">
                            <input type="checkbox" id="underlineHeadings" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-gray-900 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                        </div>
                    </label>
                </div>
            </section>

            <!-- Animations -->
            <section>
                <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Animații
                </h3>
                <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <span class="text-sm text-gray-700">Dezactivează animațiile</span>
                    </div>
                    <div class="relative">
                        <input type="checkbox" id="disableAnimations" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-gray-900 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                    </div>
                </label>
            </section>

            <!-- Color Adjustments -->
            <section>
                <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                    </svg>
                    Ajustare culori
                </h3>
                <div class="grid grid-cols-3 gap-3">
                    <button data-color-mode="normal" class="color-mode-btn p-4 bg-white border-2 border-gray-900 rounded-xl flex flex-col items-center gap-2 transition-all">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500"></div>
                        <span class="text-xs font-medium text-gray-700">Normal</span>
                    </button>
                    <button data-color-mode="grayscale" class="color-mode-btn p-4 bg-white border-2 border-gray-200 rounded-xl flex flex-col items-center gap-2 hover:border-gray-400 transition-all">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-gray-400 via-gray-500 to-gray-600"></div>
                        <span class="text-xs font-medium text-gray-700">Alb-negru</span>
                    </button>
                    <button data-color-mode="high-contrast" class="color-mode-btn p-4 bg-white border-2 border-gray-200 rounded-xl flex flex-col items-center gap-2 hover:border-gray-400 transition-all">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-black via-yellow-400 to-white border border-gray-200"></div>
                        <span class="text-xs font-medium text-gray-700">Contrast</span>
                    </button>
                    <button data-color-mode="invert" class="color-mode-btn p-4 bg-white border-2 border-gray-200 rounded-xl flex flex-col items-center gap-2 hover:border-gray-400 transition-all col-span-3">
                        <div class="w-8 h-8 rounded-full bg-black border border-gray-200"></div>
                        <span class="text-xs font-medium text-gray-700">Inversează culorile</span>
                    </button>
                </div>
            </section>

            <!-- Cursor -->
            <section>
                <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                    </svg>
                    Cursor
                </h3>
                <div class="space-y-3">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm text-gray-700">Mărime cursor</span>
                            <span id="cursorSizeLabel" class="text-sm font-medium text-gray-900">Normal</span>
                        </div>
                        <div class="flex gap-2">
                            <button data-cursor-size="normal" class="cursor-size-btn flex-1 py-2 px-4 bg-gray-900 text-white text-sm font-medium rounded-lg transition-colors">Normal</button>
                            <button data-cursor-size="large" class="cursor-size-btn flex-1 py-2 px-4 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">Mare</button>
                            <button data-cursor-size="xlarge" class="cursor-size-btn flex-1 py-2 px-4 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">Extra mare</button>
                        </div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm text-gray-700">Culoare cursor</span>
                        </div>
                        <div class="flex gap-2">
                            <button data-cursor-color="default" class="cursor-color-btn w-10 h-10 rounded-lg border-2 border-gray-900 bg-white flex items-center justify-center transition-all" title="Implicit">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2z"/></svg>
                            </button>
                            <button data-cursor-color="black" class="cursor-color-btn w-10 h-10 rounded-lg border-2 border-gray-200 bg-black hover:border-gray-400 transition-all" title="Negru"></button>
                            <button data-cursor-color="white" class="cursor-color-btn w-10 h-10 rounded-lg border-2 border-gray-200 bg-white hover:border-gray-400 transition-all" title="Alb"></button>
                            <button data-cursor-color="yellow" class="cursor-color-btn w-10 h-10 rounded-lg border-2 border-gray-200 bg-yellow-400 hover:border-gray-400 transition-all" title="Galben"></button>
                            <button data-cursor-color="blue" class="cursor-color-btn w-10 h-10 rounded-lg border-2 border-gray-200 bg-blue-500 hover:border-gray-400 transition-all" title="Albastru"></button>
                            <button data-cursor-color="red" class="cursor-color-btn w-10 h-10 rounded-lg border-2 border-gray-200 bg-red-500 hover:border-gray-400 transition-all" title="Roșu"></button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-white border-t border-gray-200 p-4">
            <button id="accessibilityReset" class="w-full py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Resetează toate setările
            </button>
        </div>
    </div>
</div>

<!-- Include Accessibility JS -->
<script src="<?= asset('assets/js/accessibility.js') ?>"></script>
