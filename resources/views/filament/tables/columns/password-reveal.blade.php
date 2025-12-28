@php
    $password = $getRecord()->password;
    $id = 'pwd-' . $getRecord()->id;
@endphp

<div class="flex items-center gap-2" x-data="{ revealed: false, copied: false }">
    @if($password)
        <span
            x-show="!revealed"
            class="font-mono text-sm text-gray-500 dark:text-gray-400"
        >••••••••</span>

        <span
            x-show="revealed"
            x-cloak
            class="font-mono text-sm text-gray-900 dark:text-white select-all"
            id="{{ $id }}"
        >{{ $password }}</span>

        <button
            type="button"
            @click="revealed = !revealed"
            class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
            :title="revealed ? 'Hide password' : 'Show password'"
        >
            <template x-if="!revealed">
                <x-heroicon-m-eye class="w-4 h-4" />
            </template>
            <template x-if="revealed">
                <x-heroicon-m-eye-slash class="w-4 h-4" />
            </template>
        </button>

        <button
            type="button"
            @click="
                navigator.clipboard.writeText('{{ addslashes($password) }}');
                copied = true;
                setTimeout(() => copied = false, 2000);
            "
            class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
            :class="{ 'text-green-500 hover:text-green-500': copied }"
            :title="copied ? 'Copied!' : 'Copy password'"
        >
            <template x-if="!copied">
                <x-heroicon-m-clipboard class="w-4 h-4" />
            </template>
            <template x-if="copied">
                <x-heroicon-m-clipboard-document-check class="w-4 h-4" />
            </template>
        </button>
    @else
        <span class="text-gray-400 text-sm">—</span>
    @endif
</div>
