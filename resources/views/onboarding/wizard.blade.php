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
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="min-h-screen flex flex-col" x-data="wizardData()" x-init="init()">
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
                                <input
                                    type="email"
                                    x-model="formData.email"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                                <span x-show="errors.email" class="text-red-500 text-sm" x-text="errors.email"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Telefon *</label>
                                <input
                                    type="tel"
                                    x-model="formData.phone"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="+40 xxx xxx xxx"
                                    required
                                >
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

                            <div class="mt-6">
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

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirmă Parola *</label>
                                <input
                                    type="password"
                                    x-model="formData.password_confirmation"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
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
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Domenii Website *</label>
                                <div class="space-y-3">
                                    <template x-for="(domain, index) in formData.domains" :key="index">
                                        <div class="flex gap-2">
                                            <input
                                                type="url"
                                                x-model="formData.domains[index]"
                                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="https://example.com"
                                                required
                                            >
                                            <button
                                                type="button"
                                                @click="formData.domains.splice(index, 1)"
                                                x-show="formData.domains.length > 1"
                                                class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200"
                                            >
                                                Șterge
                                            </button>
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
                                    :disabled="loading"
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
                formData: {
                    // Step 1
                    first_name: '',
                    last_name: '',
                    public_name: '',
                    email: '',
                    phone: '',
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
                    estimated_monthly_tickets: '',
                    // Step 4
                    work_method: 'mixed',
                    microservices: [],
                    locale: 'ro'
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
                    this.loading = true;
                    this.errors = {};

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
                        alert('A apărut o eroare. Te rugăm să încerci din nou.');
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
                        alert('A apărut o eroare. Te rugăm să încerci din nou.');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitStep3() {
                    this.loading = true;
                    this.errors = {};

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
                        alert('A apărut o eroare. Te rugăm să încerci din nou.');
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
                            alert(data.message);
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        } else {
                            this.errors = data.errors || {};
                            alert(data.message || 'A apărut o eroare.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('A apărut o eroare. Te rugăm să încerci din nou.');
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
                            this.formData.city = data.city || this.formData.city;
                            this.formData.state = data.state || this.formData.state;
                            this.formData.vat_payer = data.vat_payer;

                            // Reload cities if state was auto-filled
                            if (data.state) {
                                await this.loadCities();
                            }

                            alert('Datele au fost preluate cu succes din ANAF!');
                        } else {
                            alert(result.message || 'Nu s-au găsit date în ANAF pentru acest CUI.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Eroare la interogarea ANAF.');
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
                }
            }
        }
    </script>
</body>
</html>
