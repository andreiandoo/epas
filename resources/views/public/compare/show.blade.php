@extends('public.layout')
@section('title',"Compare EventPilot vs {$competitor['name']} — ".strtoupper($country))
@section('content')
<div class="max-w-5xl mx-auto px-4">
  <h1 class="text-3xl font-bold">Compare: EventPilot vs {{ $competitor['name'] }}</h1>
  <p class="mt-2 text-gray-600 text-sm">Country: {{ strtoupper($country) }}</p>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <div class="rounded-2xl border bg-white p-5">
      <div class="font-semibold">EventPilot</div>
      <a class="text-sm text-blue-600 underline" href="{{ $ours['url'] }}" target="_blank">{{ $ours['url'] }}</a>
    </div>
    <div class="rounded-2xl border bg-white p-5">
      <div class="font-semibold">{{ $competitor['name'] }}</div>
      <a class="text-sm text-blue-600 underline" href="{{ $competitor['url'] }}" target="_blank">{{ $competitor['url'] }}</a>
    </div>
  </div>

  <div class="mt-8 space-y-6">
    @foreach($sections as $title => $bullets)
      <div class="rounded-2xl border bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $title }}</h2>
        <ul class="mt-3 list-disc pl-5 text-gray-700">
          @foreach($bullets as $item)
          <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
    @endforeach
  </div>
</div>
@endsection
