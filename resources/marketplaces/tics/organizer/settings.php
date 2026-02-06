<?php
/**
 * Organizer Settings Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];

// Demo company data
$companyData = [
    'name' => $currentOrganizer['companyName'],
    'cui' => 'RO12345678',
    'reg_com' => 'J40/1234/2020',
    'address' => 'Str. Exemplu nr. 10',
    'city' => 'Bucuresti',
    'county' => 'Bucuresti',
    'zip' => '010101',
    'vat_payer' => true,
    'email' => $currentOrganizer['email'],
    'phone' => '+40 721 234 567',
    'website' => 'https://liveevents.ro'
];

// Demo contract data
$contractData = [
    'commission_rate' => 4,
    'commission_min' => 2.50,
    'fixed_price_ticket' => 0,
    'fixed_price_invitation' => 0,
    'commission_mode' => 'included', // 'included' or 'on_top'
    'work_mode' => 'non_exclusive', // 'exclusive' or 'non_exclusive'
    'has_contract' => true
];

// Demo bank accounts
$bankAccounts = [
    [
        'id' => 1,
        'bank' => 'ING Bank',
        'iban' => 'RO49INGB0000999901234521',
        'holder' => $currentOrganizer['companyName'],
        'is_primary' => true,
        'is_verified' => true
    ],
    [
        'id' => 2,
        'bank' => 'Banca Transilvania',
        'iban' => 'RO49BTRL0000999901234522',
        'holder' => $currentOrganizer['companyName'],
        'is_primary' => false,
        'is_verified' => true
    ]
];

// Current page for sidebar
$currentPage = 'settings';

// Page config for head
$pageTitle = 'Setari';
$pageDescription = 'Configureaza contul si organizatia';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

    <!-- Main -->
    <main class="lg:ml-64 pt-16 lg:pt-0">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <div class="px-8 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Setari</h1>
                <p class="text-sm text-gray-500">Configureaza contul si organizatia</p>
            </div>
            <!-- Tabs -->
            <div class="px-8 flex flex-wrap gap-4 border-t border-gray-100">
                <button onclick="switchTab('profile')" class="tab-btn active py-4 border-b-2 border-transparent text-sm font-medium" data-tab="profile">Profil</button>
                <button onclick="switchTab('company')" class="tab-btn py-4 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="company">Companie</button>
                <button onclick="switchTab('bank')" class="tab-btn py-4 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="bank">Conturi bancare</button>
                <button onclick="switchTab('contract')" class="tab-btn py-4 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="contract">Contract</button>
                <button onclick="switchTab('notifications')" class="tab-btn py-4 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="notifications">Notificari</button>
                <button onclick="switchTab('security')" class="tab-btn py-4 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="security">Securitate</button>
                <button onclick="switchTab('integrations')" class="tab-btn py-4 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="integrations">Integrari</button>
            </div>
        </header>

        <div class="p-8">
            <!-- TAB 1: PROFIL -->
            <div id="tab-profile" class="tab-content active max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Profil organizator</h2>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-20 h-20 bg-gray-200 rounded-xl flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <button class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">Schimba logo</button>
                                <p class="text-xs text-gray-500 mt-1">PNG, JPG pana la 2MB</p>
                            </div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Nume organizator *</label><input type="text" value="<?= htmlspecialchars($currentOrganizer['name']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Email contact</label><input type="email" value="<?= htmlspecialchars($currentOrganizer['email']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Telefon</label><input type="tel" value="<?= htmlspecialchars($companyData['phone']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Website</label><input type="url" value="<?= htmlspecialchars($companyData['website']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Descriere organizator</label><textarea rows="3" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none resize-none" placeholder="Scurta descriere...">Live Events SRL organizeaza evenimente culturale si de divertisment in Romania din 2020.</textarea></div>
                    </div>
                    <div class="flex justify-end mt-6"><button class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Salveaza modificarile</button></div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Personalizare pagini</h2>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Culoare principala</label><div class="flex items-center gap-3"><input type="color" value="#6366f1" class="w-12 h-12 rounded-lg cursor-pointer border-0"><span class="text-sm text-gray-500">#6366F1</span></div></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Subdomeniu personalizat</label><div class="flex"><span class="px-4 py-3 bg-gray-100 border border-r-0 border-gray-200 rounded-l-xl text-gray-500">https://</span><input type="text" value="liveevents" class="flex-1 px-4 py-3 border border-gray-200 outline-none"><span class="px-4 py-3 bg-gray-100 border border-l-0 border-gray-200 rounded-r-xl text-gray-500">.tics.ro</span></div></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Banner personalizat</label><div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 cursor-pointer"><p class="text-sm text-gray-500">Incarca banner (1200x300px)</p></div></div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-red-200 p-6">
                    <h2 class="text-lg font-bold text-red-600 mb-2">Zona periculoasa</h2>
                    <p class="text-sm text-gray-500 mb-4">Aceste actiuni sunt ireversibile</p>
                    <button class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50">Dezactiveaza contul de organizator</button>
                </div>
            </div>

            <!-- TAB 2: COMPANIE -->
            <div id="tab-company" class="tab-content max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Date companie</h2>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-sm text-blue-800">Datele companiei sunt utilizate pentru generarea contractului si a facturilor. Contacteaza suportul pentru modificari majore.</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Denumire firma *</label><input type="text" value="<?= htmlspecialchars($companyData['name']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">CUI / CIF *</label>
                                <div class="flex gap-2">
                                    <input type="text" value="<?= htmlspecialchars($companyData['cui']) ?>" class="input-field flex-1 px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                    <button type="button" class="px-4 py-3 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Verifica ANAF</button>
                                </div>
                            </div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Nr. Reg. Comertului</label><input type="text" value="<?= htmlspecialchars($companyData['reg_com']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Platitor TVA</label><select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"><option value="0" <?= !$companyData['vat_payer'] ? 'selected' : '' ?>>Nu</option><option value="1" <?= $companyData['vat_payer'] ? 'selected' : '' ?>>Da</option></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Cod postal</label><input type="text" value="<?= htmlspecialchars($companyData['zip']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Adresa sediu *</label><input type="text" value="<?= htmlspecialchars($companyData['address']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Oras *</label><input type="text" value="<?= htmlspecialchars($companyData['city']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Judet *</label><input type="text" value="<?= htmlspecialchars($companyData['county']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-6"><button class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Salveaza modificarile</button></div>
                </div>
            </div>

            <!-- TAB 3: CONTURI BANCARE -->
            <div id="tab-bank" class="tab-content max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-bold text-gray-900">Conturi bancare</h2>
                        <button onclick="openBankModal()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Adauga cont
                        </button>
                    </div>

                    <div class="space-y-4" id="bank-accounts-list">
                        <?php if (empty($bankAccounts)): ?>
                        <div class="p-6 text-center text-gray-500">Nu ai conturi bancare adaugate</div>
                        <?php else: ?>
                        <?php foreach ($bankAccounts as $account): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl <?= $account['is_primary'] ? 'ring-2 ring-indigo-500' : '' ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border border-gray-200">
                                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($account['bank']) ?></p>
                                        <?php if ($account['is_primary']): ?>
                                        <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded-full">Principal</span>
                                        <?php endif; ?>
                                        <?php if ($account['is_verified']): ?>
                                        <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Verificat</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-500 font-mono"><?= htmlspecialchars($account['iban']) ?></p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($account['holder']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!$account['is_primary']): ?>
                                <button class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50">Seteaza principal</button>
                                <?php endif; ?>
                                <button class="p-2 text-gray-400 hover:text-red-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Setari plati</h2>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Frecventa plati</label><select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"><option>Zilnic (balanta minima 100 RON)</option><option selected>Saptamanal (luni)</option><option>Bi-saptamanal</option><option>Lunar</option></select></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Suma minima pentru transfer</label><input type="number" value="500" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"><p class="text-xs text-gray-500 mt-1">Transferurile se fac doar cand balanta depaseste aceasta suma</p></div>
                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600"><span class="text-sm text-gray-700">Primeste notificare la fiecare transfer</span></label>
                    </div>
                </div>
            </div>

            <!-- TAB 4: CONTRACT -->
            <div id="tab-contract" class="tab-content max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-bold text-gray-900">Contract marketplace</h2>
                        <?php if ($contractData['has_contract']): ?>
                        <button class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarca contract
                        </button>
                        <?php else: ?>
                        <span class="text-sm text-gray-500">Contract negenearat</span>
                        <?php endif; ?>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-sm text-blue-800">Contractul este generat automat pe baza datelor tale de companie si a conditiilor comerciale agreate cu TICS.ro.</p>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                        <div class="bg-gray-50 rounded-xl p-5">
                            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Comision
                            </h3>
                            <p class="text-3xl font-bold text-indigo-600 mb-1"><?= $contractData['commission_rate'] ?>%</p>
                            <p class="text-sm text-gray-500">minim <?= number_format($contractData['commission_min'], 2, ',', '.') ?> lei/bilet</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-5">
                            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Mod operare comision
                            </h3>
                            <p class="text-lg font-semibold text-gray-900">
                                <?= $contractData['commission_mode'] === 'included' ? 'Inclus in pret' : 'Adaugat la pret' ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                <?= $contractData['commission_mode'] === 'included' ? 'Comisionul este inclus in pretul afisat al biletului' : 'Comisionul se adauga separat la pretul biletului' ?>
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-5">
                            <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Mod de lucru
                            </h3>
                            <p class="text-lg font-semibold text-gray-900">
                                <?= $contractData['work_mode'] === 'exclusive' ? 'Exclusiv' : 'Non-exclusiv' ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                <?= $contractData['work_mode'] === 'exclusive' ? 'Vinzi bilete doar pe aceasta platforma' : 'Poti vinde bilete si pe alte platforme' ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($contractData['fixed_price_ticket'] > 0 || $contractData['fixed_price_invitation'] > 0): ?>
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                            <h3 class="font-semibold text-gray-900 mb-2">Pret fix per bilet</h3>
                            <p class="text-2xl font-bold text-amber-600"><?= number_format($contractData['fixed_price_ticket'], 2, ',', '.') ?> RON</p>
                            <p class="text-sm text-gray-500 mt-1">Se adauga la comisionul procentual</p>
                        </div>
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                            <h3 class="font-semibold text-gray-900 mb-2">Pret fix per invitatie</h3>
                            <p class="text-2xl font-bold text-amber-600"><?= number_format($contractData['fixed_price_invitation'], 2, ',', '.') ?> RON</p>
                            <p class="text-sm text-gray-500 mt-1">Pentru bilete gratuite / invitatii</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Conditii contractuale</h3>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Platile se efectueaza in termen de 7 zile lucratoare dupa eveniment</span>
                            </div>
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Rambursarile se proceseaza in maxim 14 zile de la solicitare</span>
                            </div>
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Organizatorul este responsabil de livrarea evenimentului conform descrierii</span>
                            </div>
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Contractul este valabil pe durata nedeterminata si poate fi reziliat cu preaviz de 30 zile</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: NOTIFICARI -->
            <div id="tab-notifications" class="tab-content max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Notificari Email</h2>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Vanzare noua</p><p class="text-sm text-gray-500">Email la fiecare bilet vandut</p></div>
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Raport zilnic</p><p class="text-sm text-gray-500">Sumar zilnic cu vanzarile</p></div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Raport saptamanal</p><p class="text-sm text-gray-500">Sumar saptamanal cu statistici</p></div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Sold out</p><p class="text-sm text-gray-500">Cand o categorie de bilete e epuizata</p></div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Plata efectuata</p><p class="text-sm text-gray-500">Cand banii sunt transferati in cont</p></div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Cerere rambursare</p><p class="text-sm text-gray-500">Cand un client cere rambursare</p></div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Notificari Push</h2>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Vanzari in timp real</p><p class="text-sm text-gray-500">Notificare push pentru fiecare vanzare</p></div>
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div><p class="font-medium text-gray-900">Check-in participanti</p><p class="text-sm text-gray-500">Notificare la check-in</p></div>
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                    </div>
                </div>
            </div>

            <!-- TAB 6: SECURITATE -->
            <div id="tab-security" class="tab-content max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Schimba parola</h2>
                    <form onsubmit="changePassword(event)" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Parola curenta *</label>
                            <input type="password" id="current-password" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Parola noua *</label>
                            <input type="password" id="new-password" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" required minlength="8">
                            <p class="text-xs text-gray-500 mt-1">Minim 8 caractere</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirma parola noua *</label>
                            <input type="password" id="confirm-password" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" required>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Schimba parola</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Autentificare in doi pasi (2FA)</h2>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p class="font-medium text-gray-900">Autentificare cu aplicatie</p>
                            <p class="text-sm text-gray-500">Foloseste Google Authenticator sau similar</p>
                        </div>
                        <button class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">Activeaza</button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Sesiuni active</h2>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Chrome - Windows</p>
                                    <p class="text-sm text-gray-500">Bucuresti, Romania - Acum (sesiunea curenta)</p>
                                </div>
                            </div>
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Activ</span>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Safari - iPhone</p>
                                    <p class="text-sm text-gray-500">Bucuresti, Romania - Acum 2 zile</p>
                                </div>
                            </div>
                            <button class="text-red-600 text-sm font-medium hover:underline">Deconecteaza</button>
                        </div>
                    </div>
                    <button class="mt-4 text-red-600 text-sm font-medium hover:underline">Deconecteaza toate celelalte sesiuni</button>
                </div>
            </div>

            <!-- TAB 7: INTEGRARI -->
            <div id="tab-integrations" class="tab-content max-w-3xl">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Integrari disponibile</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-orange-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg></div>
                                <div><p class="font-medium text-gray-900">Google Analytics</p><p class="text-sm text-gray-500">Urmareste traficul si conversiile</p></div>
                            </div>
                            <button class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">Conecteaza</button>
                        </div>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></div>
                                <div><p class="font-medium text-gray-900">Facebook Pixel</p><p class="text-sm text-gray-500">Remarketing si tracking conversii</p></div>
                            </div>
                            <span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-medium rounded-full">Conectat</span>
                        </div>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
                                <div><p class="font-medium text-gray-900">Mailchimp</p><p class="text-sm text-gray-500">Sincronizare lista de emailuri</p></div>
                            </div>
                            <button class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">Conecteaza</button>
                        </div>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center"><span class="text-2xl">⚡</span></div>
                                <div><p class="font-medium text-gray-900">Zapier</p><p class="text-sm text-gray-500">Automatizeaza cu 5000+ aplicatii</p></div>
                            </div>
                            <button class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">Conecteaza</button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">API & Webhooks</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cheie API</label>
                            <div class="flex gap-2">
                                <input type="password" id="api-key" value="sk_live_xxxxxxxxxxxxxxxxxx" class="flex-1 px-4 py-3 border border-gray-200 rounded-xl font-mono text-sm" readonly>
                                <button onclick="toggleApiKey()" class="px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200">Arata</button>
                                <button onclick="copyApiKey()" class="px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200">Copiaza</button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Nu partaja aceasta cheie. <a href="#" class="text-indigo-600 hover:underline">Documentatie API</a></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                            <input type="url" placeholder="https://yoursite.com/webhook" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                            <p class="text-xs text-gray-500 mt-1">Vom trimite evenimente (vanzari, check-in) la acest URL</p>
                        </div>
                        <button class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">Salveaza webhook</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Bank Account Modal -->
    <div id="bankModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Adauga cont bancar</h3>
                <button onclick="closeBankModal()" class="p-2 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="addBankAccount(event)" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nume banca *</label>
                    <select id="bank-name" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" required>
                        <option value="">Selecteaza banca</option>
                        <option value="ING Bank">ING Bank</option>
                        <option value="BCR">BCR</option>
                        <option value="BRD">BRD</option>
                        <option value="Banca Transilvania">Banca Transilvania</option>
                        <option value="Raiffeisen Bank">Raiffeisen Bank</option>
                        <option value="UniCredit Bank">UniCredit Bank</option>
                        <option value="CEC Bank">CEC Bank</option>
                        <option value="Alpha Bank">Alpha Bank</option>
                        <option value="OTP Bank">OTP Bank</option>
                        <option value="First Bank">First Bank</option>
                        <option value="Alta banca">Alta banca</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">IBAN *</label>
                    <input type="text" id="bank-iban" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none font-mono uppercase" required maxlength="24" placeholder="RO49AAAA1B31007593840000" oninput="validateIBAN(this)">
                    <p id="iban-validation" class="text-xs mt-1 hidden"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Titular cont *</label>
                    <input type="text" id="bank-holder" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" required value="<?= htmlspecialchars($companyData['name']) ?>">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeBankModal()" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                    <button type="submit" class="flex-1 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Adauga cont</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.classList.add('text-gray-500'); });
            document.getElementById('tab-' + tabName).classList.add('active');
            const btn = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
            btn.classList.add('active');
            btn.classList.remove('text-gray-500');
            // Update URL hash
            window.location.hash = tabName;
        }

        // Load tab from hash on page load
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.replace('#', '');
            const validTabs = ['profile', 'company', 'bank', 'contract', 'notifications', 'security', 'integrations'];
            if (hash && validTabs.includes(hash)) {
                switchTab(hash);
            }
        });

        // Bank modal
        function openBankModal() {
            document.getElementById('bankModal').classList.remove('hidden');
            document.getElementById('bank-iban').value = '';
            document.getElementById('iban-validation').classList.add('hidden');
        }

        function closeBankModal() {
            document.getElementById('bankModal').classList.add('hidden');
        }

        function addBankAccount(e) {
            e.preventDefault();
            const bank = document.getElementById('bank-name').value;
            const iban = document.getElementById('bank-iban').value;
            const holder = document.getElementById('bank-holder').value;

            // In production, send to API
            alert('Contul ' + iban + ' a fost adaugat! (Demo)');
            closeBankModal();
        }

        // IBAN validation
        function validateIBAN(input) {
            const value = input.value.toUpperCase().replace(/\s/g, '');
            input.value = value;
            const validation = document.getElementById('iban-validation');

            if (!value) {
                validation.classList.add('hidden');
                return;
            }

            validation.classList.remove('hidden');

            if (!value.startsWith('RO')) {
                validation.textContent = 'IBAN-ul romanesc trebuie sa inceapa cu RO';
                validation.className = 'text-xs mt-1 text-red-600';
                return;
            }

            if (value.length < 24) {
                const remaining = 24 - value.length;
                validation.textContent = 'Mai sunt necesare ' + remaining + ' caractere';
                validation.className = 'text-xs mt-1 text-amber-600';
                return;
            }

            if (value.length === 24) {
                validation.textContent = '✓ Format IBAN valid';
                validation.className = 'text-xs mt-1 text-green-600';
            }
        }

        // Password change
        function changePassword(e) {
            e.preventDefault();
            const current = document.getElementById('current-password').value;
            const newPass = document.getElementById('new-password').value;
            const confirm = document.getElementById('confirm-password').value;

            if (newPass !== confirm) {
                alert('Parolele nu coincid!');
                return;
            }

            // In production, send to API
            alert('Parola a fost schimbata cu succes! (Demo)');
            e.target.reset();
        }

        // API key toggle
        function toggleApiKey() {
            const input = document.getElementById('api-key');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function copyApiKey() {
            const input = document.getElementById('api-key');
            navigator.clipboard.writeText(input.value);
            alert('Cheia API a fost copiata!');
        }

        // Close modal on backdrop click
        document.getElementById('bankModal').addEventListener('click', function(e) {
            if (e.target === this) closeBankModal();
        });
    </script>
</body>
</html>
