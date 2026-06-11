<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compară Template-uri — Tixello</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Comparare Template-uri</h1>
                <p class="text-gray-500 text-sm mt-1">{{ $templates->count() }} template-uri selectate</p>
            </div>
            <a href="{{ route('web-template.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Înapoi la galerie</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 md:grid-cols-{{ $templates->count() }} gap-6">
            @foreach($templates as $template)
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    {{-- Header --}}
                    @if($template->thumbnail)
                        <div class="aspect-video bg-gray-100 overflow-hidden">
                            <img src="{{ Storage::url($template->thumbnail) }}" alt="{{ $template->name }}" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="aspect-video bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                            <span class="text-white text-4xl font-bold opacity-30">{{ substr($template->name, 0, 2) }}</span>
                        </div>
                    @endif

                    <div class="p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-1">{{ $template->name }}</h2>
                        <p class="text-sm text-gray-500 mb-4">{{ $template->description }}</p>

                        {{-- Details table --}}
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Categorie</dt>
                                <dd class="font-medium">{{ $template->category->label() }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Versiune</dt>
                                <dd class="font-medium">v{{ $template->version }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Personalizări</dt>
                                <dd class="font-medium">{{ $template->customizations()->count() }}</dd>
                            </div>
                            @if($template->tech_stack)
                            <div>
                                <dt class="text-gray-500 mb-1">Tech Stack</dt>
                                <dd class="flex flex-wrap gap-1">
                                    @foreach($template->tech_stack as $tech)
                                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded">{{ $tech }}</span>
                                    @endforeach
                                </dd>
                            </div>
                            @endif
                            @if($template->compatible_microservices)
                            <div>
                                <dt class="text-gray-500 mb-1">Microservicii</dt>
                                <dd class="flex flex-wrap gap-1">
                                    @foreach($template->compatible_microservices as $ms)
                                        <span class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded">{{ $ms }}</span>
                                    @endforeach
                                </dd>
                            </div>
                            @endif
                            @if($template->customizable_fields)
                            <div>
                                <dt class="text-gray-500 mb-1">Câmpuri personalizabile</dt>
                                <dd class="text-gray-700">{{ count($template->customizable_fields) }} câmpuri</dd>
                            </div>
                            @endif
                            @if($template->color_scheme)
                            <div>
                                <dt class="text-gray-500 mb-1">Culori</dt>
                                <dd class="flex gap-1">
                                    @foreach($template->color_scheme as $key => $color)
                                        @if(str_starts_with($color, '#'))
                                            <div class="w-6 h-6 rounded-full border" style="background-color: {{ $color }}" title="{{ $key }}: {{ $color }}"></div>
                                        @endif
                                    @endforeach
                                </dd>
                            </div>
                            @endif
                        </dl>

                        <div class="mt-6 flex gap-2">
                            <a href="{{ route('web-template.preview', $template->slug) }}" target="_blank"
                               class="flex-1 text-center bg-indigo-600 text-white text-sm py-2 px-4 rounded-lg hover:bg-indigo-700 transition">
                                Preview
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
    </main>
</body>
</html>
