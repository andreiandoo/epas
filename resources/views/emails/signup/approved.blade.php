@extends('emails.signup.layout')

@section('body')
    <p>Salut, {{ $tenant->contact_first_name }}!</p>
    <p>Contul <strong>{{ $tenant->public_name }}</strong> a fost aprobat. Poți vinde bilete imediat ce îți adaugi primul eveniment.</p>
    <p><a href="{{ url('/tenant') }}" style="display:inline-block;background:#5b3fd6;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">Intră în panou</a></p>
    <p>Contractul îl primești într-un email separat.</p>

    @foreach ($tenant->domains->where('is_managed_subdomain', false) as $domain)
        @php($verification = $domain->verifications->sortByDesc('id')->first())
        @if ($verification)
            <p style="margin-top:24px;"><strong>Domeniul {{ $domain->domain }}</strong>: ca să-l legăm de contul tău, adaugă la furnizorul de domeniu această înregistrare DNS:</p>
            <p style="background:#f4f4f7;padding:12px;border-radius:8px;font-family:monospace;font-size:13px;">
                Tip: TXT<br>
                Nume: {{ $verification->getDnsRecordName() }}<br>
                Valoare: {{ $verification->getDnsRecordValue() }}
            </p>
        @endif
    @endforeach
@endsection
