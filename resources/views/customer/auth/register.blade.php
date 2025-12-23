<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Create Account') }} - {{ $tenant->public_name ?? $tenant->name }}</title>

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
                <h2 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Create your account') }}</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('Already have an account?') }}
                    <a href="{{ route('customer.login', ['tenant' => $tenant->slug]) }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        {{ __('Sign in') }}
                    </a>
                </p>
            </div>

            {{-- Register Form --}}
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

                <form method="POST" action="{{ route('customer.register.submit', ['tenant' => $tenant->slug]) }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">{{ __('First Name') }}</label>
                            <input id="first_name"
                                   name="first_name"
                                   type="text"
                                   autocomplete="given-name"
                                   required
                                   value="{{ old('first_name') }}"
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">{{ __('Last Name') }}</label>
                            <input id="last_name"
                                   name="last_name"
                                   type="text"
                                   autocomplete="family-name"
                                   required
                                   value="{{ old('last_name') }}"
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

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
                               autocomplete="new-password"
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm Password') }}</label>
                        <input id="password_confirmation"
                               name="password_confirmation"
                               type="password"
                               autocomplete="new-password"
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            {{ __('Create Account') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
