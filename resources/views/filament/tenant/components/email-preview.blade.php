<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Subject:</div>
        <div class="font-medium text-gray-900 dark:text-white">{{ $subject }}</div>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Body:</div>
        <div class="prose dark:prose-invert max-w-none">
            {!! $body !!}
        </div>
    </div>

    <div class="text-xs text-gray-400 dark:text-gray-500">
        <strong>Note:</strong> Variables like <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">{{variable_name}}</code> will be replaced with actual values when the email is sent.
    </div>
</div>
