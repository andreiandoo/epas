@extends('emails.signup.layout')

@section('body')
    <p>Salut, {{ $tenant->contact_first_name }}!</p>
    <p>Contul <strong>{{ $tenant->public_name }}</strong> a fost aprobat. Poți vinde bilete imediat ce îți adaugi primul eveniment.</p>
    <p><a href="{{ url('/tenant') }}" style="display:inline-block;background:#5b3fd6;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">Intră în panou</a></p>
    <p>Contractul îl primești într-un email separat.</p>
    @php($extras = $tenant->signup_choices['extras'] ?? [])
    @php($chosen = \App\Models\Microservice::whereIn('id', $extras['microservices'] ?? [])->get())
    @if ($chosen->where('raises_commission', true)->isNotEmpty())
        <p>Funcțiile plătite prin comision sunt deja pornite: {{ $chosen->where('raises_commission', true)->map->display_label->implode(', ') }}.</p>
    @endif
    @if ($chosen->where('raises_commission', false)->isNotEmpty())
        <p>Pe celelalte funcții alese ({{ $chosen->where('raises_commission', false)->map->display_label->implode(', ') }}) le activezi din panou, la Microservicii.</p>
    @endif
    @if (! empty($extras['custom_site']))
        <p>Pentru site-ul făcut 100% pentru tine ({{ config('onboarding.custom_site_price') }} {{ config('onboarding.custom_site_currency') }}, plată unică) te contactăm noi în zilele următoare.</p>
    @endif

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
