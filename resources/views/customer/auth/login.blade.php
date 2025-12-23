<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Login') }} - {{ $tenant->public_name ?? $tenant->name }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            {{-- Logo/Header --}}
            <div class="text-center mb-8">
                <a href="{{ url('/') }}" class="inline-block">
                    <span class="text-2xl font-bold text-gray-900">{{ $tenant->public_name ?? $tenant->name }}</span>
                </a>
                <h2 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Sign in to your account') }}</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('Or') }}
                    <a href="{{ route('customer.register', ['tenant' => $tenant->slug]) }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        {{ __('create a new account') }}
                    </a>
                </p>
            </div>

            {{-- Login Form --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                @if($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <ul class="list-disc list-inside text-sm text-red-700">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.login.submit', ['tenant' => $tenant->slug]) }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email address') }}</label>
                        <input id="email"
                               name="email"
                               type="email"
                               autocomplete="email"
                               required
                               value="{{ old('email') }}"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                        <input id="password"
                               name="password"
                               type="password"
                               autocomplete="current-password"
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember"
                                   name="remember"
                                   type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">{{ __('Remember me') }}</label>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            {{ __('Sign in') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
