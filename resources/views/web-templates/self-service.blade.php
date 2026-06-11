<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personalizare — {{ $customization->label ?? $template->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white border-b">
        <div class="max-w-3xl mx-auto px-4 py-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Personalizare Site</h1>
                    <p class="text-sm text-gray-500">{{ $customization->label ?? $template->name }}</p>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Preview link --}}
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <p class="text-sm text-indigo-700">
                    <span class="font-medium">Link preview:</span>
                    <a href="{{ route('web-template.customized-preview', ['templateSlug' => $template->slug, 'token' => $customization->unique_token]) }}"
                       target="_blank" class="underline hover:text-indigo-900">
                        Deschide preview &rarr;
                    </a>
                </p>
            </div>

            @php
                $grouped = collect($allowedFields)->groupBy('group');
                $currentData = $customization->customization_data ?? [];
            @endphp

            @foreach($grouped as $group => $fields)
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    @if($group)
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 capitalize">{{ $group }}</h2>
                    @else
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Setări generale</h2>
                    @endif

                    <div class="space-y-4">
                        @foreach($fields as $field)
                            @php
                                $key = $field['key'];
                                $label = $field['label'] ?? $key;
                                $type = $field['type'] ?? 'text';
                                $currentValue = $currentData[$key] ?? ($field['default'] ?? '');
                            @endphp

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>

                                @if($type === 'textarea')
                                    <textarea name="fields[{{ $key }}]" rows="3"
                                              class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ $currentValue }}</textarea>

                                @elseif($type === 'color')
                                    <div class="flex items-center gap-3">
                                        <input type="color" name="fields[{{ $key }}]" value="{{ $currentValue }}"
                                               class="w-12 h-10 rounded border cursor-pointer">
                                        <input type="text" value="{{ $currentValue }}" readonly
                                               class="text-sm text-gray-500 bg-gray-50 px-3 py-2 rounded border w-28"
                                               x-data @input.debounce="$el.previousElementSibling.value">
                                    </div>

                                @elseif($type === 'image')
                                    @if($currentValue)
                                        <div class="mb-2">
                                            <img src="{{ str_starts_with($currentValue, 'http') ? $currentValue : Storage::url($currentValue) }}"
                                                 alt="{{ $label }}" class="h-16 w-auto rounded border">
                                        </div>
                                    @endif
                                    <input type="file" name="fields[{{ $key }}]" accept="image/*"
                                           class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">

                                @elseif($type === 'url')
                                    <input type="url" name="fields[{{ $key }}]" value="{{ $currentValue }}"
                                           placeholder="https://"
                                           class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">

                                @elseif($type === 'select')
                                    <select name="fields[{{ $key }}]"
                                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                        @foreach(($field['options'] ?? []) as $option)
                                            <option value="{{ $option }}" @selected($currentValue === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>

                                @elseif($type === 'toggle')
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="fields[{{ $key }}]" value="0">
                                        <input type="checkbox" name="fields[{{ $key }}]" value="1"
                                               @checked($currentValue) class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>

                                @else
                                    <input type="text" name="fields[{{ $key }}]" value="{{ $currentValue }}"
                                           class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end gap-3">
                <a href="{{ route('web-template.customized-preview', ['templateSlug' => $template->slug, 'token' => $customization->unique_token]) }}"
                   target="_blank"
                   class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition text-sm">
                    Vizualizează Preview
                </a>
                <button type="submit"
                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition text-sm">
                    Salvează Modificările
                </button>
            </div>
        </form>

        <p class="text-center text-xs text-gray-400 mt-8">
            Powered by <a href="https://tixello.ro" class="text-indigo-500 hover:underline">Tixello</a>
        </p>
    </main>
</body>
</html>
