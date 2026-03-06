<div
    x-data="{
        html: '',
        open: true,
        timer: null,
        init() {
            this.$nextTick(() => {
                const ta = document.getElementById('body-html-editor');
                if (!ta) return;
                this.html = ta.value;
                ta.addEventListener('input', () => {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => { this.html = ta.value; }, 500);
                });
            });
        }
    }"
    class="mt-2"
>
    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-t-lg">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Live Preview</span>
        <button @click="open = !open" type="button" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
            <span x-show="open">Ascunde</span>
            <span x-show="!open">Arată preview</span>
        </button>
    </div>
    <div x-show="open" x-transition x-cloak>
        <iframe
            :srcdoc="html"
            class="w-full border border-t-0 border-gray-200 dark:border-white/10 rounded-b-lg bg-white"
            style="min-height: 450px;"
            sandbox="allow-same-origin"
        ></iframe>
    </div>
</div>
