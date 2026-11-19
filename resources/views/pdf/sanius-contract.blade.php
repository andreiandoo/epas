<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; line-height: 1.5; color: #111; }
        h1 { font-size: 15pt; text-align: center; margin: 0 0 4mm; }
        .meta { text-align: center; font-size: 9pt; color: #555; margin-bottom: 8mm; }
        .body p { margin: 0 0 3.5mm; text-align: justify; }
        table.data { width: 100%; border-collapse: collapse; margin: 6mm 0; font-size: 10pt; }
        table.data td { border: 1px solid #bbb; padding: 2mm 3mm; }
        table.data td.k { width: 38%; color: #444; }
        .sign { margin-top: 12mm; width: 100%; }
        .sign td { vertical-align: bottom; width: 50%; padding: 0 4mm; }
        .line { border-top: 1px solid #333; padding-top: 1.5mm; font-size: 9pt; color: #444; }
        .sigimg { height: 26mm; }
        .foot { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #777; text-align: center; }
        .test { color: #b45309; font-weight: bold; text-align: center; margin-bottom: 4mm; }
    </style>
</head>
<body>
    @if ($contract->is_test)
        <div class="test">TEST — contract fără valoare</div>
    @endif
    <h1>{{ $contract->title }}</h1>
    <div class="meta">Nr. {{ $contract->id }} · {{ $createdAt?->format('d.m.Y H:i') }} · tichet parcare {{ $contract->tab?->parking_number ?: $contract->tab?->parking_code }}</div>

    <div class="body">
        @foreach (preg_split("/\n\s*\n/", trim($contract->body)) as $para)
            <p>{!! nl2br(e(trim($para))) !!}</p>
        @endforeach
    </div>

    <table class="data">
        <tr><td class="k">Nume și prenume</td><td>{{ $contract->signer_name }}</td></tr>
        <tr><td class="k">Act de identitate</td><td>{{ ['ci' => 'Carte de identitate', 'passport' => 'Pașaport', 'other' => 'Alt act'][$contract->doc_type] ?? 'Act' }} {{ $contract->doc_series }} {{ $contract->doc_number }}</td></tr>
        @if ($contract->phone)
            <tr><td class="k">Telefon</td><td>{{ $contract->phone }}</td></tr>
        @endif
        @if ($contract->address)
            <tr><td class="k">Adresă</td><td>{{ $contract->address }}</td></tr>
        @endif
    </table>

    <table class="sign">
        <tr>
            <td>
                @if ($signature)
                    <img class="sigimg" src="{{ $signature }}" alt="Semnătura">
                @endif
                <div class="line">Semnătura clientului{{ $signedAt && $signature ? ' · semnat electronic pe '.$signedAt->format('d.m.Y').' la '.$signedAt->format('H:i') : '' }}</div>
            </td>
            <td>
                <div class="line">Data: {{ ($signedAt ?? $createdAt)?->format('d.m.Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="foot">Versiunea textului: {{ $contract->template_version }} · cod de verificare {{ substr($contract->body_hash, 0, 16) }}</div>
</body>
</html>
