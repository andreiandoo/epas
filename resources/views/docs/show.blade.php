@extends('layouts.public')

@section('title', $doc->title . ' - Documentation')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            <!-- Sidebar -->
            <aside class="hidden lg:block lg:col-span-3">
                <div class="sticky top-8 bg-white rounded-lg shadow-md p-4">
                    <h3 class="font-semibold text-gray-900 mb-4">Navigation</h3>
                    <nav class="space-y-2">
                        @foreach($tableOfContents as $category)
                        @if($category->docs->count())
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">
                                {{ $category->name }}
                            </h4>
                            <ul class="space-y-1">
                                @foreach($category->docs as $navDoc)
                                <li>
                                    <a href="{{ route('docs.show', $navDoc->slug) }}"
                                       class="block px-2 py-1 text-sm rounded
                                              {{ $navDoc->id === $doc->id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                        {{ $navDoc->title }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        @endforeach
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="lg:col-span-9">
                <!-- Breadcrumb -->
                <nav class="mb-6">
                    <ol class="flex items-center space-x-2 text-sm text-gray-500">
                        <li><a href="{{ route('docs.index') }}" class="hover:text-indigo-600">Documentation</a></li>
                        <li><span class="mx-2">/</span></li>
                        <li><a href="{{ route('docs.category', $doc->category->slug) }}" class="hover:text-indigo-600">{{ $doc->category->name }}</a></li>
                        <li><span class="mx-2">/</span></li>
                        <li class="text-gray-900">{{ $doc->title }}</li>
                    </ol>
                </nav>

                <!-- Article -->
                <article class="bg-white rounded-lg shadow-md">
                    <div class="p-8">
                        <!-- Header -->
                        <header class="mb-8 pb-6 border-b border-gray-200">
                            <div class="flex items-center gap-2 mb-4">
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
                            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $doc->title }}</h1>
                            @if($doc->excerpt)
                            <p class="text-lg text-gray-600">{{ $doc->excerpt }}</p>
                            @endif
                            <div class="mt-4 flex items-center text-sm text-gray-500 space-x-4">
                                @if($doc->author)
                                <span>By {{ $doc->author }}</span>
                                @endif
                                <span>{{ $doc->read_time }} min read</span>
                                <span>Updated {{ $doc->updated_at->diffForHumans() }}</span>
                            </div>
                        </header>

                        <!-- Content -->
                        <div class="prose prose-indigo max-w-none">
                            {!! $doc->content !!}
                        </div>

                        <!-- Tags -->
                        @if($doc->tags && count($doc->tags))
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Tags</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($doc->tags as $tag)
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Children Docs -->
                        @if($doc->children->count())
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-500 mb-4">Related Pages</h4>
                            <ul class="space-y-2">
                                @foreach($doc->children as $child)
                                <li>
                                    <a href="{{ route('docs.show', $child->slug) }}" class="text-indigo-600 hover:text-indigo-800">
                                        {{ $child->title }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                </article>

                <!-- Related Documentation -->
                @if($related->count())
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Documentation</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($related as $relatedDoc)
                        <a href="{{ route('docs.show', $relatedDoc->slug) }}" class="block bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-4">
                            <h4 class="font-medium text-gray-900 mb-1">{{ $relatedDoc->title }}</h4>
                            <p class="text-sm text-gray-600">{{ Str::limit($relatedDoc->excerpt, 80) }}</p>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </main>
        </div>
    </div>
</div>
@endsection
