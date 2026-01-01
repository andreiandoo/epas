<div class="p-4">
    <div class="mb-4 p-3 bg-gray-100 rounded-lg">
        <p class="text-sm text-gray-600"><strong>Subject:</strong> {{ $template->subject }}</p>
        <p class="text-sm text-gray-600"><strong>Template:</strong> {{ \App\Models\MarketplaceEmailTemplate::TEMPLATE_SLUGS[$template->slug] ?? $template->slug }}</p>
    </div>

    <div class="border rounded-lg overflow-hidden">
        <div class="bg-gray-50 px-4 py-2 border-b">
            <span class="text-sm font-medium">HTML Preview</span>
        </div>
        <div class="p-4 bg-white">
            <iframe
                srcdoc="{{ $template->body_html }}"
                class="w-full min-h-[400px] border-0"
                sandbox="allow-same-origin"
            ></iframe>
        </div>
    </div>

    @if($template->body_text)
    <div class="mt-4 border rounded-lg overflow-hidden">
        <div class="bg-gray-50 px-4 py-2 border-b">
            <span class="text-sm font-medium">Plain Text Preview</span>
        </div>
        <div class="p-4 bg-white">
            <pre class="text-sm whitespace-pre-wrap font-mono">{{ $template->body_text }}</pre>
        </div>
    </div>
    @endif
</div>
