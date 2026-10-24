@extends('emails.signup.layout')

@section('body')
    <p>Salut, {{ $user->name }}!</p>
    <p>Codul pentru confirmarea adresei de email este:</p>
    <p style="font-size:32px;font-weight:bold;letter-spacing:8px;margin:20px 0;color:#1f2330;">{{ $code }}</p>
    <p>Îl introduci în pagina de înscriere. Codul expiră în {{ config('onboarding.code_ttl_minutes') }} de minute.</p>
    <p style="color:#8a8fa3;font-size:13px;">Dacă nu tu ai început înscrierea, ignoră acest email.</p>
@endsection
