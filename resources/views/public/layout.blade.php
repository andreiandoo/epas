<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Tixello')</title>
  <meta name="description" content="@yield('meta_description','Tixello - Your Event Ticketing Solution')">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased">
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
      <a href="{{ route('public.home', ['locale' => app()->getLocale()]) }}" class="font-semibold text-xl">Tixello</a>
      <nav class="flex items-center gap-6 text-sm">
        <a href="{{ route('public.about', ['locale' => app()->getLocale()]) }}" class="hover:text-black">About</a>
        <a href="{{ route('public.contact', ['locale' => app()->getLocale()]) }}" class="hover:text-black">Contact</a>
        <a href="{{ route('public.events.index', ['locale' => app()->getLocale()]) }}" class="hover:text-black">Events</a>
        <a href="{{ route('public.artists.index', ['locale' => app()->getLocale()]) }}" class="hover:text-black">Artists</a>
        <a href="{{ route('public.venues.index', ['locale' => app()->getLocale()]) }}" class="hover:text-black">Venues</a>
      </nav>
    </div>
  </header>

  <main class="py-10">
    @yield('content')
  </main>

  <footer class="border-t bg-white">
    <div class="max-w-7xl mx-auto px-4 py-8 text-sm text-gray-500 flex flex-col sm:flex-row gap-3 justify-between">
      <p>© {{ date('Y') }} Tixello • Your Event Ticketing Solution.</p>
      <p>
        Compare:
        <a class="hover:text-black" href="{{ route('public.compare', ['locale' => app()->getLocale(), 'country' => 'ro', 'slug' => 'epas-iabilet']) }}">iaBilet</a> •
        <a class="hover:text-black" href="{{ route('public.compare', ['locale' => app()->getLocale(), 'country' => 'ro', 'slug' => 'epas-ambilet']) }}">amBilet</a> •
        <a class="hover:text-black" href="{{ route('public.compare', ['locale' => app()->getLocale(), 'country' => 'ro', 'slug' => 'epas-eventim']) }}">Eventim</a>
      </p>
    </div>
  </footer>
</body>
</html>
