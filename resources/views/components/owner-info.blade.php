<div class="rounded-lg border border-gray-300 bg-gray-50 p-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-gray-700">Name:</span>
            <span class="ml-2 text-sm text-gray-900">{{ $name }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-700">Email:</span>
            <span class="ml-2 text-sm text-gray-900">{{ $email }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-700">Role:</span>
            <span class="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                {{ $role }}
            </span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-700">Created:</span>
            <span class="ml-2 text-sm text-gray-900">{{ $created }}</span>
        </div>
    </div>
</div>
