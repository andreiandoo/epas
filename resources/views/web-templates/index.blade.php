<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Templates — Tixello</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div x-data="templateGallery()">
        <header class="bg-white border-b sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Tixello Web Templates</h1>
                        <p class="text-gray-500 mt-1">Galerie de template-uri — selectează, personalizează, lansează</p>
                    </div>
                    <div x-show="compareList.length >= 2" x-transition>
                        <a :href="'/web-templates/compare?templates=' + compareList.join(',')"
                           class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Compară (<span x-text="compareList.length"></span>)
                        </a>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Search --}}
                    <div class="relative flex-1 min-w-[200px] max-w-md">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" x-model="search" placeholder="Caută template..."
                               class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>

                    {{-- Category filter --}}
                    <div class="flex flex-wrap gap-1">
                        <button @click="selectedCategory = ''"
                                :class="selectedCategory === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="text-xs px-3 py-1.5 rounded-full transition">
                            Toate
                        </button>
                        <template x-for="cat in categories" :key="cat.value">
                            <button @click="selectedCategory = cat.value"
                                    :class="selectedCategory === cat.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="text-xs px-3 py-1.5 rounded-full transition"
                                    x-text="cat.label"></button>
                        </template>
                    </div>

                    {{-- Tech stack filter --}}
                    <select x-model="selectedTech" class="text-xs border rounded-lg px-3 py-1.5 text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">Orice tech</option>
                        <template x-for="tech in allTechStack" :key="tech">
                            <option :value="tech" x-text="tech"></option>
                        </template>
                    </select>

                    {{-- Featured filter --}}
                    <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                        <input type="checkbox" x-model="showFeaturedOnly" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Featured
                    </label>

                    <span class="text-xs text-gray-400 ml-auto" x-text="filteredTemplates.length + ' template-uri'"></span>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-10">
            {{-- No results --}}
            <div x-show="filteredTemplates.length === 0" class="text-center py-20 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-lg">Niciun template găsit</p>
                <p class="text-sm mt-1">Încearcă alte filtre sau caută altceva.</p>
            </div>

            {{-- Templates grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="template in filteredTemplates" :key="template.slug">
                    <div class="bg-white rounded-xl shadow-sm border hover:shadow-md transition overflow-hidden group relative">
                        {{-- Compare checkbox --}}
                        <label class="absolute top-3 left-3 z-10 cursor-pointer" @click.stop>
                            <input type="checkbox" :value="template.slug"
                                   :checked="compareList.includes(template.slug)"
                                   @change="toggleCompare(template.slug)"
                                   :disabled="!compareList.includes(template.slug) && compareList.length >= 3"
                                   class="rounded border-white/70 text-indigo-600 focus:ring-indigo-500 w-5 h-5 shadow">
                        </label>

                        {{-- Thumbnail --}}
                        <div x-show="template.thumbnail" class="aspect-video bg-gray-100 overflow-hidden">
                            <img :src="template.thumbnail" :alt="template.name" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </div>
                        <div x-show="!template.thumbnail" class="aspect-video bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                            <span class="text-white text-4xl font-bold opacity-30" x-text="template.name.substring(0, 2)"></span>
                        </div>

                        <div class="p-5">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-lg text-gray-900" x-text="template.name"></h3>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded" x-text="'v' + template.version"></span>
                            </div>

                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs px-2 py-0.5 rounded-full" :class="categoryColor(template.category)" x-text="template.category_label"></span>
                                <span x-show="template.is_featured" class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Featured</span>
                            </div>

                            <p class="text-sm text-gray-500 mb-4 line-clamp-2" x-text="template.description"></p>

                            <div x-show="template.tech_stack && template.tech_stack.length > 0" class="flex flex-wrap gap-1 mb-4">
                                <template x-for="tech in template.tech_stack" :key="tech">
                                    <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded" x-text="tech"></span>
                                </template>
                            </div>

                            <div class="flex gap-2">
                                <a :href="'/web-templates/' + template.slug + '/preview'" target="_blank"
                                   class="flex-1 text-center bg-indigo-600 text-white text-sm py-2 px-4 rounded-lg hover:bg-indigo-700 transition">
                                    Preview Demo
                                </a>
                                <a :href="'/admin/web-templates/' + template.id + '/edit'"
                                   class="text-center bg-gray-100 text-gray-700 text-sm py-2 px-4 rounded-lg hover:bg-gray-200 transition">
                                    Editează
                                </a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>

    <script>
        function templateGallery() {
            const allTemplates = @json($templates->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'category' => $t->category->value,
                'category_label' => $t->category->label(),
                'description' => $t->description,
                'thumbnail' => $t->thumbnail ? '/storage/' . $t->thumbnail : null,
                'tech_stack' => $t->tech_stack ?? [],
                'version' => $t->version,
                'is_featured' => $t->is_featured,
                'is_active' => $t->is_active,
            ])->values());

            const categories = @json(collect(\App\Enums\WebTemplateCategory::cases())->map(fn($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values());

            // Collect all unique tech stack items
            const allTech = [...new Set(allTemplates.flatMap(t => t.tech_stack || []))].sort();

            return {
                templates: allTemplates,
                categories: categories,
                allTechStack: allTech,
                search: '',
                selectedCategory: '',
                selectedTech: '',
                showFeaturedOnly: false,
                compareList: [],

                get filteredTemplates() {
                    return this.templates.filter(t => {
                        if (this.search && !t.name.toLowerCase().includes(this.search.toLowerCase()) &&
                            !t.description?.toLowerCase().includes(this.search.toLowerCase())) return false;
                        if (this.selectedCategory && t.category !== this.selectedCategory) return false;
                        if (this.selectedTech && !(t.tech_stack || []).includes(this.selectedTech)) return false;
                        if (this.showFeaturedOnly && !t.is_featured) return false;
                        return true;
                    });
                },

                toggleCompare(slug) {
                    const idx = this.compareList.indexOf(slug);
                    if (idx >= 0) {
                        this.compareList.splice(idx, 1);
                    } else if (this.compareList.length < 3) {
                        this.compareList.push(slug);
                    }
                },

                categoryColor(cat) {
                    const colors = {
                        'simple-organizer': 'bg-blue-100 text-blue-700',
                        'marketplace': 'bg-green-100 text-green-700',
                        'artist-agency': 'bg-amber-100 text-amber-700',
                        'theater': 'bg-red-100 text-red-700',
                        'festival': 'bg-purple-100 text-purple-700',
                        'stadium': 'bg-gray-200 text-gray-700',
                    };
                    return colors[cat] || 'bg-gray-100 text-gray-600';
                }
            };
        }
    </script>
</body>
</html>
