@if (count($items))
    <div class="ep-vf-todo">
        @foreach ($items as $it)
            <a href="#{{ $it['id'] }}"><span class="ep-todo"></span>{{ $it['label'] }}</a>
        @endforeach
    </div>
@else
    <div class="ep-vf-done">Totul este completat.</div>
@endif
