<div class="p-4 border border-gray-300 rounded-lg bg-gray-50">
    <h4 class="mb-3 text-sm font-semibold text-gray-700">Click to Copy Variables</h4>
    <div class="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
        @foreach([
            ['var' => 'first_name', 'desc' => 'First name'],
            ['var' => 'last_name', 'desc' => 'Last name'],
            ['var' => 'full_name', 'desc' => 'Full name (first + last)'],
            ['var' => 'email', 'desc' => 'Email address'],
            ['var' => 'company_name', 'desc' => 'Legal company name'],
            ['var' => 'public_name', 'desc' => 'Public display name'],
            ['var' => 'plan', 'desc' => 'Subscription plan'],
            ['var' => 'website_url', 'desc' => 'Primary domain URL'],
            ['var' => 'verification_link', 'desc' => 'Email verification link'],
            ['var' => 'reset_password_link', 'desc' => 'Password reset link'],
            ['var' => 'phone', 'desc' => 'Phone number'],
            ['var' => 'address', 'desc' => 'Company address'],
        ] as $variable)
            <button
                type="button"
                onclick="copyVariable('{{ $variable['var'] }}')"
                class="flex items-start p-2 text-left transition bg-white border border-gray-200 rounded-md group hover:border-blue-400 hover:bg-blue-50"
                title="Click to copy {{ $variable['var'] }}"
            >
                <svg class="flex-shrink-0 w-4 h-4 mr-2 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <div class="flex-1">
                    <div class="font-mono text-xs font-semibold text-gray-700 group-hover:text-blue-700">
                        {{ $variable['var'] }}
                    </div>
                    <div class="mt-0.5 text-xs text-gray-500">{{ $variable['desc'] }}</div>
                </div>
            </button>
        @endforeach
    </div>

    <div id="copy-notification" class="hidden p-2 mt-3 text-sm text-center text-green-700 rounded-md bg-green-50">
        <span id="copy-text"></span> copied to clipboard!
    </div>
</div>

<script>
    function copyVariable(variableName) {
        // Copy only the variable name (user will add {{}} manually)
        const textToCopy = variableName;

        // Copy to clipboard
        navigator.clipboard.writeText(textToCopy).then(() => {
            // Show notification
            const notification = document.getElementById('copy-notification');
            const copyText = document.getElementById('copy-text');
            copyText.textContent = textToCopy;
            notification.classList.remove('hidden');

            // Hide after 2 seconds
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
            alert('Failed to copy to clipboard');
        });
    }
</script>
