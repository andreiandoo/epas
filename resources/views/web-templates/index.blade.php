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
    <header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <h1 class="text-3xl font-bold text-gray-900">Tixello Web Templates</h1>
            <p class="text-gray-500 mt-1">Galerie de template-uri pentru platformă — selectează, personalizează, lansează</p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-10">
        @foreach($templatesByCategory as $categoryValue => $templates)
            @php
                $category = \App\Enums\WebTemplateCategory::from($categoryValue);
            @endphp
            <section class="mb-12">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 flex items-center gap-2">
                    <span>{{ $category->label() }}</span>
                    <span class="text-sm bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ $templates->count() }}</span>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($templates as $template)
                        <div class="bg-white rounded-xl shadow-sm border hover:shadow-md transition overflow-hidden group">
                            @if($template->thumbnail)
                                <div class="aspect-video bg-gray-100 overflow-hidden">
                                    <img src="{{ Storage::url($template->thumbnail) }}" alt="{{ $template->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                </div>
                            @else
                                <div class="aspect-video bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                    <span class="text-white text-4xl font-bold opacity-30">{{ substr($template->name, 0, 2) }}</span>
                                </div>
                            @endif
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold text-lg text-gray-900">{{ $template->name }}</h3>
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">v{{ $template->version }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mb-4 line-clamp-2">{{ $template->description }}</p>

                                @if($template->tech_stack)
                                    <div class="flex flex-wrap gap-1 mb-4">
                                        @foreach($template->tech_stack as $tech)
                                            <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded">{{ $tech }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex gap-2">
                                    <a href="{{ route('web-template.preview', $template->slug) }}"
                                       class="flex-1 text-center bg-indigo-600 text-white text-sm py-2 px-4 rounded-lg hover:bg-indigo-700 transition"
                                       target="_blank">
                                        Preview Demo
                                    </a>
                                    <a href="/admin/web-templates/{{ $template->id }}/edit"
                                       class="text-center bg-gray-100 text-gray-700 text-sm py-2 px-4 rounded-lg hover:bg-gray-200 transition">
                                        Editează
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </main>
</body>
</html>
