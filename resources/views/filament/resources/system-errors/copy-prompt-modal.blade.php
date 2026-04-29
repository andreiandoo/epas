<div
    x-data="{
        copied: false,
        copy() {
            const ta = $refs.promptText;
            navigator.clipboard.writeText(ta.value).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            }).catch(() => {
                ta.select();
                document.execCommand('copy');
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            });
        },
    }"
    class="space-y-3"
>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Apasă <strong>Copy</strong>, apoi paste-ază în chat-ul Claude. Promptul include
        toate detaliile erorii (mesaj, stack trace, request context) plus instrucțiunea
        de debugging.
    </p>

    <textarea
        x-ref="promptText"
        readonly
        class="w-full min-h-[280px] font-mono text-xs leading-tight p-3 rounded-md bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100"
    >{{ $prompt }}</textarea>

    <div class="flex items-center gap-2">
        <button
            type="button"
            @click="copy()"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span x-text="copied ? 'Copiat!' : 'Copy'"></span>
        </button>
        <span x-show="copied" class="text-sm text-success-600 dark:text-success-400" x-cloak>
            Promptul e în clipboard.
        </span>
    </div>
</div>
