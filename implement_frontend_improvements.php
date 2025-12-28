<?php

// 1. Update default.ts to make header dynamic for logged-in users
$defaultTs = file_get_contents('resources/tenant-client/src/templates/default.ts');

// Change "Contul meu" link to have an ID so we can update it dynamically
$defaultTs = str_replace(
    '<a href="/login" class="btn-primary px-4 py-2 rounded-lg text-sm">Contul meu</a>',
    '<a href="/login" id="account-link" class="btn-primary px-4 py-2 rounded-lg text-sm">Contul meu</a>',
    $defaultTs
);

file_put_contents('resources/tenant-client/src/templates/default.ts', $defaultTs);
echo "✓ Updated default.ts header with account-link ID\n";

// 2. Update Router.ts to add methods for updating header and fixing greetings
$routerTs = file_get_contents('resources/tenant-client/src/core/Router.ts');

// Add updateHeaderForUser method after updateCartBadge method
$updateHeaderMethod = '
    // Update header to show user name when logged in
    private updateHeaderForUser(): void {
        const accountLink = document.getElementById(\'account-link\');
        if (accountLink && this.currentUser) {
            const firstName = this.currentUser.name?.split(\' \')[0] || this.currentUser.email;
            accountLink.textContent = `Buna, ${firstName}`;
            accountLink.href = \'/account\';
        }
    }
';

// Insert after updateCartBadge method (after line with "badge.classList.add('hidden');")
$routerTs = preg_replace(
    '/(private updateCartBadge\(\): void \{[^}]+\})/s',
    '$1' . $updateHeaderMethod,
    $routerTs
);

// Call updateHeaderForUser and updateCartBadge after successful login
$routerTs = str_replace(
    'this.currentUser = data.data.user;',
    'this.currentUser = data.data.user;
                    this.updateHeaderForUser();
                    this.updateCartBadge();',
    $routerTs
);

// Call updateHeaderForUser and updateCartBadge in init() method
$routerTs = str_replace(
    'this.navigate(window.location.pathname);',
    'this.navigate(window.location.pathname);
        this.updateHeaderForUser();
        this.updateCartBadge();',
    $routerTs
);

// Fix greeting in renderAccount to use first_name only
$routerTs = str_replace(
    '<p class="text-gray-600">Bun venit, ${userName}!</p>',
    '<p class="text-gray-600">Bun venit, ${userName?.split(\' \')[0] || userName}!</p>',
    $routerTs
);

file_put_contents('resources/tenant-client/src/core/Router.ts', $routerTs);
echo "✓ Updated Router.ts with updateHeaderForUser() and cart badge on init\n";

// 3. Add city, country, date_of_birth to renderProfile
$routerTs = file_get_contents('resources/tenant-client/src/core/Router.ts');

// Find renderProfile method and add new fields
$routerTs = preg_replace(
    '/(id="profile-phone"[^>]+value="\$\{profileData\.phone \|\| \'\'\}"[^>]+>)/s',
    '$1
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Oraș</label>
                            <input type="text" id="profile-city" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" value="${profileData.city || \'\'}" placeholder="Cluj-Napoca">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Țară</label>
                            <input type="text" id="profile-country" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" value="${profileData.country || \'\'}" placeholder="România">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Data nașterii</label>
                            <input type="date" id="profile-dob" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" value="${profileData.date_of_birth || \'\'}">',
    $routerTs
);

// Update profile form submission to include new fields
$routerTs = preg_replace(
    '/(phone: profilePhone,)/s',
    '$1
                    city: profileCity,
                    country: profileCountry,
                    date_of_birth: profileDob,',
    $routerTs
);

// Add new field variables in profile submit handler
$routerTs = preg_replace(
    '/(const profilePhone = \(document\.getElementById\(\'profile-phone\'\) as HTMLInputElement\)\.value;)/s',
    '$1
            const profileCity = (document.getElementById(\'profile-city\') as HTMLInputElement).value;
            const profileCountry = (document.getElementById(\'profile-country\') as HTMLInputElement).value;
            const profileDob = (document.getElementById(\'profile-dob\') as HTMLInputElement).value;',
    $routerTs
);

file_put_contents('resources/tenant-client/src/core/Router.ts', $routerTs);
echo "✓ Added city, country, date_of_birth fields to profile page\n";

echo "\n✅ All frontend improvements implemented!\n";
echo "Run: cd resources/tenant-client && npm run build\n";
