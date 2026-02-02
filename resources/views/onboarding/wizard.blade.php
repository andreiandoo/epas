<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Înregistrare - Tixello</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        dark: {
                            900: '#09090b',
                            800: '#0a0a0f',
                            700: '#0f0f17',
                            600: '#1a1a2e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #09090b 0%, #0f0f17 50%, #1a1a2e 100%);
        }

        /* Animated background gradient */
        .bg-animated {
            background: linear-gradient(-45deg, #09090b, #1a1a2e, #0f172a, #09090b);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Animated gradient text */
        .gradient-text {
            background: linear-gradient(90deg, #8B5CF6, #06B6D4, #10B981, #8B5CF6);
            background-size: 300% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textGradient 6s ease infinite;
        }

        @keyframes textGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Glassmorphism card */
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(139, 92, 246, 0.3);
        }

        /* Input styling */
        .dark-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .dark-input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #8B5CF6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            outline: none;
        }

        .dark-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        /* Step indicator */
        .step-indicator {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .step-indicator.active {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            border-color: transparent;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
            transform: scale(1.1);
        }

        .step-indicator.completed {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border-color: transparent;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }

        /* Progress line */
        .progress-line {
            background: rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .progress-line.completed::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #10B981, #059669);
            animation: fillLine 0.5s ease forwards;
        }

        @keyframes fillLine {
            from { width: 0; }
            to { width: 100%; }
        }

        /* Step content animation */
        .step-content {
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Password strength meter */
        .password-strength-meter {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength-fill {
            height: 100%;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            width: 33%;
        }
        .strength-medium {
            background: linear-gradient(90deg, #f59e0b, #d97706);
            width: 66%;
        }
        .strength-strong {
            background: linear-gradient(90deg, #10B981, #059669);
            width: 100%;
        }

        /* Button styles */
        .btn-primary {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .btn-success:hover {
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
            transform: translateY(-2px);
        }

        /* Selection cards */
        .selection-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .selection-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }

        .selection-card.selected {
            background: rgba(139, 92, 246, 0.15);
            border-color: #8B5CF6;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
        }

        /* Modal backdrop */
        .modal-backdrop {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
        }

        /* Floating particles animation */
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(139, 92, 246, 0.3);
            border-radius: 50%;
            animation: float 15s infinite;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 20s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 2s; animation-duration: 25s; }
        .particle:nth-child(3) { left: 30%; animation-delay: 4s; animation-duration: 18s; }
        .particle:nth-child(4) { left: 40%; animation-delay: 1s; animation-duration: 22s; }
        .particle:nth-child(5) { left: 50%; animation-delay: 3s; animation-duration: 20s; }
        .particle:nth-child(6) { left: 60%; animation-delay: 5s; animation-duration: 24s; }
        .particle:nth-child(7) { left: 70%; animation-delay: 2s; animation-duration: 19s; }
        .particle:nth-child(8) { left: 80%; animation-delay: 4s; animation-duration: 21s; }
        .particle:nth-child(9) { left: 90%; animation-delay: 1s; animation-duration: 23s; }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* Glow effect */
        .glow-purple {
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.3);
        }

        .glow-cyan {
            box-shadow: 0 0 40px rgba(6, 182, 212, 0.3);
        }

        .glow-emerald {
            box-shadow: 0 0 40px rgba(16, 185, 129, 0.3);
        }

        /* Checkbox styling */
        .dark-checkbox {
            appearance: none;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dark-checkbox:checked {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            border-color: transparent;
        }

        .dark-checkbox:checked::after {
            content: '\\2713';
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }

        /* Select styling */
        .dark-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }

        .dark-select option {
            background: #1a1a2e;
            color: white;
        }

        /* Work method cards */
        .method-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }

        .method-card:hover {
            transform: translateY(-4px);
        }

        .method-card.selected {
            background: rgba(139, 92, 246, 0.1);
            border-color: #8B5CF6;
        }

        .method-card.selected .method-percentage {
            text-shadow: 0 0 20px currentColor;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.5);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.7);
        }
    </style>
</head>
<body class="min-h-screen bg-animated text-white">
    <!-- Floating particles background -->
    <div class="floating-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="min-h-screen flex flex-col relative z-10" x-data="wizardData()" x-init="init()">
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
                            <svg x-show="modalType === 'info'" class="h-6 w-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg leading-6 font-medium text-white" x-text="modalTitle"></h3>
                            <div class="mt-2">
                                <p class="text-sm text-white/60 whitespace-pre-line" x-text="modalMessage"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6">
                        <button type="button" @click="closeModal()" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:text-sm" :class="modalType === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : (modalType === 'error' ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-purple-500')">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Header -->
        <header class="glass-card border-b border-white/10">
            <div class="max-w-5xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold gradient-text">Tixello</h1>
                        <p class="text-sm text-white/60">Platforma ta de ticketing pentru evenimente</p>
                    </div>
                    <div class="text-sm text-white/60">
                        Ai deja cont? <a href="/admin" class="text-purple-400 hover:text-purple-300 font-medium transition-colors">Autentifică-te</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Progress Steps -->
        <div class="glass-card border-b border-white/10 py-6">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex items-center justify-between">
                    <template x-for="i in 4" :key="i">
                        <div class="flex items-center" :class="{'flex-1': i < 4}">
                            <div class="flex items-center">
                                <div
                                    class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white"
                                    :class="{
                                        'active': currentStep === i,
                                        'completed': currentStep > i
                                    }"
                                >
                                    <span x-show="currentStep < i || currentStep === i" x-text="i"></span>
                                    <svg x-show="currentStep > i" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3 hidden sm:block">
                                    <div class="text-sm font-medium" :class="currentStep >= i ? 'text-white' : 'text-white/40'">
                                        Pasul <span x-text="i"></span>
                                    </div>
                                    <div class="text-xs text-white/50" x-text="getStepTitle(i)"></div>
                                </div>
                            </div>
                            <div
                                x-show="i < 4"
                                class="flex-1 h-1 mx-4 progress-line rounded-full"
                                :class="currentStep > i ? 'completed' : ''"
                            ></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 py-12 px-4">
            <div class="max-w-3xl mx-auto">
                <div class="glass-card rounded-2xl p-8 glow-purple">
                    <!-- Step 1: Personal Info -->
                    <div x-show="currentStep === 1" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Informații Personale</h2>
                        <p class="text-white/60 mb-6">Introdu datele tale de contact pentru crearea contului</p>

                        <form @submit.prevent="submitStep1()">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Prenume *</label>
                                    <input
                                        type="text"
                                        x-model="formData.first_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        required
                                    >
                                    <span x-show="errors.first_name" class="text-red-400 text-sm" x-text="errors.first_name"></span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Nume *</label>
                                    <input
                                        type="text"
                                        x-model="formData.last_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        required
                                    >
                                    <span x-show="errors.last_name" class="text-red-400 text-sm" x-text="errors.last_name"></span>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Nume Public Organizație *</label>
                                <input
                                    type="text"
                                    x-model="formData.public_name"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    placeholder="ex: Teatrul Odeon"
                                    required
                                >
                                <p class="text-xs text-white/50 mt-1">Numele sub care va fi afișată organizația (poate fi diferit de denumirea legală)</p>
                                <span x-show="errors.public_name" class="text-red-400 text-sm" x-text="errors.public_name"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-white/80 mb-3">Ce tip de activitate desfășurați? *</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <template x-for="type in organizerTypes" :key="type.value">
                                        <div
                                            @click="formData.organizer_type = type.value"
                                            class="selection-card rounded-xl p-4 text-center"
                                            :class="formData.organizer_type === type.value ? 'selected' : ''"
                                        >
                                            <span class="text-3xl block mb-2" x-text="type.icon"></span>
                                            <div class="font-medium text-white text-sm" x-text="type.label"></div>
                                            <p class="text-xs text-white/50 mt-1" x-text="type.description"></p>
                                        </div>
                                    </template>
                                </div>
                                <span x-show="errors.organizer_type" class="text-red-400 text-sm mt-1 block" x-text="errors.organizer_type"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Email *</label>
                                <div class="relative">
                                    <input
                                        type="email"
                                        x-model="formData.email"
                                        @input.debounce.500ms="checkEmailAvailability()"
                                        class="w-full px-4 py-3 dark-input rounded-lg pr-10"
                                        :class="emailStatus === 'available' ? 'border-emerald-500' : (emailStatus === 'taken' ? 'border-red-500' : '')"
                                        required
                                    >
                                    <div class="absolute right-3 top-3.5">
                                        <svg x-show="emailChecking" class="animate-spin h-5 w-5 text-purple-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg x-show="emailStatus === 'available' && !emailChecking" class="h-5 w-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <svg x-show="emailStatus === 'taken' && !emailChecking" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                <span x-show="emailStatus === 'taken'" class="text-red-400 text-sm">Această adresă de email este deja înregistrată</span>
                                <span x-show="errors.email" class="text-red-400 text-sm" x-text="errors.email"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Telefon *</label>
                                <div class="flex">
                                    <select
                                        x-model="formData.phone_country"
                                        class="w-28 px-2 py-2 border border-white/20 rounded-l-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white/5"
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
                                        class="flex-1 px-4 py-2 border border-l-0 border-white/20 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        placeholder="xxx xxx xxx"
                                        required
                                    >
                                </div>
                                <span x-show="errors.phone" class="text-red-400 text-sm" x-text="errors.phone"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Funcție în Companie</label>
                                <input
                                    type="text"
                                    x-model="formData.contact_position"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    placeholder="ex: Director General, Administrator"
                                >
                                <p class="text-xs text-white/50 mt-1">Funcția pe care o ocupi în companie (opțional)</p>
                                <span x-show="errors.contact_position" class="text-red-400 text-sm" x-text="errors.contact_position"></span>
                            </div>

                            <div class="mt-6 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Parolă *</label>
                                    <input
                                        type="password"
                                        x-model="formData.password"
                                        @input="checkPasswordStrength()"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        required
                                        minlength="8"
                                    >
                                    <div class="password-strength-meter mt-2">
                                        <div class="password-strength-fill" :class="passwordStrengthClass"></div>
                                    </div>
                                    <p class="text-xs text-white/50 mt-1" x-text="passwordStrengthText"></p>
                                    <span x-show="errors.password" class="text-red-400 text-sm" x-text="errors.password"></span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Confirmă Parola *</label>
                                    <input
                                        type="password"
                                        x-model="formData.password_confirmation"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 btn-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continuă →</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Company Info -->
                    <div x-show="currentStep === 2" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Informații Companie</h2>
                        <p class="text-white/60 mb-6">Detalii despre firma ta pentru facturare și contracte</p>

                        <form @submit.prevent="submitStep2()">
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Țară *</label>
                                <select
                                    x-model="formData.country"
                                    @change="loadStates()"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
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
                                        class="rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                    >
                                    <span class="ml-2 text-sm text-white/80">Plătitor de TVA</span>
                                </label>
                            </div>

                            <div class="mb-6" x-show="formData.country === 'Romania'">
                                <label class="block text-sm font-medium text-white/80 mb-2">CUI / CIF</label>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        x-model="formData.cui"
                                        class="flex-1 px-4 py-2 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        placeholder="ex: RO12345678"
                                    >
                                    <button
                                        type="button"
                                        @click="lookupCui()"
                                        :disabled="!formData.cui || cuiLoading"
                                        class="px-4 py-2 btn-success text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span x-show="!cuiLoading">Verifică ANAF</span>
                                        <span x-show="cuiLoading">...</span>
                                    </button>
                                </div>
                                <p class="text-xs text-white/50 mt-1">Completează CUI-ul pentru a prelua automat datele firmei din ANAF</p>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Nume Companie *</label>
                                <input
                                    type="text"
                                    x-model="formData.company_name"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    required
                                >
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Registrul Comerțului</label>
                                <input
                                    type="text"
                                    x-model="formData.reg_com"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    placeholder="ex: J40/12345/2020"
                                >
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Adresă *</label>
                                <textarea
                                    x-model="formData.address"
                                    rows="2"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    required
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Județ / Sector *</label>
                                    <select
                                        x-model="formData.state"
                                        @change="loadCities()"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        required
                                    >
                                        <option value="">Selectează...</option>
                                        <template x-for="state in availableStates" :key="state">
                                            <option :value="state" x-text="state"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Oraș *</label>
                                    <select
                                        x-model="formData.city"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
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
                                <label class="block text-sm font-medium text-white/80 mb-3">Procesor de Plată</label>
                                <p class="text-sm text-white/60 mb-4">Selectează sistemul de plăți pe care dorești să-l folosești pentru procesarea plăților de la clienții tăi</p>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($paymentProcessors as $key => $processor)
                                    <div
                                        @click="formData.payment_processor = '{{ $key }}'"
                                        class="border-2 rounded-lg p-4 cursor-pointer transition hover:shadow-md"
                                        :class="formData.payment_processor === '{{ $key }}' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="flex items-center mb-2">
                                            <input
                                                type="radio"
                                                name="payment_processor"
                                                value="{{ $key }}"
                                                x-model="formData.payment_processor"
                                                class="mr-2"
                                            >
                                            <div class="font-semibold text-white">{{ $processor['name'] }}</div>
                                        </div>
                                        <p class="text-xs text-white/60 mb-2">{{ $processor['description'] }}</p>
                                        <div class="text-xs text-white/50">
                                            <div><strong>Monede:</strong> {{ implode(', ', array_slice($processor['supported_currencies'], 0, 3)) }}</div>
                                            <div class="mt-1"><strong>Comision:</strong> {{ $processor['fees'] }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                    <!-- Nu știu option -->
                                    <div
                                        @click="formData.payment_processor = 'unknown'"
                                        class="border-2 rounded-lg p-4 cursor-pointer transition hover:shadow-md col-span-2"
                                        :class="formData.payment_processor === 'unknown' ? 'border-amber-500 bg-amber-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="flex items-center mb-2">
                                            <input
                                                type="radio"
                                                name="payment_processor"
                                                value="unknown"
                                                x-model="formData.payment_processor"
                                                class="mr-2"
                                            >
                                            <div class="font-semibold text-white">🤔 Nu știu acum</div>
                                        </div>
                                        <p class="text-xs text-white/60">Voi decide și configura procesorul de plăți mai târziu din panoul de administrare.</p>
                                    </div>
                                </div>
                                <span x-show="errors.payment_processor" class="text-red-400 text-sm" x-text="errors.payment_processor"></span>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 1"
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    ← Înapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 btn-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continuă →</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 3: Websites -->
                    <div x-show="currentStep === 3" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Website-uri și Estimare</h2>
                        <p class="text-white/60 mb-6">Adaugă domeniile pe care vei vinde bilete</p>

                        <form @submit.prevent="submitStep3()">
                            <!-- No website option -->
                            <div class="mb-6">
                                <label class="flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all duration-200"
                                       :class="formData.has_no_website ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'">
                                    <input
                                        type="checkbox"
                                        x-model="formData.has_no_website"
                                        class="w-5 h-5 text-purple-500 rounded focus:ring-purple-500"
                                    >
                                    <div>
                                        <div class="font-medium text-white">🌐 Nu am website propriu</div>
                                        <p class="text-sm text-white/60">Vreau să primesc un subdomeniu gratuit pe tics.ro</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Subdomain input (shown when no website is checked) -->
                            <div x-show="formData.has_no_website" x-cloak class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
                                <label class="block text-sm font-medium text-white/80 mb-2">Alege-ți subdomeniul gratuit pe tics.ro</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        x-model="formData.subdomain"
                                        @input.debounce.500ms="checkSubdomainAvailability()"
                                        class="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg"
                                        :class="subdomainError ? 'border-red-500' : (subdomainAvailable ? 'border-green-500' : 'border-white/20')"
                                        placeholder="organizatia-ta"
                                    >
                                    <span class="text-lg font-medium text-white/80">.tics.ro</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2" x-show="subdomainChecking">
                                    <svg class="animate-spin h-4 w-4 text-white/50" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm text-white/60">Se verifică disponibilitatea...</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2 text-green-600" x-show="subdomainAvailable && !subdomainChecking && formData.subdomain">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm" x-text="formData.subdomain + '.tics.ro este disponibil!'"></span>
                                </div>
                                <span x-show="subdomainError" class="text-red-500 text-sm mt-2 block" x-text="subdomainError"></span>
                                <p class="text-xs text-white/50 mt-2">Website-ul tău va fi accesibil la adresa <strong x-text="(formData.subdomain || 'subdomeniu') + '.tics.ro'"></strong></p>
                            </div>

                            <!-- Domains input (shown when has website) -->
                            <div x-show="!formData.has_no_website" class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Domenii Website *</label>
                                <div class="space-y-3">
                                    <template x-for="(domain, index) in formData.domains" :key="index">
                                        <div>
                                            <div class="flex gap-2">
                                                <input
                                                    type="url"
                                                    x-model="formData.domains[index]"
                                                    @input.debounce.500ms="checkDomainAvailability(index)"
                                                    class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                                    :class="domainErrors[index] ? 'border-red-500' : 'border-white/20'"
                                                    placeholder="https://example.com"
                                                    :required="!formData.has_no_website"
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
                                            <span x-show="domainErrors[index]" class="text-red-400 text-sm" x-text="domainErrors[index]"></span>
                                        </div>
                                    </template>
                                </div>
                                <button
                                    type="button"
                                    @click="formData.domains.push('')"
                                    class="mt-3 text-sm text-purple-500 hover:text-blue-800"
                                >
                                    + Adaugă alt domeniu
                                </button>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Estimare Bilete Lunare *</label>
                                <select
                                    x-model="formData.estimated_monthly_tickets"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
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
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    ← Înapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 btn-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continuă →</span>
                                    <span x-show="loading">Se procesează...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 4: Work Method & Microservices -->
                    <div x-show="currentStep === 4" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Metoda de Lucru și Servicii</h2>
                        <p class="text-white/60 mb-6">Alege modul în care vei utiliza platforma</p>

                        <form @submit.prevent="submitStep4()">
                            <div class="mb-8">
                                <label class="block text-sm font-medium text-white/80 mb-4">Metoda de Lucru *</label>
                                <div class="grid grid-cols-3 gap-4">
                                    <div
                                        @click="formData.work_method = 'exclusive'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'exclusive' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-purple-500 mb-2">1%</div>
                                            <div class="font-semibold mb-1">Exclusiv</div>
                                            <div class="text-xs text-white/50">Vânzări exclusiv prin ePas</div>
                                        </div>
                                    </div>
                                    <div
                                        @click="formData.work_method = 'mixed'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'mixed' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-green-600 mb-2">2%</div>
                                            <div class="font-semibold mb-1">Mixt</div>
                                            <div class="text-xs text-white/50">ePas + alte platforme</div>
                                        </div>
                                    </div>
                                    <div
                                        @click="formData.work_method = 'reseller'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'reseller' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-orange-600 mb-2">3%</div>
                                            <div class="font-semibold mb-1">Reseller</div>
                                            <div class="text-xs text-white/50">Revânzare bilete</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Limbă Preferată *</label>
                                <select
                                    x-model="formData.locale"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
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
                                <label class="block text-sm font-medium text-white/80 mb-4">Microservicii Adiționale (opțional)</label>
                                <div class="space-y-3">
                                    @foreach($microservices as $microservice)
                                    <label class="flex items-start p-4 border rounded-lg hover:bg-white/5 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="{{ $microservice->id }}"
                                            @change="toggleMicroservice({{ $microservice->id }})"
                                            class="mt-1 rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                        >
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div class="font-medium">{{ $microservice->getTranslation('name', app()->getLocale()) }}</div>
                                                <a href="/microservice/{{ $microservice->slug }}" target="_blank" class="text-xs text-purple-500 hover:text-blue-800 underline" @click.stop>
                                                    Detalii →
                                                </a>
                                            </div>
                                            <div class="text-sm text-white/60">{{ $microservice->getTranslation('short_description', app()->getLocale()) }}</div>
                                            <div class="text-sm font-semibold text-purple-500 mt-1">
                                                {{ number_format($microservice->price, 2) }} RON / {{ $microservice->pricing_model }}
                                            </div>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Terms & Conditions and GDPR Agreements -->
                            <div class="mb-8 space-y-4 p-4 bg-white/5 rounded-lg">
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        type="checkbox"
                                        x-model="formData.agree_terms"
                                        class="mt-1 rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                        required
                                    >
                                    <span class="ml-3 text-sm text-white/80">
                                        Am citit și sunt de acord cu
                                        <a href="/termeni-si-conditii" target="_blank" class="text-purple-500 hover:text-blue-800 underline">Termenii și Condițiile</a> *
                                    </span>
                                </label>
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        type="checkbox"
                                        x-model="formData.agree_gdpr"
                                        class="mt-1 rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                        required
                                    >
                                    <span class="ml-3 text-sm text-white/80">
                                        Sunt de acord cu
                                        <a href="/politica-confidentialitate" target="_blank" class="text-purple-500 hover:text-blue-800 underline">Procesarea Datelor cu Caracter Personal (GDPR)</a> *
                                    </span>
                                </label>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 3"
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    ← Înapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading || !formData.agree_terms || !formData.agree_gdpr"
                                    class="px-6 py-3 btn-success text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium text-lg"
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
                // Subdomain checking state
                subdomainChecking: false,
                subdomainAvailable: false,
                subdomainError: '',
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
                    organizer_type: '',
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
                    payment_processor: '',
                    // Step 3
                    has_no_website: false,
                    subdomain: '',
                    domains: [''],
                    estimated_monthly_tickets: '',
                    // Step 4
                    work_method: 'mixed',
                    microservices: [],
                    locale: 'ro',
                    agree_terms: false,
                    agree_gdpr: false
                },
                organizerTypes: [
                    { value: 'event_organizer', icon: '🎪', label: 'Organizator evenimente', description: 'Organizez concerte, festivaluri, conferințe' },
                    { value: 'pub_bar', icon: '🍺', label: 'Pub / Bar / Club', description: 'Dețin sau administrez un local' },
                    { value: 'theater', icon: '🎭', label: 'Teatru', description: 'Dețin sau lucrez pentru un teatru' },
                    { value: 'concert_hall', icon: '🎵', label: 'Sală de spectacole', description: 'Dețin sau administrez o sală' },
                    { value: 'philharmonic', icon: '🎻', label: 'Filarmonică / Operă', description: 'Instituție culturală' },
                    { value: 'museum', icon: '🏛️', label: 'Muzeu / Galerie', description: 'Expoziții și evenimente culturale' },
                    { value: 'sports', icon: '⚽', label: 'Evenimente sportive', description: 'Competiții și meciuri' },
                    { value: 'other', icon: '📋', label: 'Altceva', description: 'Alt tip de activitate' }
                ],
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
                    const subdomain = this.formData.subdomain;

                    // Reset state
                    this.subdomainAvailable = false;
                    this.subdomainError = '';

                    if (!subdomain || subdomain.length < 3) {
                        if (subdomain && subdomain.length < 3) {
                            this.subdomainError = 'Subdomeniul trebuie să aibă cel puțin 3 caractere';
                        }
                        return;
                    }

                    // Validate subdomain format
                    if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/.test(subdomain.toLowerCase())) {
                        this.subdomainError = 'Subdomeniul poate conține doar litere, cifre și cratimă (nu la început sau sfârșit)';
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
                        this.subdomainError = data.available ? '' : data.message;
                    } catch (error) {
                        console.error('Error checking subdomain:', error);
                        this.subdomainError = 'Eroare la verificarea disponibilității';
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
