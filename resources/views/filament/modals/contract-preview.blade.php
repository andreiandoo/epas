<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Template</p>
            <p class="font-medium">{{ $template->name }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Work Method</p>
            <p class="font-medium">{{ ucfirst($template->work_method ?? 'Default') }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tenant</p>
            <p class="font-medium">{{ $tenant->company_name ?? $tenant->name }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Contract Number</p>
            <p class="font-medium">{{ $tenant->contract_number ?? 'Not generated' }}</p>
        </div>
    </div>

    <div class="border-t pt-4">
        <h3 class="text-lg font-medium mb-2">Contract Content Preview</h3>
        <div class="bg-white dark:bg-gray-900 border rounded-lg p-6 max-h-96 overflow-y-auto prose dark:prose-invert max-w-none">
            {{-- SECURITY FIX: Sanitize HTML content to prevent XSS --}}
            {!! \App\Helpers\HtmlSanitizer::sanitize($content) !!}
        </div>
    </div>

    @if($contractUrl)
    <div class="border-t pt-4">
        <h3 class="text-lg font-medium mb-2">Generated PDF</h3>
        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
            <iframe src="{{ $contractUrl }}" class="w-full h-96 border rounded"></iframe>
        </div>
        <div class="mt-2">
            <a href="{{ $contractUrl }}" target="_blank" class="text-primary-600 hover:text-primary-500 text-sm">
                Open PDF in new tab
            </a>
        </div>
    </div>
    @else
    <div class="border-t pt-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            No PDF generated yet. Use "Generate Contract" to create the PDF.
        </p>
    </div>
    @endif
</div>
