<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inregistrare - Tixello</title>
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
            content: '\2713';
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

        .dark-select option,
        .dark-input option,
        select.dark-input option {
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

        /* Tenant type grid cards */
        .tenant-type-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .tenant-type-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }

        .tenant-type-card.selected {
            background: rgba(139, 92, 246, 0.15);
            border-color: #8B5CF6;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
        }

        /* Business type radio cards */
        .biz-type-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .biz-type-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .biz-type-card.selected {
            background: rgba(139, 92, 246, 0.12);
            border-color: #8B5CF6;
        }

        /* Success state */
        .success-card {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .verification-code {
            font-family: 'Courier New', monospace;
            letter-spacing: 0.15em;
            background: rgba(139, 92, 246, 0.15);
            border: 2px dashed rgba(139, 92, 246, 0.4);
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
                <div class="inline-block align-bottom bg-dark-700 border border-white/10 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full" :class="modalType === 'success' ? 'bg-green-100' : (modalType === 'error' ? 'bg-red-100' : 'bg-blue-100')">
                            <svg x-show="modalType === 'success'" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg x-show="modalType === 'error'" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
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
                        Ai deja cont? <a href="/admin" class="text-purple-400 hover:text-purple-300 font-medium transition-colors">Autentifica-te</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Progress Steps -->
        <div class="glass-card border-b border-white/10 py-6" x-show="!completed">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex items-center justify-between">
                    <template x-for="i in totalSteps" :key="i">
                        <div class="flex items-center" :class="{'flex-1': i < totalSteps}">
                            <div class="flex items-center">
                                <div
                                    class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white"
                                    :class="{
                                        'active': currentStep === i,
                                        'completed': currentStep > i
                                    }"
                                >
                                    <span x-show="currentStep <= i" x-text="i"></span>
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
                                x-show="i < totalSteps"
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

                    <!-- ============================================================ -->
                    <!-- STEP 1: Date personale -->
                    <!-- ============================================================ -->
                    <div x-show="currentStep === 1" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Date Personale</h2>
                        <p class="text-white/60 mb-6">Introdu datele tale de contact pentru crearea contului</p>

                        <form @submit.prevent="submitStep1()">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Prenume *</label>
                                    <input
                                        type="text"
                                        x-model="formData.first_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        placeholder="Ion"
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
                                        placeholder="Popescu"
                                        required
                                    >
                                    <span x-show="errors.last_name" class="text-red-400 text-sm" x-text="errors.last_name"></span>
                                </div>
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
                                        placeholder="email@exemplu.com"
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
                                <span x-show="emailStatus === 'taken'" class="text-red-400 text-sm">Aceasta adresa de email este deja inregistrata</span>
                                <span x-show="errors.email" class="text-red-400 text-sm" x-text="errors.email"></span>
                            </div>

                            <div class="mt-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Telefon *</label>
                                <div class="flex">
                                    <select
                                        x-model="formData.phone_country"
                                        class="w-28 px-2 py-2 border border-white/20 rounded-l-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white/5"
                                    >
                                        <option value="+40">+40</option>
                                        <option value="+49">+49</option>
                                        <option value="+33">+33</option>
                                        <option value="+44">+44</option>
                                        <option value="+1">+1</option>
                                        <option value="+36">+36</option>
                                        <option value="+359">+359</option>
                                        <option value="+373">+373</option>
                                        <option value="+39">+39</option>
                                        <option value="+34">+34</option>
                                    </select>
                                    <input
                                        type="tel"
                                        x-model="formData.phone_number"
                                        class="flex-1 px-4 py-2 border border-l-0 border-white/20 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white/5"
                                        placeholder="xxx xxx xxx"
                                        required
                                    >
                                </div>
                                <span x-show="errors.phone" class="text-red-400 text-sm" x-text="errors.phone"></span>
                            </div>

                            <div class="mt-6 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Parola *</label>
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
                                    <label class="block text-sm font-medium text-white/80 mb-2">Confirma Parola *</label>
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
                                    <span x-show="!loading">Continua</span>
                                    <span x-show="loading">Se proceseaza...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ============================================================ -->
                    <!-- STEP 2: Tip cont -->
                    <!-- ============================================================ -->
                    <div x-show="currentStep === 2" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Tip Cont</h2>
                        <p class="text-white/60 mb-6">Ce tip de activitate desfasurati?</p>

                        <form @submit.prevent="submitStep2()">
                            <!-- Tenant type cards -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-3">Selecteaza tipul contului *</label>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <template x-for="tt in tenantTypes" :key="tt.value">
                                        <div
                                            @click="selectTenantType(tt.value)"
                                            class="tenant-type-card rounded-xl p-4 text-center"
                                            :class="formData.tenant_type === tt.value ? 'selected' : ''"
                                        >
                                            <span class="text-3xl block mb-2" x-text="getTenantTypeIcon(tt.value)"></span>
                                            <div class="font-medium text-white text-sm" x-text="tt.label"></div>
                                        </div>
                                    </template>
                                </div>
                                <span x-show="errors.tenant_type" class="text-red-400 text-sm mt-1 block" x-text="errors.tenant_type"></span>
                            </div>

                            <!-- Conditional entity name fields -->
                            <div x-show="formData.tenant_type" class="mb-6" x-cloak>

                                <!-- Artist: name + search -->
                                <div x-show="formData.tenant_type === 'artist'">
                                    <label class="block text-sm font-medium text-white/80 mb-2">Numele artistului / formatiei *</label>
                                    <input
                                        type="text"
                                        x-model="formData.entity_name"
                                        @input.debounce.500ms="searchArtists()"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        placeholder="ex: Carla's Dreams"
                                    >
                                    <!-- Artist search result -->
                                    <div x-show="artistSearchResult" x-cloak class="mt-2 flex items-center gap-2 text-emerald-400 text-sm">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <span x-text="artistSearchResult"></span>
                                    </div>
                                    <div x-show="artistSearching" class="mt-2 flex items-center gap-2 text-white/50 text-sm">
                                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Se cauta...</span>
                                    </div>
                                    <input type="hidden" x-model="formData.matched_artist_id">
                                </div>

                                <!-- Venue types: name + city -->
                                <div x-show="['venue','stadium-arena','philharmonic','opera','theater','museum'].includes(formData.tenant_type)">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-white/80 mb-2">Numele locatiei *</label>
                                            <input
                                                type="text"
                                                x-model="formData.entity_name"
                                                class="w-full px-4 py-3 dark-input rounded-lg"
                                                placeholder="ex: Sala Palatului"
                                            >
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-white/80 mb-2">Orasul *</label>
                                            <input
                                                type="text"
                                                x-model="formData.entity_city"
                                                class="w-full px-4 py-3 dark-input rounded-lg"
                                                placeholder="ex: Bucuresti"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Agency -->
                                <div x-show="formData.tenant_type === 'agency'">
                                    <label class="block text-sm font-medium text-white/80 mb-2">Numele agentiei *</label>
                                    <input
                                        type="text"
                                        x-model="formData.entity_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        placeholder="ex: Global Artist Agency"
                                    >
                                </div>

                                <!-- Speaker -->
                                <div x-show="formData.tenant_type === 'speaker'">
                                    <label class="block text-sm font-medium text-white/80 mb-2">Numele dvs. complet sau pseudonim *</label>
                                    <input
                                        type="text"
                                        x-model="formData.entity_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        placeholder="ex: Dr. Alexandru Ionescu"
                                    >
                                </div>

                                <!-- Festival / Competition -->
                                <div x-show="['festival','competition'].includes(formData.tenant_type)">
                                    <label class="block text-sm font-medium text-white/80 mb-2">Numele evenimentului / festivalului *</label>
                                    <input
                                        type="text"
                                        x-model="formData.entity_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        placeholder="ex: Untold Festival"
                                    >
                                </div>

                                <!-- Tenant-artist (generic) -->
                                <div x-show="formData.tenant_type === 'tenant-artist'">
                                    <label class="block text-sm font-medium text-white/80 mb-2">Numele organizatiei *</label>
                                    <input
                                        type="text"
                                        x-model="formData.entity_name"
                                        class="w-full px-4 py-3 dark-input rounded-lg"
                                        placeholder="ex: Entertainment SRL"
                                    >
                                </div>

                                <span x-show="errors.entity_name" class="text-red-400 text-sm mt-1 block" x-text="errors.entity_name"></span>
                            </div>

                            <!-- Public name - auto-filled from entity_name -->
                            <div x-show="formData.tenant_type" class="mb-6" x-cloak>
                                <label class="block text-sm font-medium text-white/80 mb-2">Nume Public *</label>
                                <input
                                    type="text"
                                    x-model="formData.public_name"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    placeholder="Numele afisat public"
                                >
                                <p class="text-xs text-white/50 mt-1">Numele sub care veti fi afisati pe platforma (poate fi diferit de denumirea legala)</p>
                                <span x-show="errors.public_name" class="text-red-400 text-sm" x-text="errors.public_name"></span>
                            </div>

                            <!-- Business type - only for artist/speaker -->
                            <div x-show="['artist','speaker'].includes(formData.tenant_type)" class="mb-6" x-cloak>
                                <label class="block text-sm font-medium text-white/80 mb-3">Forma juridica *</label>
                                <div class="grid grid-cols-3 gap-3">
                                    <div
                                        @click="formData.business_type = 'srl'"
                                        class="biz-type-card rounded-xl p-4 text-center"
                                        :class="formData.business_type === 'srl' ? 'selected' : ''"
                                    >
                                        <div class="text-2xl mb-1">🏢</div>
                                        <div class="font-medium text-white text-sm">SRL</div>
                                        <p class="text-xs text-white/50 mt-1">Societate cu Raspundere Limitata</p>
                                    </div>
                                    <div
                                        @click="formData.business_type = 'pfa'"
                                        class="biz-type-card rounded-xl p-4 text-center"
                                        :class="formData.business_type === 'pfa' ? 'selected' : ''"
                                    >
                                        <div class="text-2xl mb-1">📋</div>
                                        <div class="font-medium text-white text-sm">PFA</div>
                                        <p class="text-xs text-white/50 mt-1">Persoana Fizica Autorizata</p>
                                    </div>
                                    <div
                                        @click="formData.business_type = 'persoana_fizica'"
                                        class="biz-type-card rounded-xl p-4 text-center"
                                        :class="formData.business_type === 'persoana_fizica' ? 'selected' : ''"
                                    >
                                        <div class="text-2xl mb-1">👤</div>
                                        <div class="font-medium text-white text-sm">Persoana fizica</div>
                                        <p class="text-xs text-white/50 mt-1">Fara forma juridica</p>
                                    </div>
                                </div>
                                <span x-show="errors.business_type" class="text-red-400 text-sm mt-1 block" x-text="errors.business_type"></span>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 1"
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    Inapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 btn-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continua</span>
                                    <span x-show="loading">Se proceseaza...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ============================================================ -->
                    <!-- STEP 3: Date companie -->
                    <!-- ============================================================ -->
                    <div x-show="currentStep === 3" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Date Companie</h2>
                        <p class="text-white/60 mb-6">Detalii pentru facturare si contracte</p>

                        <form @submit.prevent="submitStep3()">
                            <!-- Contact position (optional, all types) -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Functie in companie</label>
                                <input
                                    type="text"
                                    x-model="formData.contact_position"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    placeholder="ex: Director General, Administrator"
                                >
                                <p class="text-xs text-white/50 mt-1">Optional - functia pe care o ocupi</p>
                                <span x-show="errors.contact_position" class="text-red-400 text-sm" x-text="errors.contact_position"></span>
                            </div>

                            <!-- Country (all types) -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Tara *</label>
                                <select
                                    x-model="formData.country"
                                    @change="loadStates()"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    required
                                >
                                    <option value="Romania">Romania</option>
                                    <option value="United States">United States</option>
                                    <option value="Germany">Germany</option>
                                    <option value="France">France</option>
                                    <option value="United Kingdom">United Kingdom</option>
                                    <option value="Hungary">Hungary</option>
                                    <option value="Bulgaria">Bulgaria</option>
                                    <option value="Moldova">Moldova</option>
                                    <option value="Italy">Italy</option>
                                    <option value="Spain">Spain</option>
                                </select>
                                <span x-show="errors.country" class="text-red-400 text-sm" x-text="errors.country"></span>
                            </div>

                            <!-- SRL/PFA: full company fields -->
                            <template x-if="formData.business_type === 'srl' || formData.business_type === 'pfa'">
                                <div>
                                    <div class="mb-6">
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                x-model="formData.vat_payer"
                                                class="rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                            >
                                            <span class="ml-2 text-sm text-white/80">Platitor de TVA</span>
                                        </label>
                                    </div>

                                    <div class="mb-6" x-show="formData.country === 'Romania'">
                                        <label class="block text-sm font-medium text-white/80 mb-2">CUI / CIF</label>
                                        <div class="flex gap-2">
                                            <input
                                                type="text"
                                                x-model="formData.cui"
                                                class="flex-1 px-4 py-3 dark-input rounded-lg"
                                                placeholder="ex: RO12345678"
                                            >
                                            <button
                                                type="button"
                                                @click="lookupCui()"
                                                :disabled="!formData.cui || cuiLoading"
                                                class="px-4 py-2 btn-success text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <span x-show="!cuiLoading">Verifica ANAF</span>
                                                <span x-show="cuiLoading">...</span>
                                            </button>
                                        </div>
                                        <p class="text-xs text-white/50 mt-1">Completeaza CUI-ul pentru a prelua automat datele firmei din ANAF</p>
                                    </div>

                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-white/80 mb-2">Nume Companie *</label>
                                        <input
                                            type="text"
                                            x-model="formData.company_name"
                                            class="w-full px-4 py-3 dark-input rounded-lg"
                                            required
                                        >
                                        <span x-show="errors.company_name" class="text-red-400 text-sm" x-text="errors.company_name"></span>
                                    </div>

                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-white/80 mb-2">Registrul Comertului</label>
                                        <input
                                            type="text"
                                            x-model="formData.reg_com"
                                            class="w-full px-4 py-3 dark-input rounded-lg"
                                            placeholder="ex: J40/12345/2020"
                                        >
                                    </div>
                                </div>
                            </template>

                            <!-- Address (all types) -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Adresa *</label>
                                <textarea
                                    x-model="formData.address"
                                    rows="2"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    required
                                ></textarea>
                                <span x-show="errors.address" class="text-red-400 text-sm" x-text="errors.address"></span>
                            </div>

                            <!-- State / City -->
                            <div class="grid grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Judet / Sector *</label>
                                    <template x-if="formData.country === 'Romania'">
                                        <select
                                            x-model="formData.state"
                                            @change="loadCities()"
                                            class="w-full px-4 py-3 dark-input rounded-lg"
                                            required
                                        >
                                            <option value="">Selecteaza...</option>
                                            <template x-for="state in availableStates" :key="state">
                                                <option :value="state" x-text="state"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="formData.country !== 'Romania'">
                                        <input
                                            type="text"
                                            x-model="formData.state"
                                            class="w-full px-4 py-3 dark-input rounded-lg"
                                            placeholder="Stat / Regiune"
                                            required
                                        >
                                    </template>
                                    <span x-show="errors.state" class="text-red-400 text-sm" x-text="errors.state"></span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white/80 mb-2">Oras *</label>
                                    <template x-if="formData.country === 'Romania'">
                                        <select
                                            x-model="formData.city"
                                            class="w-full px-4 py-3 dark-input rounded-lg"
                                            :disabled="!formData.state"
                                            required
                                        >
                                            <option value="">Selecteaza...</option>
                                            <template x-for="city in availableCities" :key="city">
                                                <option :value="city" x-text="city"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="formData.country !== 'Romania'">
                                        <input
                                            type="text"
                                            x-model="formData.city"
                                            class="w-full px-4 py-3 dark-input rounded-lg"
                                            placeholder="Oras"
                                            required
                                        >
                                    </template>
                                    <span x-show="errors.city" class="text-red-400 text-sm" x-text="errors.city"></span>
                                </div>
                            </div>

                            <!-- Payment processor -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-3">Procesor de Plata</label>
                                <p class="text-sm text-white/60 mb-4">Selecteaza sistemul de plati pe care doresti sa-l folosesti pentru procesarea platilor de la clientii tai</p>
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
                                    <!-- Nu stiu option -->
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
                                            <div class="font-semibold text-white">Nu stiu acum</div>
                                        </div>
                                        <p class="text-xs text-white/60">Voi decide si configura procesorul de plati mai tarziu din panoul de administrare.</p>
                                    </div>
                                </div>
                                <span x-show="errors.payment_processor" class="text-red-400 text-sm" x-text="errors.payment_processor"></span>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 2"
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    Inapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 btn-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continua</span>
                                    <span x-show="loading">Se proceseaza...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ============================================================ -->
                    <!-- STEP 4: Domeniu & Website -->
                    <!-- ============================================================ -->
                    <div x-show="currentStep === 4" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Domeniu & Website</h2>
                        <p class="text-white/60 mb-6">Adauga domeniile pe care vei vinde bilete</p>

                        <form @submit.prevent="submitStep4()">
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
                                        <div class="font-medium text-white">Nu am website propriu</div>
                                        <p class="text-sm text-white/60">Vreau sa primesc un subdomeniu gratuit pe tics.ro</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Subdomain input -->
                            <div x-show="formData.has_no_website" x-cloak class="mb-6 p-4 rounded-lg border border-purple-500/20 bg-purple-500/5">
                                <label class="block text-sm font-medium text-white/80 mb-2">Alege-ti subdomeniul gratuit pe tics.ro</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        x-model="formData.subdomain"
                                        @input.debounce.500ms="checkSubdomainAvailability()"
                                        class="flex-1 px-4 py-3 dark-input rounded-lg text-lg"
                                        :class="subdomainError ? 'border-red-500' : (subdomainAvailable ? 'border-green-500' : '')"
                                        placeholder="organizatia-ta"
                                    >
                                    <span class="text-lg font-medium text-white/80">.tics.ro</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2" x-show="subdomainChecking">
                                    <svg class="animate-spin h-4 w-4 text-white/50" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm text-white/60">Se verifica disponibilitatea...</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2 text-emerald-400" x-show="subdomainAvailable && !subdomainChecking && formData.subdomain">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm" x-text="formData.subdomain + '.tics.ro este disponibil!'"></span>
                                </div>
                                <span x-show="subdomainError" class="text-red-400 text-sm mt-2 block" x-text="subdomainError"></span>
                                <p class="text-xs text-white/50 mt-2">Website-ul tau va fi accesibil la adresa <strong x-text="(formData.subdomain || 'subdomeniu') + '.tics.ro'"></strong></p>
                            </div>

                            <!-- Domains input -->
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
                                                    class="flex-1 px-4 py-3 dark-input rounded-lg"
                                                    :class="domainErrors[index] ? 'border-red-500' : ''"
                                                    placeholder="https://example.com"
                                                    :required="!formData.has_no_website"
                                                >
                                                <button
                                                    type="button"
                                                    @click="formData.domains.splice(index, 1); delete domainErrors[index]"
                                                    x-show="formData.domains.length > 1"
                                                    class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition"
                                                >
                                                    Sterge
                                                </button>
                                            </div>
                                            <span x-show="domainErrors[index]" class="text-red-400 text-sm" x-text="domainErrors[index]"></span>
                                        </div>
                                    </template>
                                </div>
                                <button
                                    type="button"
                                    @click="formData.domains.push('')"
                                    class="mt-3 text-sm text-purple-400 hover:text-purple-300 transition"
                                >
                                    + Adauga alt domeniu
                                </button>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Estimare Bilete Lunare *</label>
                                <select
                                    x-model="formData.estimated_monthly_tickets"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    required
                                >
                                    <option value="">Selecteaza...</option>
                                    <option value="0">0 - 100 bilete/luna</option>
                                    <option value="100">100 - 500 bilete/luna</option>
                                    <option value="500">500 - 1.000 bilete/luna</option>
                                    <option value="1000">1.000 - 5.000 bilete/luna</option>
                                    <option value="5000">5.000 - 10.000 bilete/luna</option>
                                    <option value="10000">peste 10.000 bilete/luna</option>
                                </select>
                                <span x-show="errors.estimated_monthly_tickets" class="text-red-400 text-sm" x-text="errors.estimated_monthly_tickets"></span>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 3"
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    Inapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    class="px-6 py-3 btn-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                                >
                                    <span x-show="!loading">Continua</span>
                                    <span x-show="loading">Se proceseaza...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ============================================================ -->
                    <!-- STEP 5: Plan & Microservicii -->
                    <!-- ============================================================ -->
                    <div x-show="currentStep === 5" x-cloak class="step-content">
                        <h2 class="text-2xl font-bold text-white mb-2">Metoda de Lucru si Servicii</h2>
                        <p class="text-white/60 mb-6">Alege modul in care vei utiliza platforma</p>

                        <form @submit.prevent="submitStep5()">
                            <div class="mb-8">
                                <label class="block text-sm font-medium text-white/80 mb-4">Metoda de Lucru *</label>
                                <div class="grid grid-cols-3 gap-4">
                                    <div
                                        @click="formData.work_method = 'exclusive'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'exclusive' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-purple-500 mb-2 method-percentage">1%</div>
                                            <div class="font-semibold mb-1">Exclusiv</div>
                                            <div class="text-xs text-white/50">Vanzari exclusiv prin Tixello</div>
                                        </div>
                                    </div>
                                    <div
                                        @click="formData.work_method = 'mixed'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'mixed' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-green-500 mb-2 method-percentage">2%</div>
                                            <div class="font-semibold mb-1">Mixt</div>
                                            <div class="text-xs text-white/50">Tixello + alte platforme</div>
                                        </div>
                                    </div>
                                    <div
                                        @click="formData.work_method = 'reseller'"
                                        class="border-2 rounded-lg p-6 cursor-pointer transition"
                                        :class="formData.work_method === 'reseller' ? 'border-purple-500 bg-purple-500/10' : 'border-white/10 hover:border-white/20'"
                                    >
                                        <div class="text-center">
                                            <div class="text-4xl font-bold text-orange-500 mb-2 method-percentage">3%</div>
                                            <div class="font-semibold mb-1">Reseller</div>
                                            <div class="text-xs text-white/50">Revanzare bilete</div>
                                        </div>
                                    </div>
                                </div>
                                <span x-show="errors.work_method" class="text-red-400 text-sm mt-1 block" x-text="errors.work_method"></span>
                            </div>

                            <div class="mb-8">
                                <button
                                    type="button"
                                    @click="showMicroservices = !showMicroservices"
                                    class="w-full flex items-center justify-between p-4 border border-white/10 rounded-lg hover:bg-white/5 transition"
                                >
                                    <div>
                                        <span class="text-sm font-medium text-white/80">Microservicii Aditionale (optional)</span>
                                        <span x-show="formData.microservices.length > 0" class="ml-2 px-2 py-0.5 text-xs bg-purple-500/20 text-purple-300 rounded-full" x-text="formData.microservices.length + ' selectate'"></span>
                                    </div>
                                    <svg class="w-5 h-5 text-white/50 transition-transform" :class="showMicroservices ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="showMicroservices" x-collapse x-cloak class="mt-3">
                                    <p class="text-xs text-white/50 mb-3">Am preselectat serviciile recomandate pentru tipul tau de cont. Poti modifica selectia.</p>
                                    <div class="space-y-3">
                                        @foreach($microservices as $microservice)
                                        <label class="flex items-start p-4 border rounded-lg border-white/10 hover:bg-white/5 cursor-pointer transition">
                                            <input
                                                type="checkbox"
                                                value="{{ $microservice->id }}"
                                                :checked="formData.microservices.includes({{ $microservice->id }})"
                                                @change="toggleMicroservice({{ $microservice->id }})"
                                                class="mt-1 rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                            >
                                            <div class="ml-3 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div class="font-medium text-white">{{ $microservice->getTranslation('name', app()->getLocale()) }}</div>
                                                    <a href="/microservice/{{ $microservice->slug }}" target="_blank" class="text-xs text-purple-400 hover:text-purple-300 underline" @click.stop>
                                                        Detalii
                                                    </a>
                                                </div>
                                                <div class="text-sm text-white/60">{{ $microservice->getTranslation('short_description', app()->getLocale()) }}</div>
                                                <div class="text-sm font-semibold text-purple-400 mt-1">
                                                    {{ number_format($microservice->price, 2) }} RON / {{ $microservice->pricing_model }}
                                                </div>
                                            </div>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Cost summary -->
                                <div x-show="formData.microservices.length > 0" x-cloak class="mt-4 p-4 bg-purple-500/10 border border-purple-500/20 rounded-lg">
                                    <h4 class="text-sm font-semibold text-purple-300 mb-3">Sumar costuri microservicii selectate</h4>
                                    <div class="space-y-2">
                                        @foreach($microservices as $microservice)
                                        <div x-show="formData.microservices.includes({{ $microservice->id }})" class="flex items-center justify-between text-sm">
                                            <span class="text-white/70">{{ $microservice->getTranslation('name', app()->getLocale()) }}</span>
                                            <span class="text-white font-medium">{{ number_format($microservice->price, 2) }} RON / {{ $microservice->pricing_model }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-3 pt-3 border-t border-purple-500/20 flex items-center justify-between">
                                        <span class="text-sm font-semibold text-white">Total lunar estimat:</span>
                                        <span class="text-lg font-bold text-purple-300" x-text="calculateMicroserviceCost() + ' RON / luna'"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-white/80 mb-2">Limba Preferata *</label>
                                <select
                                    x-model="formData.locale"
                                    class="w-full px-4 py-3 dark-input rounded-lg"
                                    required
                                >
                                    <option value="ro">Romana</option>
                                    <option value="en">English</option>
                                    <option value="hu">Magyar</option>
                                    <option value="de">Deutsch</option>
                                    <option value="fr">Francais</option>
                                </select>
                            </div>

                            <!-- Terms & Conditions -->
                            <div class="mb-8 space-y-4 p-4 bg-white/5 rounded-lg">
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        type="checkbox"
                                        x-model="formData.agree_terms"
                                        class="mt-1 rounded border-white/20 text-purple-500 focus:ring-purple-500"
                                        required
                                    >
                                    <span class="ml-3 text-sm text-white/80">
                                        Am citit si sunt de acord cu
                                        <a href="/termeni-si-conditii" target="_blank" class="text-purple-400 hover:text-purple-300 underline">Termenii si Conditiile</a> *
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
                                        <a href="/politica-confidentialitate" target="_blank" class="text-purple-400 hover:text-purple-300 underline">Procesarea Datelor cu Caracter Personal (GDPR)</a> *
                                    </span>
                                </label>
                            </div>

                            <div class="mt-8 flex justify-between">
                                <button
                                    type="button"
                                    @click="currentStep = 4"
                                    class="px-6 py-3 btn-secondary text-white rounded-lg font-medium"
                                >
                                    Inapoi
                                </button>
                                <button
                                    type="submit"
                                    :disabled="loading || !formData.agree_terms || !formData.agree_gdpr"
                                    class="px-6 py-3 btn-success text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium text-lg"
                                >
                                    <span x-show="!loading">Finalizeaza Inregistrarea</span>
                                    <span x-show="loading">Se proceseaza...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ============================================================ -->
                    <!-- SUCCESS STATE -->
                    <!-- ============================================================ -->
                    <div x-show="completed" x-cloak class="step-content">
                        <div class="text-center py-8">
                            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-emerald-500/20 mb-6">
                                <svg class="h-10 w-10 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h2 class="text-3xl font-bold text-white mb-3">Inregistrare finalizata!</h2>
                            <p class="text-white/60 mb-8 max-w-md mx-auto" x-text="successMessage"></p>

                            <!-- Verification code -->
                            <div x-show="verificationCode" x-cloak class="mb-8">
                                <div class="success-card rounded-2xl p-8 max-w-md mx-auto">
                                    <p class="text-sm text-white/60 mb-3">Codul tau de verificare:</p>
                                    <div class="verification-code rounded-xl px-6 py-4 text-3xl font-bold text-purple-300 mb-4" x-text="verificationCode"></div>
                                    <p class="text-sm text-white/60">
                                        Trimite acest cod ca mesaj pe
                                        <span class="text-purple-400 font-medium">Instagram</span>,
                                        <span class="text-purple-400 font-medium">Facebook</span> sau
                                        <span class="text-purple-400 font-medium">TikTok</span>
                                        catre <span class="font-bold text-white">@tixello</span>
                                    </p>
                                </div>
                            </div>

                            <div x-show="successRedirect">
                                <a :href="successRedirect" class="inline-block px-8 py-3 btn-primary text-white rounded-lg font-medium text-lg">
                                    Mergi la panoul de administrare
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        function wizardData() {
            return {
                currentStep: {{ $step ?? 1 }},
                totalSteps: 5,
                loading: false,
                cuiLoading: false,
                errors: {},
                completed: false,
                successMessage: '',
                successRedirect: '',
                verificationCode: '',

                // Email check
                emailChecking: false,
                emailStatus: '',

                // Domain check
                domainErrors: {},

                // Subdomain check
                subdomainChecking: false,
                subdomainAvailable: false,
                subdomainError: '',

                // Artist search
                artistSearching: false,
                artistSearchResult: '',

                // Microservices section
                showMicroservices: false,

                // Modal
                showModal: false,
                modalTitle: '',
                modalMessage: '',
                modalType: 'info',
                modalCallback: null,

                // Password
                passwordStrength: 0,
                passwordStrengthClass: '',
                passwordStrengthText: '',

                // Location
                availableStates: [],
                availableCities: [],

                // Tenant types from server
                tenantTypes: @json($tenantTypes),

                // Microservice slug-to-id map (built at init)
                microserviceSlugToId: {},

                formData: {
                    // Step 1
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                    phone_country: '+40',
                    phone_number: '',
                    password: '',
                    password_confirmation: '',
                    // Step 2
                    tenant_type: '',
                    entity_name: '',
                    entity_city: '',
                    matched_artist_id: '',
                    public_name: '',
                    business_type: 'srl',
                    // Step 3
                    contact_position: '',
                    country: 'Romania',
                    vat_payer: true,
                    cui: '',
                    company_name: '',
                    reg_com: '',
                    address: '',
                    city: '',
                    state: '',
                    payment_processor: '',
                    // Step 4
                    has_no_website: false,
                    subdomain: '',
                    domains: [''],
                    estimated_monthly_tickets: '',
                    // Step 5
                    work_method: 'mixed',
                    microservices: [],
                    locale: 'ro',
                    agree_terms: false,
                    agree_gdpr: false
                },

                init() {
                    // Load existing data from session
                    @if(isset($data) && !empty($data))
                        this.formData = Object.assign(this.formData, @json($data));
                    @endif

                    // Build microservice slug-to-id map
                    @foreach($microservices as $ms)
                        this.microserviceSlugToId['{{ $ms->slug }}'] = {{ $ms->id }};
                    @endforeach

                    // Load states for Romania by default
                    if (this.formData.country === 'Romania') {
                        this.loadStates();
                    }

                    // Watch entity_name to auto-fill public_name
                    this.$watch('formData.entity_name', (val) => {
                        if (val && (!this.formData.public_name || this._lastAutoPublicName === this.formData.public_name)) {
                            this.formData.public_name = val;
                            this._lastAutoPublicName = val;
                        }
                    });

                    this._lastAutoPublicName = '';
                },

                getStepTitle(step) {
                    const titles = {
                        1: 'Date personale',
                        2: 'Tip cont',
                        3: 'Date companie',
                        4: 'Domeniu',
                        5: 'Plan'
                    };
                    return titles[step];
                },

                getTenantTypeIcon(value) {
                    const icons = {
                        'tenant-artist': '🎪',
                        'artist': '🎸',
                        'agency': '🏢',
                        'venue': '📍',
                        'speaker': '🎤',
                        'competition': '🏆',
                        'stadium-arena': '🏟️',
                        'philharmonic': '🎻',
                        'opera': '🎭',
                        'theater': '🎭',
                        'museum': '🏛️',
                        'festival': '🎪'
                    };
                    return icons[value] || '📋';
                },

                selectTenantType(value) {
                    this.formData.tenant_type = value;
                    // Reset entity fields
                    this.formData.entity_name = '';
                    this.formData.entity_city = '';
                    this.formData.matched_artist_id = '';
                    this.artistSearchResult = '';

                    // Set default business_type for non-artist/speaker types
                    if (!['artist', 'speaker'].includes(value)) {
                        this.formData.business_type = 'srl';
                    }

                    // Pre-select microservices based on tenant type
                    this.preselectMicroservices(value);
                },

                preselectMicroservices(tenantType) {
                    const defaults = {
                        'tenant-artist': ['analytics', 'crm', 'shop', 'affiliate-tracking'],
                        'artist': ['analytics', 'crm', 'shop', 'affiliate-tracking'],
                        'agency': ['analytics', 'crm', 'efactura', 'accounting'],
                        'venue': ['analytics', 'crm', 'door-sales', 'ticket-customizer'],
                        'stadium-arena': ['analytics', 'crm', 'door-sales', 'ticket-customizer'],
                        'speaker': ['analytics', 'crm'],
                        'competition': ['analytics', 'crm', 'shop'],
                        'philharmonic': ['analytics', 'crm', 'door-sales', 'ticket-customizer', 'efactura'],
                        'opera': ['analytics', 'crm', 'door-sales', 'ticket-customizer', 'efactura'],
                        'theater': ['analytics', 'crm', 'door-sales', 'ticket-customizer', 'efactura'],
                        'museum': ['analytics', 'crm', 'door-sales', 'ticket-customizer'],
                        'festival': ['analytics', 'crm', 'shop', 'door-sales', 'affiliate-tracking', 'efactura'],
                    };

                    const slugs = defaults[tenantType] || [];
                    this.formData.microservices = [];
                    slugs.forEach(slug => {
                        const id = this.microserviceSlugToId[slug];
                        if (id) {
                            this.formData.microservices.push(id);
                        }
                    });
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
                        this.passwordStrengthText = 'Parola slaba';
                    } else if (strength <= 4) {
                        this.passwordStrengthClass = 'strength-medium';
                        this.passwordStrengthText = 'Parola medie';
                    } else {
                        this.passwordStrengthClass = 'strength-strong';
                        this.passwordStrengthText = 'Parola puternica';
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
                                'Accept': 'application/json',
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

                async searchArtists() {
                    const q = this.formData.entity_name;
                    this.artistSearchResult = '';
                    this.formData.matched_artist_id = '';

                    if (!q || q.length < 2) return;

                    this.artistSearching = true;

                    try {
                        const response = await fetch('{{ route("onboarding.search-artists") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ q: q })
                        });

                        const data = await response.json();
                        if (data.results && data.results.length > 0) {
                            const artist = data.results[0];
                            this.artistSearchResult = 'Am gasit "' + artist.name + '" in biblioteca noastra!';
                            this.formData.matched_artist_id = artist.id;
                        }
                    } catch (error) {
                        console.error('Error searching artists:', error);
                    } finally {
                        this.artistSearching = false;
                    }
                },

                async submitStep1() {
                    if (this.emailStatus === 'taken') {
                        this.openModal('Email indisponibil', 'Aceasta adresa de email este deja inregistrata. Te rugam sa folosesti alta adresa.', 'error');
                        return;
                    }

                    this.loading = true;
                    this.errors = {};

                    // Combine phone
                    this.formData.phone = this.formData.phone_country + this.formData.phone_number.replace(/\s/g, '');

                    try {
                        const response = await fetch('{{ route("onboarding.step1") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step || 2;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A aparut o eroare. Te rugam sa incerci din nou.', 'error');
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
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step || 3;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A aparut o eroare. Te rugam sa incerci din nou.', 'error');
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
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step || 4;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A aparut o eroare. Te rugam sa incerci din nou.', 'error');
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
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentStep = data.next_step || 5;
                        } else {
                            this.errors = data.errors || {};
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A aparut o eroare. Te rugam sa incerci din nou.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitStep5() {
                    this.loading = true;
                    this.errors = {};

                    try {
                        const response = await fetch('{{ route("onboarding.step5") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.completed = true;
                            this.successMessage = data.message || 'Contul tau a fost creat cu succes!';
                            this.successRedirect = data.redirect || '';
                            this.verificationCode = data.verification_code || '';
                        } else {
                            this.errors = data.errors || {};
                            let errorMsg = data.message || 'A aparut o eroare.';
                            if (data.error) {
                                errorMsg += '\n\nDetalii: ' + data.error;
                                if (data.file) {
                                    errorMsg += '\nFisier: ' + data.file;
                                }
                            }
                            this.openModal('Eroare', errorMsg, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'A aparut o eroare. Te rugam sa incerci din nou.', 'error');
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
                                'Accept': 'application/json',
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

                            if (data.state) {
                                const anafState = data.state.toLowerCase().trim();
                                const matchedState = this.availableStates.find(s =>
                                    s.toLowerCase().trim() === anafState ||
                                    s.toLowerCase().trim().includes(anafState) ||
                                    anafState.includes(s.toLowerCase().trim())
                                );

                                if (matchedState) {
                                    this.formData.state = matchedState;
                                    await this.loadCities();

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
                                            this.formData.city = data.city;
                                        }
                                    }
                                } else {
                                    this.formData.state = data.state;
                                }
                            }

                            this.openModal('Succes', 'Datele au fost preluate cu succes din ANAF!', 'success');
                        } else {
                            this.openModal('ANAF', result.message || 'Nu s-au gasit date in ANAF pentru acest CUI.', 'info');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.openModal('Eroare', 'Eroare la interogarea ANAF.', 'error');
                    } finally {
                        this.cuiLoading = false;
                    }
                },

                async loadStates() {
                    if (this.formData.country === 'Romania') {
                        const raw = @json($romaniaCounties);
                        this.availableStates = Array.isArray(raw) ? raw : Object.values(raw);
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

                calculateMicroserviceCost() {
                    const prices = @json($microservices->mapWithKeys(fn ($m) => [$m->id => (float) $m->price]));
                    let total = 0;
                    this.formData.microservices.forEach(id => {
                        total += prices[id] || 0;
                    });
                    return total.toFixed(2);
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
                                'Accept': 'application/json',
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

                    this.subdomainAvailable = false;
                    this.subdomainError = '';

                    if (!subdomain || subdomain.length < 3) {
                        if (subdomain && subdomain.length < 3) {
                            this.subdomainError = 'Subdomeniul trebuie sa aiba cel putin 3 caractere';
                        }
                        return;
                    }

                    if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/.test(subdomain.toLowerCase())) {
                        this.subdomainError = 'Subdomeniul poate contine doar litere, cifre si cratima (nu la inceput sau sfarsit)';
                        return;
                    }

                    this.subdomainChecking = true;

                    try {
                        const response = await fetch('{{ route("onboarding.check-subdomain") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ subdomain: subdomain })
                        });

                        const data = await response.json();
                        this.subdomainAvailable = data.available;
                        this.subdomainError = data.available ? '' : data.message;
                    } catch (error) {
                        console.error('Error checking subdomain:', error);
                        this.subdomainError = 'Eroare la verificarea disponibilitatii';
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
