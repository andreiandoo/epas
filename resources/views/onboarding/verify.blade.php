<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verificare Email - EventPilot ePas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
            <svg class="w-20 h-20 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-4">Înregistrare Finalizată!</h1>

        <p class="text-gray-600 mb-6">
            Contul tău a fost creat cu succes. În curând vei primi un email de verificare.
        </p>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-blue-800">
                <strong>Următorii pași:</strong>
            </p>
            <ol class="text-sm text-blue-700 text-left mt-2 ml-4 space-y-1">
                <li>1. Verifică-ți inbox-ul pentru email-ul de confirmare</li>
                <li>2. Dă click pe link-ul de verificare</li>
                <li>3. Autentifică-te în panoul de administrare</li>
            </ol>
        </div>

        <div class="space-y-3">
            <a
                href="/admin"
                class="block w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
            >
                Mergi la Autentificare
            </a>

            <a
                href="/"
                class="block w-full px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium"
            >
                Înapoi la Pagina Principală
            </a>
        </div>

        <p class="text-xs text-gray-500 mt-6">
            Nu ai primit emailul? Verifică folderul Spam sau contactează suportul.
        </p>
    </div>
</body>
</html>
