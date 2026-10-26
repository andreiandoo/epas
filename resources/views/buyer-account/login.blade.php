@extends('buyer-account.layout')
@section('title', 'Contul meu')

@section('content')
<div class="narrow">
    <div class="card">
        @if ($sent)
            <h1>Verifică-ți emailul</h1>
            <p class="muted">Dacă există un cont pentru <strong>{{ $sent }}</strong>, ți-am trimis linkul de intrare. Dacă nu există, ți-am trimis linkul prin care îl creezi. Linkul e valabil 30 de minute.</p>
            <p class="muted small">Nu vezi emailul? Uită-te și la Spam sau <a href="{{ route('buyer-account.show') }}">încearcă din nou</a>.</p>
        @else
            @if (session('status'))<p class="notice">{{ session('status') }}</p>@endif
            <h1>Biletele tale, într-un singur loc</h1>
            <p class="muted">În contul Tixello vezi biletele cumpărate de la orice organizator care vinde prin Tixello. Intri fără parolă: îți trimitem un link pe email.</p>
            <form method="post" action="{{ route('buyer-account.link') }}" novalidate>
                @csrf
                <label class="field-label" for="email">Adresa cu care ai cumpărat biletele</label>
                <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="{{ old('email') }}" @error('email') aria-invalid="true" @enderror required>
                @error('email')<p class="err">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary full">Trimite-mi linkul</button>
            </form>
        @endif
    </div>
</div>
@endsection
