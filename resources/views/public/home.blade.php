@extends('public.layout')

@section('title','Tixello — Your Event Ticketing Solution')
@section('meta_description','Discover events across all Tixello tenants. Explore artists, venues and compare ticketing platforms with Tixello.')

@section('content')
<div class="max-w-7xl mx-auto px-4">
  {{-- Hero --}}
  <section class="rounded-3xl bg-gradient-to-br from-gray-900 to-gray-700 text-white p-10 md:p-14">
    <div class="max-w-3xl">
      <h1 class="text-4xl md:text-5xl font-extrabold leading-tight">All your events, one powerful Core.</h1>
      <p class="mt-4 text-lg text-gray-200">
        Tixello brings tenants, venues, artists and ticketing together. Browse the public catalogue and jump to each tenant's storefront.
      </p>
      <div class="mt-6 flex gap-3">
        <a href="{{ route('public.events.index') }}" class="rounded-xl bg-white text-gray-900 px-5 py-3 font-semibold">Browse Events</a>
        <a href="{{ route('public.venues.index') }}" class="rounded-xl ring-1 ring-white/40 px-5 py-3">Venues</a>
        <a href="{{ route('public.artists.index') }}" class="rounded-xl ring-1 ring-white/40 px-5 py-3">Artists</a>
      </div>
    </div>
  </section>

  {{-- KPIs --}}
  <section class="mt-8 grid sm:grid-cols-2 lg:grid-cols-6 gap-4">
    <x-public.stat-card label="Total tickets sold" :value="$stats['tickets']" />
    <x-public.stat-card label="Customers" :value="$stats['customers']" />
    <x-public.stat-card label="Tenants" :value="$stats['tenants']" />
    <x-public.stat-card label="Venues" :value="$stats['venues']" />
    <x-public.stat-card label="Events" :value="$stats['events']" />
    <x-public.stat-card label="Artists" :value="$stats['artists']" />
  </section>

  {{-- Latest Events --}}
  <section class="mt-12">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold">Latest events</h2>
      <a href="{{ route('public.events.index') }}" class="text-sm text-gray-600 hover:text-black">See all</a>
    </div>
    <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      @forelse($latestEvents as $event)
        <x-public.event-card :event="$event" />
      @empty
        <div class="col-span-full text-gray-500">No events yet.</div>
      @endforelse
    </div>
  </section>

  {{-- Compare strip --}}
  <section class="mt-16 rounded-2xl border bg-white p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h3 class="text-xl font-semibold">Compare Tixello with other ticketing platforms</h3>
        <p class="text-gray-600 text-sm">Neutral, feature-by-feature comparisons for transparency.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm" href="{{ url('/compare/ro/epas-iabilet') }}">iaBilet</a>
        <a class="px-3 py-2 rounded-lg border text-sm" href="{{ url('/compare/ro/epas-ambilet') }}">amBilet</a>
        <a class="px-3 py-2 rounded-lg border text-sm" href="{{ url('/compare/ro/epas-eventim') }}">Eventim</a>
        <a class="px-3 py-2 rounded-lg border text-sm" href="{{ url('/compare/ro/epas-eventbook') }}">Eventbook</a>
        <a class="px-3 py-2 rounded-lg border text-sm" href="{{ url('/compare/ro/epas-oveit') }}">Oveit</a>
      </div>
    </div>
  </section>
</div>
@endsection
