<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract History - {{ $tenant->contract_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gray-800 text-white p-6">
                <h1 class="text-2xl font-bold">Contract History</h1>
                <p class="text-gray-300">{{ $tenant->company_name ?? $tenant->name }}</p>
            </div>

            <div class="divide-y">
                @forelse($versions as $version)
                <div class="p-6 hover:bg-gray-50">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-medium">
                                Version {{ $version->version_number }}
                                @if($loop->first)
                                <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Current</span>
                                @endif
                            </h3>
                            <p class="text-sm text-gray-500">{{ $version->contract_number }}</p>
                            <p class="text-sm text-gray-500 mt-1">
                                Generated: {{ $version->generated_at->format('M d, Y H:i') }}
                            </p>
                            @if($version->notes)
                            <p class="text-sm text-gray-600 mt-2">{{ $version->notes }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($version->status === 'signed') bg-green-100 text-green-800
                                @elseif($version->status === 'viewed') bg-blue-100 text-blue-800
                                @elseif($version->status === 'sent') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($version->status) }}
                            </span>
                            <a href="{{ route('contract.version.download', [$token, $version->id]) }}"
                               class="text-sm text-blue-600 hover:text-blue-800">
                                Download
                            </a>
                        </div>
                    </div>

                    @if($version->signed_at)
                    <div class="mt-3 pt-3 border-t text-sm text-gray-500">
                        <p>Signed on {{ $version->signed_at->format('M d, Y H:i') }}</p>
                        @if($version->signature_ip)
                        <p>IP: {{ $version->signature_ip }}</p>
                        @endif
                    </div>
                    @endif
                </div>
                @empty
                <div class="p-6 text-center text-gray-500">
                    No contract versions found.
                </div>
                @endforelse
            </div>

            <div class="p-6 bg-gray-50">
                <a href="{{ route('contract.view', $token) }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to Contract
                </a>
            </div>
        </div>
    </div>
</body>
</html>
