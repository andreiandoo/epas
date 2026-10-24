@extends('emails.signup.layout')

@section('body')
    <p>Salut, {{ $tenant->contact_first_name }}!</p>
    <p>Nu am putut aproba înscrierea <strong>{{ $tenant->public_name }}</strong>.</p>
    @if ($tenant->rejected_reason)
        <p style="background:#f4f4f7;padding:12px;border-radius:8px;">{{ $tenant->rejected_reason }}</p>
    @endif
    <p>Dacă vrei să lămurim ce lipsește, răspunde la acest email.</p>
@endsection
