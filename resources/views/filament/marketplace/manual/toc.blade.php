{{-- Page manual table of contents (drawer + hub page). --}}
@foreach (collect($manual['chapters'])->groupBy('chapter') as $chapterName => $parts)
    <div class="epm-toc-group" data-epm-toc-group>
        @if ($parts->count() > 1)
            <p class="epm-toc-heading">{{ $chapterName }}</p>
        @endif
        @foreach ($parts as $part)
            <a href="#epm-ch-{{ $part['id'] }}"
               class="epm-toc-link {{ $parts->count() > 1 ? 'epm-toc-link--sub' : '' }}"
               data-epm-goto="epm-ch-{{ $part['id'] }}"
               data-epm-toc-item="{{ $part['id'] }}">{{ $part['title'] }}</a>
        @endforeach
    </div>
@endforeach
