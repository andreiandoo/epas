@extends('layouts.customer')

@section('title', __('My Tickets'))

@section('content')
    <div class="space-y-6">
        {{-- Page header --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('My Tickets') }}</h1>
            <p class="text-gray-500 mt-1">{{ __('View and download your event tickets.') }}</p>
        </div>

        {{-- Tickets list --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @php
                $tickets = $customer->orders()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'completed')
                    ->with(['tickets.event', 'tickets.ticketType'])
                    ->get()
                    ->flatMap->tickets;
            @endphp

            @if($tickets->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('No tickets yet') }}</h3>
                    <p class="text-gray-500">{{ __('When you purchase tickets, they will appear here.') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-200">
                    @foreach($tickets as $ticket)
                        <div class="p-6 hover:bg-gray-50 transition">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-semibold text-gray-900">{{ $ticket->code ?? '#' . $ticket->id }}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $ticket->status === 'valid' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $ticket->status === 'used' ? 'bg-gray-100 text-gray-800' : '' }}
                                            {{ $ticket->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ ucfirst($ticket->status ?? 'valid') }}
                                        </span>
                                    </div>
                                    @if($ticket->event)
                                        <div class="mt-2">
                                            <div class="text-sm font-medium text-gray-900">{{ $ticket->event->title }}</div>
                                            <div class="text-sm text-gray-500">
                                                @if($ticket->event->start_date)
                                                    {{ $ticket->event->start_date->format('d M Y, H:i') }}
                                                @endif
                                                @if($ticket->event->venue)
                                                    &bull; {{ $ticket->event->venue->name }}
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if($ticket->ticketType)
                                        <div class="text-sm text-gray-500 mt-1">
                                            {{ $ticket->ticketType->name }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    @if($ticket->status === 'valid')
                                        <a href="#" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            {{ __('Download') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
