<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acces Protejat — {{ $template->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-auto px-4">
        <div class="bg-white rounded-2xl shadow-lg border p-8 text-center">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Preview Protejat</h1>
            <p class="text-sm text-gray-500 mb-6">Acest preview necesită o parolă de acces.</p>

            @if($error)
                <div class="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-lg mb-4">{{ $error }}</div>
            @endif

            <form method="POST" action="">
                @csrf
                <input type="password" name="password" placeholder="Introdu parola" autofocus
                       class="w-full px-4 py-3 border rounded-lg text-center text-lg tracking-widest focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none mb-4">
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                    Accesează Preview
                </button>
            </form>

            <p class="text-xs text-gray-400 mt-6">
                {{ $template->name }} · Powered by <a href="https://tixello.ro" class="text-indigo-500">Tixello</a>
            </p>
        </div>
    </div>
</body>
</html>
