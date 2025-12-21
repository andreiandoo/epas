<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Înregistrare - EventPilot ePas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/favicon.ico">
    <style>
        [x-cloak] { display: none !important; }

        .step-indicator {
            transition: all 0.3s ease;
        }

        .step-indicator.active {
            background: #3b82f6;
            color: white;
        }

        .step-indicator.completed {
            background: #10b981;
            color: white;
        }

        .step-content {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .password-strength-meter {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength-fill {
            height: 100%;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        /* Modal styles */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="min-h-screen flex flex-col" x-data="wizardData()" x-init="init()">
        <!-- Modal Component -->
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 modal-backdrop transition-opacity" aria-hidden="true" @click="closeModal()"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full" :class="modalType === 'success' ? 'bg-green-100' : (modalType === 'error' ? 'bg-red-100' : 'bg-blue-100')">
                            <!-- Success icon -->
                            <svg x-show="modalType === 'success'" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <!-- Error icon -->
                            <svg x-show="modalType === 'error'" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <!-- Info icon -->
                            <svg x-show="modalType === 'info'" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="modalTitle"></h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 whitespace-pre-line" x-text="modalMessage"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6">
                        <button type="button" @click="closeModal()" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:text-sm" :class="modalType === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : (modalType === 'error' ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500')">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-5xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">EventPilot ePas</h1>
                        <p class="text-sm text-gray-500">Sistem de ticketing pentru evenimente</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        Ai deja cont? <a href="/admin" class="text-blue-600 hover:text-blue-800 font-medium">Autentifică-te</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Progress Steps -->
        <div class="bg-white border-b py-6">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex items-center justify-between">
                    <template x-for="i in 4" :key="i">
                        <div class="flex items-center" :class="{'flex-1': i < 4}">
                            <div class="flex items-center">
                                <div
                                    class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-semibold"
                                    :class="{
                                        'active': currentStep === i,
                                        'completed': currentStep > i,
                                        'bg-gray-200 text-gray-500': currentStep < i
                                    }"
                                >
                                    <span x-show="currentStep < i || currentStep === i" x-text="i"></span>
                                    <svg x-show="currentStep > i" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3 hidden sm:block">
                                    <div class="text-sm font-medium" :class="currentStep >= i ? 'text-gray-900' : 'text-gray-400'">
                                        Pasul <span x-text="i"></span>
                                    </div>
                                    <div class="text-xs text-gray-500" x-text="getStepTitle(i)"></div>
                                </div>
                            </div>
                            <div
                                x-show="i < 4"
                                class="flex-1 h-1 mx-4"
                                :class="currentStep > i ? 'bg-green-500' : 'bg-gray-200'"
                            ></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 py-12 px-4">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <!-- Step 1: Personal Info -->
                    <div x-show="currentStep === 1" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Informații Personale</h2>
                        <p class="text-gray-600 mb-6">Introdu datele tale de contact pentru crearea contului</p>

                        <form @submit.prevent="submitStep1()">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Prenume *</label>
                                    <input
                                        type="text"
                                        x-model="formData.first_name"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    >
                                    <span x-show="errors.first_name" class="text-red-500 text-sm" x-text="errors.first_name"></span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nume *</label>
                                    <input
                                        type="text"
                                        x-model="formData.last_name"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    >
                                    <span x-show="errors.last_name" class="text-red-500 text-sm" x-text="errors.last_name"></span>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nume Public Organizație *</label>
                                <input
                                    type="text"
                                    x-model="formData.public_name"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="ex: Teatrul Odeon"
                                    required
                                >
                                <p class="text-xs text-gray-500 mt-1">Numele sub care va fi afișată organizația (poate fi diferit de denumirea legală)</p>
                                <span x-show="errors.public_name" class="text-red-500 text-sm" x-text="errors.public_name"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <div class="relative">
                                    <input
                                        type="email"
                                        x-model="formData.email"
                                        @input.debounce.500ms="checkEmailAvailability()"
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-10"
                                        :class="emailStatus === 'available' ? 'border-green-500' : (emailStatus === 'taken' ? 'border-red-500' : 'border-gray-300')"
                                        required
                                    >
                                    <div class="absolute right-3 top-2.5">
                                        <svg x-show="emailChecking" class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg x-show="emailStatus === 'available' && !emailChecking" class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <svg x-show="emailStatus === 'taken' && !emailChecking" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                <span x-show="emailStatus === 'taken'" class="text-red-500 text-sm">Această adresă de email este deja înregistrată</span>
                                <span x-show="errors.email" class="text-red-500 text-sm" x-text="errors.email"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Telefon *</label>
                                <div class="flex">
                                    <select
                                        x-model="formData.phone_country"
                                        class="w-28 px-2 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50"
                                    >
                                        <option value="+40">🇷🇴 +40</option>
                                        <option value="+49">🇩🇪 +49</option>
                                        <option value="+33">🇫🇷 +33</option>
                                        <option value="+44">🇬🇧 +44</option>
                                        <option value="+1">🇺🇸 +1</option>
                                        <option value="+36">🇭🇺 +36</option>
                                        <option value="+359">🇧🇬 +359</option>
                                        <option value="+373">🇲🇩 +373</option>
                                        <option value="+39">🇮🇹 +39</option>
                                        <option value="+34">🇪🇸 +34</option>
                                    </select>
                                    <input
                                        type="tel"
                                        x-model="formData.phone_number"
                                        class="flex-1 px-4 py-2 border border-l-0 border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="xxx xxx xxx"
                                        required
                                    >
                                </div>
                                <span x-show="errors.phone" class="text-red-500 text-sm" x-text="errors.phone"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Funcție în Companie</label>
                                <input
                                    type="text"
                                    x-model="formData.contact_position"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="ex: Director General, Administrator"
                                >
                                <p class="text-xs text-gray-500 mt-1">Funcția pe care o ocupi în companie (opțional)</p>
                                <span x-show="errors.contact_position" class="text-red-500 text-sm" x-text="errors.contact_position"></span>
                            </div>

                            <div class="mt-6 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Parolă *</label>
                                    <input
                                        type="password"
                                        x-model="formData.password"
                                        @input="checkPasswordStrength()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                        minlength="8"
                                    >
                                    <div class="password-strength-meter mt-2">
                                        <div class="password-strength-fill" :class="passwordStrengthClass"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1" x-text="passwordStrengthText"></p>
                                    <span x-show="errors.password" class="text-red-500 text-sm" x-text="errors.password"></span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirmă Parola *</label>
                                    <input
                                        type="password"
                                        x-model="formData.password_confirmation"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continuă →</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Company Info -->
                    <div x-show="currentStep === 2" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Informații Companie</h2>
                        <p class="text-gray-600 mb-6">Detalii despre firma ta pentru facturare și contracte</p>

                        <form @submit.prevent="submitStep2()">
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Țară *</label>
                                <select
                                    x-model="formData.country"
                                    @change="loadStates()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                                    <option value="Romania">Romania</option>
                                    <option value="United States">United States</option>
                                    <option value="Germany">Germany</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        x-model="formData.vat_payer"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Plătitor de TVA</span>
                                </label>
                            </div>

                            <div class="mb-6" x-show="formData.country === 'Romania'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">CUI / CIF</label>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        x-model="formData.cui"
                                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="ex: RO12345678"
                                    >
                                    <button
                                        type="button"
                                        @click="lookupCui()"
                                        :disabled="!formData.cui || cuiLoading"
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span x-show="!cuiLoading">Verifică ANAF</span>
                                        <span x-show="cuiLoading">...</span>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Completează CUI-ul pentru a prelua automat datele firmei din ANAF</p>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nume Companie *</label>
                                <input
                                    type="text"
                                    x-model="formData.company_name"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Registrul Comerțului</label>
                                <input
                                    type="text"
                                    x-model="formData.reg_com"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="ex: J40/12345/2020"
                                >
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Adresă *</label>
                                <textarea
                                    x-model="formData.address"
                                    rows="2"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Județ / Sector *</label>
                                    <select
                                        x-model="formData.state"
                                        @change="loadCities()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    >
                                        <option value="">Selectează...</option>
                                        <template x-for="state in availableStates" :key="state">
                                            <option :value="state" x-text="state"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Oraș *</label>
                                    <select
                                        x-model="formData.city"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :disabled="!formData.state"
                                        required
                                    >
                                        <option value="">Selectează...</option>
                                        <template x-for="city in availableCities" :key="city">
                                            <option :value="city" x-text="city"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Procesor de Plată *</label>
                                <p class="text-sm text-gray-500 mb-4">Selectează sistemul de plăți pe care dorești să-l folosești pentru procesarea plăților de la clienții tăi</p>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($paymentProcessors as $key => $processor)
                                    <div
                                        @click="formData.payment_processor = '{{ $key }}'"
                                        class="border-2 rounded-lg p-4 cursor-pointer transition hover:shadow-md"
                                        :class="formData.payment_processor === '{{ $key }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <div class="flex items-center mb-2">
                                            <input
                                                type="radio"
                                                name="payment_processor"
                                                value="{{ $key }}"
                                                x-model="formData.payment_processor"
                                                class="mr-2"
                                            >
                                            <div class="font-semibold text-gray-900">{{ $processor['name'] }}</div>
                                        </div>
                                        <p class="text-xs text-gray-600 mb-2">{{ $processor['description'] }}</p>
                                        <div class="text-xs text-gray-500">
                                            <div><strong>Monede:</strong> {{ implode(', ', array_slice($processor['supported_currencies'], 0, 3)) }}</div>
                                            <div class="mt-1"><strong>Comision:</strong> {{ $processor['fees'] }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <span x-show="errors.payment_processor" class="text-red-500 text-sm" x-text="errors.payment_processor"></span>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 1"
                                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium"
                                >
                                    ← Înapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continuă →</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 3: Websites -->
                    <div x-show="currentStep === 3" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Website-uri și Estimare</h2>
                        <p class="text-gray-600 mb-6">Adaugă domeniile pe care vei vinde bilete</p>

                        <form @submit.prevent="submitStep3()">
                            <!-- No Website Option -->
                            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        x-model="formData.no_website"
                                        @change="if(formData.no_website) { formData.domains = []; formData.subdomain = ''; subdomainError = ''; subdomainAvailable = false; } else { formData.domains = ['']; formData.subdomain = ''; }"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span class="ml-3 text-sm font-medium text-gray-900">
                                        Nu am un website propriu - vreau un subdomeniu pe ticks.ro
                                    </span>
                                </label>
                                <p class="text-xs text-gray-600 mt-2 ml-6">
                                    Vei primi un subdomeniu gratuit care va fi activat automat (ex: teatrul-tau.ticks.ro)
                                </p>
                            </div>

                            <!-- Subdomain Input (shown when no_website is checked) -->
                            <div class="mb-6" x-show="formData.no_website" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Alege subdomeniul tău *</label>
                                <div class="flex items-center">
                                    <input
                                        type="text"
                                        x-model="formData.subdomain"
                                        @input.debounce.500ms="checkSubdomainAvailability()"
                                        class="flex-1 px-4 py-2 border rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="subdomainError ? 'border-red-500' : (subdomainAvailable ? 'border-green-500' : 'border-gray-300')"
                                        placeholder="numele-tau"
                                        :required="formData.no_website"
                                    >
                                    <span class="px-4 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-600 font-medium">
                                        .ticks.ro
                                    </span>
                                    <div class="ml-3">
                                        <svg x-show="subdomainChecking" class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg x-show="subdomainAvailable && !subdomainChecking && !subdomainError" class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <svg x-show="subdomainError && !subdomainChecking" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    Doar litere mici, cifre și cratime. Minim 3 caractere.
                                </p>
                                <span x-show="subdomainError" class="text-red-500 text-sm" x-text="subdomainError"></span>
                                <span x-show="subdomainAvailable && !subdomainError && formData.subdomain.length >= 3" class="text-green-500 text-sm">
                                    ✓ Subdomeniul este disponibil
                                </span>
                            </div>

                            <!-- Domain URLs (hidden when no_website is checked) -->
                            <div class="mb-6" x-show="!formData.no_website">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Domenii Website *</label>
                                <div class="space-y-3">
                                    <template x-for="(domain, index) in formData.domains" :key="index">
                                        <div>
                                            <div class="flex gap-2">
                                                <input
                                                    type="url"
                                                    x-model="formData.domains[index]"
                                                    @input.debounce.500ms="checkDomainAvailability(index)"
                                                    class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    :class="domainErrors[index] ? 'border-red-500' : 'border-gray-300'"
                                                    placeholder="https://example.com"
                                                    :required="!formData.no_website"
                                                >
                                                <button
                                                    type="button"
                                                    @click="formData.domains.splice(index, 1); delete domainErrors[index]"
                                                    x-show="formData.domains.length > 1"
                                                    class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200"
                                                >
                                                    Șterge
                                                </button>
                                            </div>
                                            <span x-show="domainErrors[index]" class="text-red-500 text-sm" x-text="domainErrors[index]"></span>
                                        </div>
                                    </template>
                                </div>
                                <button
                                    type="button"
                                    @click="formData.domains.push('')"
                                    class="mt-3 text-sm text-blue-600 hover:text-blue-800"
                                >
                                    + Adaugă alt domeniu
                                </button>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Estimare Bilete Lunare *</label>
                                <select
                                    x-model="formData.estimated_monthly_tickets"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                                    <option value="">Selectează...</option>
                                    <option value="0">0 - 100 bilete/lună</option>
                                    <option value="100">100 - 500 bilete/lună</option>
                                    <option value="500">500 - 1.000 bilete/lună</option>
                                    <option value="1000">1.000 - 5.000 bilete/lună</option>
                                    <option value="5000">5.000 - 10.000 bilete/lună</option>
                                    <option value="10000">peste 10.000 bilete/lună</option>
                                </select>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 2"
                                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium"
                                >
                                    ← Înapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continuă →</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 4: Work Method & Microservices -->
                    <div x-show="currentStep === 4" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Metoda de Lucru și Servicii</h2>
                        <p class="text-gray-600 mb-6">Alege modul în care vei utiliza platforma</p>

                        <form @submit.prevent="submitStep4()">
                            <div class="mb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-4">Metoda de Lucru *</label>
                                <div class="grid grid-cols-3 gap-4">
                                    <div
                                        @click="formData.work_method = 'exclusive'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'exclusive' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-blue-600 mb-2">1%</div>
                                            <div class="font-semibold mb-1">Exclusiv</div>
                                            <div class="text-xs text-gray-500">Vânzări exclusiv prin ePas</div>
                                        </div>
                                    </div>
                                    <div
                                        @click="formData.work_method = 'mixed'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'mixed' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-green-600 mb-2">2%</div>
                                            <div class="font-semibold mb-1">Mixt</div>
                                            <div class="text-xs text-gray-500">ePas + alte platforme</div>
                                        </div>
                                    </div>
                                    <div
                                        @click="formData.work_method = 'reseller'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'reseller' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-orange-600 mb-2">3%</div>
                                            <div class="font-semibold mb-1">Reseller</div>
                                            <div class="text-xs text-gray-500">Revânzare bilete</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Limbă Preferată *</label>
                                <select
                                    x-model="formData.locale"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                                    <option value="ro">Română</option>
                                    <option value="en">English</option>
                                    <option value="hu">Magyar</option>
                                    <option value="de">Deutsch</option>
                                    <option value="fr">Français</option>
                                </select>
                            </div>

                            <div class="mb-8">
                                <label class="block text-sm font-medium text-gray-700 mb-4">Microservicii Adiționale (opțional)</label>
                                <div class="space-y-3">
                                    @foreach($microservices as $microservice)
                                    <label class="flex items-start p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="{{ $microservice->id }}"
                                            @change="toggleMicroservice({{ $microservice->id }})"
                                            class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div class="font-medium">{{ $microservice->getTranslation('name', app()->getLocale()) }}</div>
                                                <a href="/microservice/{{ $microservice->slug }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 underline" @click.stop>
                                                    Detalii →
                                                </a>
                                            </div>
                                            <div class="text-sm text-gray-500">{{ $microservice->getTranslation('short_description', app()->getLocale()) }}</div>
                                            <div class="text-sm font-semibold text-blue-600 mt-1">
                                                {{ number_format($microservice->price, 2) }} RON / {{ $microservice->pricing_model }}
                                            </div>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Terms & Conditions and GDPR Agreements -->
                            <div class="mb-8 space-y-4 p-4 bg-gray-50 rounded-lg">
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        type="checkbox"
                                        x-model="formData.agree_terms"
                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        required
                                    >
                                    <span class="ml-3 text-sm text-gray-700">
                                        Am citit și sunt de acord cu
                                        <a href="/termeni-si-conditii" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Termenii și Condițiile</a> *
                                    </span>
                                </label>
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        type="checkbox"
                                        x-model="formData.agree_gdpr"
                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        required
                                    >
                                    <span class="ml-3 text-sm text-gray-700">
                                        Sunt de acord cu
                                        <a href="/politica-confidentialitate" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Procesarea Datelor cu Caracter Personal (GDPR)</a> *
                                    </span>
                                </label>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 3"
                                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium"
                                >
                                    ← Înapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading || !formData.agree_terms || !formData.agree_gdpr"
                                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium text-lg"
                                >
                                    <span x-show="!loading">Finalizează Înregistrarea ✓</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function wizardData() {
            return {
                currentStep: {{ $step }},
                loading: false,
                cuiLoading: false,
                errors: {},
                emailChecking: false,
                emailStatus: '', // 'available', 'taken', ''
                domainErrors: {},
                subdomainError: '',
                subdomainAvailable: false,
                subdomainChecking: false,
                // Modal state
                showModal: false,
                modalTitle: '',
                modalMessage: '',
                modalType: 'info', // 'success', 'error', 'info'
                modalCallback: null,
                formData: {
                    // Step 1
                    first_name: '',
                    last_name: '',
                    public_name: '',
                    email: '',
                    phone: '',
                    phone_country: '+40',
                    phone_number: '',
                    contact_position: '',
                    password: '',
                    password_confirmation: '',
                    // Step 2
                    country: 'Romania',
                    vat_payer: true,
                    cui: '',
                    company_name: '',
                    reg_com: '',
                    address: '',
                    city: '',
                    state: '',
                    payment_processor: 'stripe',
                    // Step 3
                    domains: [''],
                    no_website: false,
                    subdomain: '',
                    estimated_monthly_tickets: '',
                    // Step 4
                    work_method: 'mixed',
                    microservices: [],
                    locale: 'ro',
                    agree_terms: false,
                    agree_gdpr: false
                },
                passwordStrength: 0,
                passwordStrengthClass: '',
                passwordStrengthText: '',
                availableStates: [],
                availableCities: [],

                init() {
                    // Load existing data from session if any
                    @if(isset($data) && !empty($data))
                        this.formData = Object.assign(this.formData, @json($data));
                    @endif

                    // Load states for Romania by default
                    if (this.formData.country === 'Romania') {
                        this.loadStates();
                    }
                },

                getStepTitle(step) {
                    const titles = {
                        1: 'Date Personale',
                        2: 'Companie',
                        3: 'Website-uri',
                        4: 'Metoda de Lucru'
                    };
                    return titles[step];
                },

                // Modal functions
                openModal(title, message, type = 'info', callback = null) {
                    this.modalTitle = title;
                    this.modalMessage = message;
                    this.modalType = type;
                    this.modalCallback = callback;
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    if (this.modalCallback) {
                        this.modalCallback();
                        this.modalCallback = null;
                    }
                },

                checkPasswordStrength() {
                    const password = this.formData.password;
                    let strength = 0;

                    if (password.length >= 8) strength++;
                    if (password.length >= 12) strength++;
                    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                    if (/\d/.test(password)) strength++;
                    if (/[^a-zA-Z\d]/.test(password)) strength++;

                    this.passwordStrength = strength;

                    if (strength <= 2) {
                        this.passwordStrengthClass = 'strength-weak';
                        this.passwordStrengthText = 'Parolă slabă';
                    } else if (strength <= 4) {
                        this.passwordStrengthClass = 'strength-medium';
                        this.passwordStrengthText = 'Parolă medie';
                    } else {
                        this.passwordStrengthClass = 'strength-strong';
                        this.passwordStrengthText = 'Parolă puternică';
                    }
                },

                async submitStep1() {
                    // Check if email is taken
                    if (this.emailStatus === 'taken') {
                        this.openModal('Email indisponibil', 'Această adresă de email este deja înregistrată. Te rugăm să folosești altă adresă.', 'error');
                        return;
                    }

                    this.loading = true;
                    this.errors = {};

                    // Combine phone number
                    this.formData.phone = this.formData.phone_country + this.formData.phone_number.replace(/\s/g, '');

                    try {
                        const response = await fetch('{{ route("onboarding.step1") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A apărut o eroare. Te rugăm să încerci din nou.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitStep2() {
                    this.loading = true;
                    this.errors = {};

                    try {
                        const response = await fetch('{{ route("onboarding.step2") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A apărut o eroare. Te rugăm să încerci din nou.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitStep3() {
                    this.loading = true;
                    this.errors = {};

                    // Validate based on mode
                    if (this.formData.no_website) {
                        if (!this.formData.subdomain || this.formData.subdomain.length < 3) {
                            this.openModal('Eroare', 'Te rugăm să alegi un subdomeniu valid (minim 3 caractere)', 'error');
                            this.loading = false;
                            return;
                        }
                        if (!this.subdomainAvailable) {
                            this.openModal('Eroare', 'Subdomeniul nu este disponibil. Te rugăm să alegi altul.', 'error');
                            this.loading = false;
                            return;
                        }
                    } else {
                        if (!this.formData.domains.length || !this.formData.domains[0]) {
                            this.openModal('Eroare', 'Te rugăm să adaugi cel puțin un domeniu', 'error');
                            this.loading = false;
                            return;
                        }
                    }

                    try {
                        const response = await fetch('{{ route("onboarding.step3") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A apărut o eroare. Te rugăm să încerci din nou.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitStep4() {
                    this.loading = true;
                    this.errors = {};

                    try {
                        const response = await fetch('{{ route("onboarding.step4") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.openModal('Succes', data.message, 'success', () => {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                }
                            });
                        } else {
                            this.errors = data.errors || {};
                            let errorMsg = data.message || 'A apărut o eroare.';
                            if (data.error) {
                                errorMsg += '\n\nDetalii: ' + data.error;
                                if (data.file) {
                                    errorMsg += '\nFișier: ' + data.file;
                                }
                            }
                            this.openModal('Eroare', errorMsg, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A apărut o eroare. Te rugăm să încerci din nou.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async lookupCui() {
                    if (!this.formData.cui) return;

                    this.cuiLoading = true;

                    try {
                        const response = await fetch('{{ route("onboarding.lookup-cui") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ cui: this.formData.cui })
                        });

                        const result = await response.json();

                        if (result.success) {
                            const data = result.data;
                            this.formData.company_name = data.company_name || this.formData.company_name;
                            this.formData.reg_com = data.reg_com || this.formData.reg_com;
                            this.formData.address = data.address || this.formData.address;
                            this.formData.vat_payer = data.vat_payer;

                            // Match state from ANAF with available states (case-insensitive)
                            if (data.state) {
                                const anafState = data.state.toLowerCase().trim();
                                const matchedState = this.availableStates.find(s =>
                                    s.toLowerCase().trim() === anafState ||
                                    s.toLowerCase().trim().includes(anafState) ||
                                    anafState.includes(s.toLowerCase().trim())
                                );

                                if (matchedState) {
                                    this.formData.state = matchedState;
                                    // Load cities for the matched state
                                    await this.loadCities();

                                    // Now try to match the city (case-insensitive)
                                    if (data.city && this.availableCities.length > 0) {
                                        const anafCity = data.city.toLowerCase().trim();
                                        const matchedCity = this.availableCities.find(c =>
                                            c.toLowerCase().trim() === anafCity ||
                                            c.toLowerCase().trim().includes(anafCity) ||
                                            anafCity.includes(c.toLowerCase().trim())
                                        );

                                        if (matchedCity) {
                                            this.formData.city = matchedCity;
                                        } else {
                                            // Use ANAF city value if no exact match
                                            this.formData.city = data.city;
                                        }
                                    }
                                } else {
                                    // Use ANAF state value if no match found
                                    this.formData.state = data.state;
                                }
                            }

                            this.openModal('Succes', 'Datele au fost preluate cu succes din ANAF!', 'success');
                        } else {
                            this.openModal('ANAF', result.message || 'Nu s-au găsit date în ANAF pentru acest CUI.', 'info');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'Eroare la interogarea ANAF.', 'error');
                    } finally {
                        this.cuiLoading = false;
                    }
                },

                async loadStates() {
                    // For Romania, load counties
                    if (this.formData.country === 'Romania') {
                        this.availableStates = @json($romaniaCounties);
                    } else {
                        this.availableStates = [];
                    }
                    this.availableCities = [];
                },

                async loadCities() {
                    if (!this.formData.state) {
                        this.availableCities = [];
                        return;
                    }

                    try {
                        const response = await fetch(`/register/api/cities/${encodeURIComponent(this.formData.country)}/${encodeURIComponent(this.formData.state)}`);
                        const data = await response.json();

                        if (data.success && data.cities) {
                            this.availableCities = Object.values(data.cities);
                        } else {
                            this.availableCities = [];
                        }
                    } catch (error) {
                        console.error('Error loading cities:', error);
                        this.availableCities = [];
                    }
                },

                toggleMicroservice(id) {
                    const index = this.formData.microservices.indexOf(id);
                    if (index > -1) {
                        this.formData.microservices.splice(index, 1);
                    } else {
                        this.formData.microservices.push(id);
                    }
                },

                async checkEmailAvailability() {
                    if (!this.formData.email || !this.formData.email.includes('@')) {
                        this.emailStatus = '';
                        return;
                    }

                    this.emailChecking = true;
                    this.emailStatus = '';

                    try {
                        const response = await fetch('{{ route("onboarding.check-email") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ email: this.formData.email })
                        });

                        const data = await response.json();
                        this.emailStatus = data.available ? 'available' : 'taken';
                    } catch (error) {
                        console.error('Error checking email:', error);
                        this.emailStatus = '';
                    } finally {
                        this.emailChecking = false;
                    }
                },

                async checkDomainAvailability(index) {
                    const domain = this.formData.domains[index];
                    if (!domain) {
                        this.domainErrors[index] = '';
                        return;
                    }

                    try {
                        const response = await fetch('{{ route("onboarding.check-domain") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ domain: domain })
                        });

                        const data = await response.json();
                        this.domainErrors[index] = data.available ? '' : data.message;
                    } catch (error) {
                        console.error('Error checking domain:', error);
                        this.domainErrors[index] = '';
                    }
                },

                async checkSubdomainAvailability() {
                    const subdomain = this.formData.subdomain.toLowerCase().trim();

                    // Reset state
                    this.subdomainError = '';
                    this.subdomainAvailable = false;

                    // Validate format locally first
                    if (subdomain.length < 3) {
                        this.subdomainError = 'Subdomeniul trebuie să aibă minim 3 caractere';
                        return;
                    }

                    if (subdomain.length > 63) {
                        this.subdomainError = 'Subdomeniul nu poate avea mai mult de 63 de caractere';
                        return;
                    }

                    if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/.test(subdomain)) {
                        this.subdomainError = 'Subdomeniul poate conține doar litere mici, cifre și cratime (nu poate începe sau termina cu cratimă)';
                        return;
                    }

                    // Reserved subdomains
                    const reserved = ['www', 'mail', 'ftp', 'admin', 'api', 'app', 'cdn', 'static', 'assets', 'test', 'demo', 'staging', 'dev', 'core', 'panel', 'dashboard', 'login', 'register', 'auth', 'oauth', 'shop', 'store', 'help', 'support', 'docs', 'status', 'blog', 'news'];
                    if (reserved.includes(subdomain)) {
                        this.subdomainError = 'Acest subdomeniu este rezervat';
                        return;
                    }

                    this.subdomainChecking = true;

                    try {
                        const response = await fetch('{{ route("onboarding.check-subdomain") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ subdomain: subdomain })
                        });

                        const data = await response.json();
                        this.subdomainAvailable = data.available;
                        if (!data.available) {
                            this.subdomainError = data.message || 'Subdomeniul nu este disponibil';
                        }
                    } catch (error) {
                        console.error('Error checking subdomain:', error);
                        this.subdomainError = 'Eroare la verificare. Încearcă din nou.';
                    } finally {
                        this.subdomainChecking = false;
                    }
                },

                getFullPhone() {
                    return this.formData.phone_country + this.formData.phone_number.replace(/\s/g, '');
                }
            }
        }
    </script>
</body>
</html>
