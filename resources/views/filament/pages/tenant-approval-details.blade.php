@php
    $entity = ['pf' => 'Persoană fizică', 'pfa' => 'PFA', 'srl' => 'SRL'][$tenant->legal_entity_type] ?? '—';
    $choices = $tenant->signup_choices ?? [];
    $site = $choices['site'] ?? null;
    $siteLabel = match ($site['mode'] ?? null) {
        'subdomain' => $site['subdomain'] . '.' . app(\App\Services\CloudflareService::class)->getBaseDomain() . ' (subdomeniu gratuit)',
        'own' => $site['domain'] . ' (domeniu propriu)',
        default => 'Mai târziu',
    };
    $identity = $tenant->legal_entity_type === 'pf' ? ($tenant->identity_data ?? []) : [];
    $methods = ['exclusive' => '1% · doar Tixello', 'mixed' => '2% · Tixello și alte canale', 'reseller' => '3% · revânzare'];
    $rows = [
        'Tip' => $tenant->tenant_type?->label() ?? '—',
        'Metoda de lucru' => $methods[$tenant->work_method] ?? '—',
        'Bilete / lună (estimat)' => $tenant->estimated_monthly_tickets ?: '—',
        'Forma juridică' => $entity,
        'Denumire' => $tenant->company_name,
        'CUI' => $tenant->cui ?: '—',
        'Reg. Com.' => $tenant->reg_com ?: '—',
        'Plătitor TVA' => $tenant->legal_entity_type === 'pf' ? '—' : ($tenant->vat_payer ? 'da' : 'nu'),
        'CNP' => $identity['cnp'] ?? null,
        'CI' => isset($identity['id_series']) ? $identity['id_series'] . ' ' . $identity['id_number'] : null,
        'Adresa' => trim($tenant->address . ', ' . $tenant->city . ', ' . $tenant->state, ', '),
        'Contact' => trim($tenant->contact_first_name . ' ' . $tenant->contact_last_name) . ' · ' . $tenant->contact_email . ' · ' . $tenant->contact_phone,
        'Email confirmat' => $tenant->owner?->email_verified_at ? 'da, ' . $tenant->owner->email_verified_at->format('d.m.Y H:i') : 'nu',
        'Site' => $siteLabel,
        'Template' => $choices['template']['slug'] ?? 'Mai târziu',
        'Procesator plăți' => $choices['payments']['processor'] ?? 'Nu știe încă',
        'Termeni acceptați' => $tenant->terms_accepted_at ? $tenant->terms_accepted_at->format('d.m.Y H:i') . ' · v' . $tenant->terms_version . ' · IP ' . $tenant->terms_ip : '—',
    ];
@endphp
<dl class="divide-y divide-gray-200 dark:divide-white/10 text-sm">
    @foreach ($rows as $label => $value)
        @continue($value === null)
        <div class="py-2 grid grid-cols-3 gap-4">
            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
            <dd class="col-span-2">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
@if ($tenant->cui)
    <p class="mt-4 text-sm">
        <a href="https://www.listafirme.ro/search.asp?searchfor={{ urlencode($tenant->cui) }}" target="_blank" rel="noopener" class="underline">Caută firma pe listafirme.ro ↗</a>
    </p>
@endif
