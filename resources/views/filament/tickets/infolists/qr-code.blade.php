<div class="flex justify-center py-4">
    @php
        $ticket = $getRecord();
    @endphp

    <div class="text-center">
        <div class="inline-block p-4 bg-white rounded-lg shadow-md">
            {{-- TODO: Generate actual QR code once simplesoftwareio/simple-qrcode package is installed --}}
            <div class="w-48 h-48 bg-gray-200 flex items-center justify-center rounded">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-600">QR Code</p>
                    <p class="text-xs text-gray-500">{{ $ticket->code }}</p>
                </div>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-600">
            Scan this code at the event entrance
        </p>
    </div>
</div>
