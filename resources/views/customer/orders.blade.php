@extends('layouts.customer')

@section('title', __('My Orders'))

@section('content')
    <div class="space-y-6">
        {{-- Page header --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('My Orders') }}</h1>
            <p class="text-gray-500 mt-1">{{ __('View and manage your order history.') }}</p>
        </div>

        {{-- Orders list --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @if($orders->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('No orders yet') }}</h3>
                    <p class="text-gray-500">{{ __('When you make a purchase, your orders will appear here.') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-200">
                    @foreach($orders as $order)
                        <div class="p-6 hover:bg-gray-50 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-semibold text-gray-900">{{ $order->order_ref ?? '#' . $order->id }}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $order->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $order->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ !in_array($order->status, ['completed', 'pending', 'cancelled']) ? 'bg-gray-100 text-gray-800' : '' }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        {{ $order->created_at->format('d M Y, H:i') }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-semibold text-gray-900">
                                        {{ number_format($order->total ?? 0, 2) }} {{ $order->currency ?? 'RON' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $order->tickets_count ?? $order->tickets()->count() }} {{ __('tickets') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($orders->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
