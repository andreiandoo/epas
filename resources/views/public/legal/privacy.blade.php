<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Politica de Confidentialitate - Tixello</title>
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
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Politica de Confidentialitate (GDPR)</h1>
        
        <div class="prose prose-lg max-w-none">
            <p class="text-gray-600 mb-6">Ultima actualizare: {{ date('d.m.Y') }}</p>

            <h2 class="text-xl font-semibold mt-8 mb-4">1. Introducere</h2>
            <p class="text-gray-700 mb-4">
                Aceasta Politica de Confidentialitate descrie modul in care Tixello colecteaza, utilizeaza 
                si protejeaza datele dumneavoastra cu caracter personal in conformitate cu Regulamentul General 
                privind Protectia Datelor (GDPR).
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">2. Date Colectate</h2>
            <p class="text-gray-700 mb-4">Colectam urmatoarele tipuri de date:</p>
            <ul class="list-disc list-inside text-gray-700 mb-4 space-y-1">
                <li>Date de identificare (nume, prenume, email, telefon)</li>
                <li>Date ale companiei (denumire, CUI, adresa)</li>
                <li>Date de plata (procesate prin furnizori terti)</li>
                <li>Date de utilizare a platformei</li>
            </ul>

            <h2 class="text-xl font-semibold mt-8 mb-4">3. Scopul Prelucrarii</h2>
            <p class="text-gray-700 mb-4">Prelucram datele dumneavoastra pentru:</p>
            <ul class="list-disc list-inside text-gray-700 mb-4 space-y-1">
                <li>Furnizarea serviciilor de ticketing</li>
                <li>Procesarea platilor si facturare</li>
                <li>Comunicari despre servicii si actualizari</li>
                <li>Conformitatea cu obligatiile legale</li>
            </ul>

            <h2 class="text-xl font-semibold mt-8 mb-4">4. Temeiul Legal</h2>
            <p class="text-gray-700 mb-4">
                Prelucram datele in baza: executarii contractului, obligatiilor legale, 
                interesului legitim si consimtamantului dumneavoastra.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">5. Drepturile Dumneavoastra</h2>
            <p class="text-gray-700 mb-4">Aveti dreptul de:</p>
            <ul class="list-disc list-inside text-gray-700 mb-4 space-y-1">
                <li>Acces la datele dumneavoastra</li>
                <li>Rectificare a datelor incorecte</li>
                <li>Stergere a datelor ("dreptul de a fi uitat")</li>
                <li>Restrictionare a prelucrarii</li>
                <li>Portabilitate a datelor</li>
                <li>Opozitie la prelucrare</li>
            </ul>

            <h2 class="text-xl font-semibold mt-8 mb-4">6. Pastrarea Datelor</h2>
            <p class="text-gray-700 mb-4">
                Pastram datele dumneavoastra pe perioada necesara indeplinirii scopurilor pentru care 
                au fost colectate sau conform cerintelor legale.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">7. Securitatea Datelor</h2>
            <p class="text-gray-700 mb-4">
                Implementam masuri tehnice si organizatorice adecvate pentru protejarea datelor 
                impotriva accesului neautorizat, modificarii sau distrugerii.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">8. Transferuri Internationale</h2>
            <p class="text-gray-700 mb-4">
                Datele dumneavoastra pot fi transferate in afara SEE doar cu garantii adecvate 
                conform GDPR.
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">9. Contact DPO</h2>
            <p class="text-gray-700 mb-4">
                Pentru exercitarea drepturilor sau intrebari, contactati-ne la:
                <a href="mailto:dpo@tixello.com" class="text-blue-600 hover:text-blue-800">dpo@tixello.com</a>
            </p>

            <h2 class="text-xl font-semibold mt-8 mb-4">10. Plangeri</h2>
            <p class="text-gray-700 mb-4">
                Aveti dreptul de a depune o plangere la Autoritatea Nationala de Supraveghere 
                a Prelucrarii Datelor cu Caracter Personal (ANSPDCP).
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
