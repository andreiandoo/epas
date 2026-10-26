@extends('buyer-account.layout')
@section('title', 'Dezabonare')

@section('content')
<div class="narrow">
    <div class="card">
        <h1>Gata, te-am dezabonat</h1>
        <p class="muted">Nu mai trimitem recomandări Tixello la <strong>{{ $account->email }}</strong>. Emailurile despre biletele tale (confirmări, schimbări de program) le primești în continuare de la organizatori.</p>
        <p class="muted small" style="margin-bottom:0">Te-ai răzgândit? Poți porni recomandările din nou din <a href="{{ route('buyer-account.show') }}">contul tău</a>.</p>
    </div>
</div>
@endsection
