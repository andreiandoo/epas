<div class="flex justify-center py-4">
    @php
        $ticket = $getRecord();
    @endphp

    <div class="text-center">
        <div class="inline-block p-4 bg-white rounded-lg shadow-md">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->code) }}&color=181622&margin=0"
                alt="QR Code"
                class="w-48 h-48"
            />
        </div>
        <p class="mt-3 text-lg font-mono font-bold text-gray-900">{{ $ticket->code }}</p>
        <p class="mt-1 text-sm text-gray-600">
            Scan this code at the event entrance
        </p>
    </div>
</div>
