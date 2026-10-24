@extends('emails.signup.layout')

@section('body')
    @php
        $entity = ['pf' => 'Persoană fizică', 'pfa' => 'PFA', 'srl' => 'SRL'][$tenant->legal_entity_type] ?? '—';
        $site = $tenant->signup_choices['site'] ?? null;
    @endphp
    <p>S-a înscris un tenant nou, care așteaptă aprobarea ta.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;margin:12px 0 20px;">
        <tr><td style="padding:4px 0;color:#8a8fa3;width:140px;">Nume public</td><td><strong>{{ $tenant->public_name }}</strong></td></tr>
        <tr><td style="padding:4px 0;color:#8a8fa3;">Tip</td><td>{{ $tenant->tenant_type?->label() ?? '—' }}</td></tr>
        <tr><td style="padding:4px 0;color:#8a8fa3;">Facturează</td><td>{{ $entity }}{{ $tenant->cui ? ' · CUI ' . $tenant->cui : '' }} · {{ $tenant->company_name }}</td></tr>
        <tr><td style="padding:4px 0;color:#8a8fa3;">Contact</td><td>{{ $tenant->contact_first_name }} {{ $tenant->contact_last_name }} · {{ $tenant->contact_email }} · {{ $tenant->contact_phone }}</td></tr>
        <tr><td style="padding:4px 0;color:#8a8fa3;">Comision</td><td>{{ rtrim(rtrim(number_format((float) $tenant->commission_rate, 2, ',', ''), '0'), ',') }}%{{ ! empty($tenant->signup_choices['extras']['custom_site']) ? ' · vrea site 100% custom (' . config('onboarding.custom_site_price') . ' ' . config('onboarding.custom_site_currency') . ')' : '' }}</td></tr>
        <tr><td style="padding:4px 0;color:#8a8fa3;">Site</td><td>{{ $tenant->domain ?: (($site['mode'] ?? null) === 'later' ? 'mai târziu' : '—') }}</td></tr>
    </table>
    <p><a href="{{ url('/admin/tenant-approvals') }}" style="display:inline-block;background:#5b3fd6;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">Deschide coada de aprobare</a></p>
@endsection
