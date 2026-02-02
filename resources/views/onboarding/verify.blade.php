<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verificare Email - EventPilot ePas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            @if($token === 'pending')
                <!-- Pending Verification -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-6">
                    <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Verifica-ti Email-ul</h1>
                <p class="text-gray-600 mb-6">
                    Ti-am trimis un email cu instructiunile de verificare a domeniului tau.
                    Te rugam sa verifici inbox-ul (si folderul spam).
                </p>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-800 mb-2">Ce urmeaza?</h3>
                    <ol class="text-sm text-blue-700 text-left list-decimal list-inside space-y-1">
                        <li>Verifica email-ul pentru instructiuni</li>
                        <li>Alege una din cele 3 metode de verificare</li>
                        <li>Dupa verificare, domeniul va fi activat</li>
                    </ol>
                </div>
            @else
                <!-- Verification Result -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                    <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Verificare in Curs</h1>
                <p class="text-gray-600 mb-6">
                    Codul tau de verificare a fost primit. Echipa noastra va verifica domeniul si il va activa in curand.
                </p>
            @endif

            <div class="space-y-3">
                <a href="/tenant" class="block w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    Mergi la Panoul Tenant
                </a>
                <a href="/" class="block w-full px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">
                    Inapoi la Pagina Principala
                </a>
            </div>
        </div>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500">
                Ai nevoie de ajutor? <a href="/contact" class="text-blue-600 hover:text-blue-800">Contacteaza-ne</a>
            </p>
        </div>
    </div>
</body>
</html>
