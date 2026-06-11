@extends('layouts.public')

@section('title', $currentCategory->name . ' - Documentation')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="{{ route('docs.index') }}" class="hover:text-indigo-600">Documentation</a></li>
                <li><span class="mx-2">/</span></li>
                <li class="text-gray-900">{{ $currentCategory->name }}</li>
            </ol>
        </nav>

        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            <!-- Sidebar -->
            <aside class="hidden lg:block lg:col-span-3">
                <div class="sticky top-8 bg-white rounded-lg shadow-md p-4">
                    <h3 class="font-semibold text-gray-900 mb-4">Categories</h3>
                    <nav class="space-y-1">
                        @foreach($categories as $category)
                        <a href="{{ route('docs.category', $category->slug) }}"
                           class="block px-3 py-2 rounded-md text-sm
                                  {{ $category->id === $currentCategory->id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                            {{ $category->name }}
                            <span class="float-right text-gray-400">{{ $category->docs_count }}</span>
                        </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="lg:col-span-9">
                <div class="bg-white rounded-lg shadow-md p-8 mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4" style="border-left: 4px solid {{ $currentCategory->color }}; padding-left: 1rem;">
                        {{ $currentCategory->name }}
                    </h1>
                    @if($currentCategory->description)
                    <p class="text-lg text-gray-600">{{ $currentCategory->description }}</p>
                    @endif
                </div>

                @if($docs->count())
                <div class="space-y-4">
                    @foreach($docs as $doc)
                    <a href="{{ route('docs.show', $doc->slug) }}" class="block bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                        @if($doc->type === 'api') bg-blue-100 text-blue-700
                                        @elseif($doc->type === 'component') bg-green-100 text-green-700
                                        @elseif($doc->type === 'module') bg-yellow-100 text-yellow-700
                                        @else bg-gray-100 text-gray-700
                                        @endif">
                                        {{ $doc->getTypeLabel() }}
                                    </span>
                                    <span class="text-xs text-gray-500">v{{ $doc->version }}</span>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $doc->title }}</h3>
                                @if($doc->excerpt)
                                <p class="text-gray-600">{{ Str::limit($doc->excerpt, 150) }}</p>
                                @endif
                                <div class="mt-3 text-xs text-gray-400">
                                    {{ $doc->read_time }} min read &middot; Updated {{ $doc->updated_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $docs->links() }}
                </div>
                @else
                <div class="bg-white rounded-lg shadow-md p-8 text-center text-gray-500">
                    No documentation available in this category yet.
                </div>
                @endif
            </main>
        </div>
    </div>
</div>
@endsection
