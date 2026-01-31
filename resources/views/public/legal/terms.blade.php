<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Termeni si Conditii - Tixello</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gray-50">
    <header class="bg-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-6">
            <a href="/" class="text-2xl font-bold text-gray-900">Tixello</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Termeni si Conditii</h1>
        
        <div class="prose prose-lg max-w-none">
            <p class="text-gray-600 mb-6">Ultima actualizare: {{ date('d.m.Y') }}</p>

            <h2 class="text-xl font-semibold mt-8 mb-4">1. Acceptarea Termenilor</h2>
            <p class="text-gray-700 mb-4">
                Prin accesarea si utilizarea platformei Tixello, acceptati sa fiti legat de acesti Termeni si Conditii. 
                Daca nu sunteti de acord cu acesti termeni, va rugam sa nu utilizati serviciile noastre.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">2. Descrierea Serviciului</h2>
            <p class="text-gray-700 mb-4">
                Tixello ofera o platforma de ticketing pentru evenimente, permitand organizatorilor sa creeze, 
                sa gestioneze si sa vanda bilete pentru evenimentele lor.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">3. Inregistrarea Contului</h2>
            <p class="text-gray-700 mb-4">
                Pentru a utiliza serviciile noastre, trebuie sa va inregistrati si sa furnizati informatii exacte 
                si complete. Sunteti responsabil pentru mentinerea confidentialitatii contului dvs.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">4. Comisioane si Plati</h2>
            <p class="text-gray-700 mb-4">
                Comisioanele pentru utilizarea platformei variaza in functie de planul ales (1%, 2% sau 3%).
                Platile sunt procesate prin procesatorii de plati selectati de dvs.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">5. Obligatiile Utilizatorului</h2>
            <p class="text-gray-700 mb-4">
                Va angajati sa utilizati platforma in conformitate cu legile aplicabile si sa nu incalcati 
                drepturile altor utilizatori sau terti.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">6. Limitarea Raspunderii</h2>
            <p class="text-gray-700 mb-4">
                Tixello nu va fi raspunzator pentru nicio dauna indirecta, incidentala sau consecventa 
                care rezulta din utilizarea sau imposibilitatea de a utiliza serviciile.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">7. Modificari ale Termenilor</h2>
            <p class="text-gray-700 mb-4">
                Ne rezervam dreptul de a modifica acesti termeni in orice moment. 
                Modificarile vor fi anuntate prin email sau pe platforma.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">8. Contact</h2>
            <p class="text-gray-700 mb-4">
                Pentru intrebari despre acesti Termeni si Conditii, ne puteti contacta la 
                <a href="mailto:contact@tixello.com" class="text-blue-600 hover:text-blue-800">contact@tixello.com</a>.
            </p>
        </div>

        <div class="mt-12">
            <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800">&larr; Inapoi</a>
        </div>
    </main>

    <footer class="bg-white border-t mt-12">
        <div class="max-w-4xl mx-auto px-4 py-6 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} Tixello. Toate drepturile rezervate.
        </div>
    </footer>
</body>
</html>
