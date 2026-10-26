@extends('buyer-account.layout')
@section('title', 'Creează contul')

@section('content')
<div class="narrow">
    <div class="card">
        <h1>Creează-ți contul Tixello</h1>
        <p class="muted">Contul e pentru <strong>{{ $email }}</strong>. În el vezi biletele cumpărate cu această adresă de la organizatorii care vând prin Tixello.</p>
        <form method="post" action="{{ $action }}" novalidate>
            @csrf
            <label class="check">
                <input type="checkbox" name="accept" value="1" required>
                <span>Sunt de acord ca Tixello să-mi păstreze contul și istoricul biletelor, cum scrie în <a href="/politica-confidentialitate#cumparatori" target="_blank" rel="noopener">politica de confidențialitate</a>. Pot șterge contul oricând.</span>
            </label>
            @error('accept')<p class="err">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-primary full">Creează contul</button>
        </form>
    </div>
</div>
@endsection
