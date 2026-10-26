@extends('buyer-account.layout')
@section('title', 'Biletele mele')

@section('top')
    <form method="post" action="{{ route('buyer-account.logout') }}">@csrf<button type="submit" class="btn btn-line" style="min-height:40px;padding:8px 16px">Ieși din cont</button></form>
@endsection

@section('content')
    @if (session('status'))<p class="notice">{{ session('status') }}</p>@endif
    <h1>Biletele mele</h1>
    <p class="muted">Comenzile plătite cu <strong>{{ $account->email }}</strong> pe site-urile organizatorilor Tixello.</p>

    @if ($orders->isEmpty())
        <div class="card" style="margin-top:20px">
            <h2>Încă nu ai bilete aici</h2>
            <p class="muted" style="margin:0">Când cumperi bilete cu această adresă de la un organizator care vinde prin Tixello, apar aici automat.</p>
        </div>
    @else
        <div class="orders" style="margin-top:20px">
            @foreach ($orders as $o)
                <div class="order">
                    <div>
                        <p class="small muted" style="margin:0">{{ $o['organizer'] }}</p>
                        <h3>{{ $o['event'] ?: 'Comanda #' . $o['id'] }}</h3>
                        <p class="small muted">
                            @if ($o['date']){{ \Illuminate\Support\Carbon::parse($o['date'])->locale('ro')->isoFormat('D MMMM YYYY') }} · @endif
                            {{ $o['tickets'] }} {{ $o['tickets'] === 1 ? 'bilet' : 'bilete' }} ·
                            {{ number_format($o['total'], 2, ',', '.') }} {{ $o['currency'] === 'RON' ? 'lei' : $o['currency'] }} ·
                            comanda #{{ $o['id'] }} din {{ $o['bought_at']->format('d.m.Y') }}
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        @if ($o['pdf'])<a href="{{ $o['pdf'] }}" class="btn btn-primary" target="_blank" rel="noopener">Descarcă biletele</a>@endif
                        @if ($o['site'])<a href="{{ $o['site'] }}" class="btn btn-line" target="_blank" rel="noopener">Site-ul organizatorului</a>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid2">
        <div class="card">
            <h2>Recomandări de la Tixello</h2>
            @if ($account->marketing_opt_in)
                <p class="muted small">Primești pe email recomandări de evenimente și oferte de la Tixello, din {{ $account->marketing_opt_in_at?->format('d.m.Y') }}.</p>
                <form method="post" action="{{ route('buyer-account.marketing') }}">@csrf<input type="hidden" name="on" value="0"><button type="submit" class="btn btn-line">Nu mai vreau recomandări</button></form>
            @else
                <p class="muted small">{{ config('legal.buyer_consents.marketing') }}</p>
                <form method="post" action="{{ route('buyer-account.marketing') }}">@csrf<input type="hidden" name="on" value="1"><button type="submit" class="btn btn-line">Vreau recomandări</button></form>
            @endif
            <p class="small muted" style="margin-bottom:0">Organizatorii de la care cumperi au newsletterul lor, separat. Te dezabonezi de la ei din emailurile lor.</p>
        </div>
        <div class="card">
            <h2>Șterge contul</h2>
            <p class="muted small">Ștergem contul și acordul pentru recomandări. Biletele rămân valabile și le ai în emailurile de confirmare. Datele comenzilor rămân la organizatori, care le păstrează conform legii.</p>
            <form method="post" action="{{ route('buyer-account.destroy') }}">
                @csrf
                <label class="check"><input type="checkbox" name="confirm" value="1" required><span>Da, vreau să-mi șterg contul Tixello.</span></label>
                @error('confirm')<p class="err">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-danger" style="margin-top:12px">Șterge contul</button>
            </form>
        </div>
    </div>
@endsection
