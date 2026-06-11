<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract - {{ $tenant->contract_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-800 text-white p-6">
                <h1 class="text-2xl font-bold">Contract</h1>
                <p class="text-gray-300">{{ $tenant->contract_number }}</p>
            </div>

            <!-- Contract Info -->
            <div class="p-6 border-b">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Company</p>
                        <p class="font-medium">{{ $tenant->company_name ?? $tenant->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($tenant->contract_status === 'signed') bg-green-100 text-green-800
                            @elseif($tenant->contract_status === 'viewed') bg-blue-100 text-blue-800
                            @elseif($tenant->contract_status === 'sent') bg-yellow-100 text-yellow-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst($tenant->contract_status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Generated</p>
                        <p class="font-medium">{{ $tenant->contract_generated_at?->format('M d, Y') }}</p>
                    </div>
                    @if($tenant->contract_signed_at)
                    <div>
                        <p class="text-sm text-gray-500">Signed</p>
                        <p class="font-medium">{{ $tenant->contract_signed_at->format('M d, Y H:i') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- PDF Viewer -->
            <div class="p-6">
                <iframe src="{{ route('contract.pdf', $token) }}" class="w-full h-[600px] border rounded"></iframe>
            </div>

            <!-- Actions -->
            <div class="p-6 bg-gray-50 flex justify-between items-center">
                <a href="{{ route('contract.history', $token) }}" class="text-sm text-gray-600 hover:text-gray-900">
                    View History
                </a>

                <div class="flex gap-3">
                    <a href="{{ route('contract.pdf', $token) }}" download class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Download PDF
                    </a>

                    @if(!$tenant->contract_signed_at)
                    <a href="{{ route('contract.sign', $token) }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                        Sign Contract
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
