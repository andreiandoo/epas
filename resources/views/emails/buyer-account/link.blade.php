@extends('emails.signup.layout')

@section('body')
    <p>Salut{{ $account->first_name ? ', ' . $account->first_name : '' }}!</p>

    @if ($kind === 'welcome')
        <p>Ai bifat la plată să păstrezi biletele și în contul tău Tixello. De acum găsești acolo, într-un singur loc, biletele de la orice organizator care vinde prin Tixello.</p>
    @elseif ($kind === 'marketing')
        <p>Ai bifat la plată că vrei recomandări de evenimente de la Tixello. Ți le trimitem pe adresa asta și poți renunța oricând.</p>
    @elseif ($kind === 'create')
        <p>Ai cerut să-ți faci cont de cumpărător Tixello. Apasă butonul ca să-l creezi. În cont vezi biletele cumpărate cu această adresă de la organizatorii care vând prin Tixello.</p>
    @else
        <p>Ai cerut un link de intrare în contul tău Tixello.</p>
    @endif

    @if ($kind !== 'marketing')
        <p style="margin:24px 0;"><a href="{{ $actionUrl }}" style="display:inline-block;background:#e8492a;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:999px;font-weight:bold;">{{ $kind === 'create' ? 'Creează contul' : 'Intră în cont' }}</a></p>
        <p style="color:#8a8fa3;font-size:13px;">Linkul e valabil 30 de minute și merge doar de pe această adresă de email. Nu-l trimite altcuiva.</p>
    @endif

    <p style="color:#8a8fa3;font-size:13px;margin-top:24px;">
        Nu tu ai cerut asta? Ignoră emailul
        @if ($unsubscribeUrl)
            sau <a href="{{ $unsubscribeUrl }}" style="color:#8a8fa3;">oprește emailurile de la Tixello</a>
        @endif.
        Cum folosim datele: <a href="{{ url('/politica-confidentialitate#cumparatori') }}" style="color:#8a8fa3;">politica de confidențialitate</a>.
    </p>
@endsection
