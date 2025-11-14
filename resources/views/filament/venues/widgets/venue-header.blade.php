<x-filament::section>
    <div class="flex flex-col gap-6 md:flex-row">
        <div class="md:w-1/3">
            @if($image)
                <img src="{{ $image }}" alt="{{ $venue->name }}" class="w-full h-auto shadow rounded-xl">
            @else
                <div class="flex items-center justify-center w-full text-gray-400 bg-gray-100 aspect-video rounded-xl">
                    No image
                </div>
            @endif
        </div>
        <div class="space-y-4 md:w-2/3">
            <div>
                <h1 class="text-3xl font-bold">{{ $venue->name }}</h1>
                @if($venue->tenant)
                    <div class="text-sm text-gray-600">Tenant: <span class="font-medium">{{ $venue->tenant->name }}</span></div>
                @endif
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-2">
                @if($venue->address || $venue->city || $venue->state || $venue->country)
                    <div class="p-3 bg-white border rounded-lg">
                        <div class="mb-1 font-semibold">Address</div>
                        <div>
                            {{ $venue->address }}
                            @if($venue->city) , {{ $venue->city }} @endif
                            @if($venue->state) , {{ $venue->state }} @endif
                            @if($venue->country) , {{ $venue->country }} @endif
                        </div>
                    </div>
                @endif
                <div class="p-3 bg-white border rounded-lg">
                    <div class="mb-1 font-semibold">Capacity</div>
                    <div class="flex flex-wrap gap-3">
                        @if($venue->capacity_total)<span class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-md">Total {{ number_format($venue->capacity_total) }}</span>@endif
                        @if($venue->capacity_standing)<span class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-md">Standing {{ number_format($venue->capacity_standing) }}</span>@endif
                        @if($venue->capacity_seated)<span class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-md">Seated {{ number_format($venue->capacity_seated) }}</span>@endif
                    </div>
                </div>
                <div class="p-3 bg-white border rounded-lg">
                    <div class="mb-1 font-semibold">Contacts</div>
                    <ul class="space-y-1">
                        @if($venue->phone)<li>📞 {{ $venue->phone }}</li>@endif
                        @if($venue->email)<li>✉️ {{ $venue->email }}</li>@endif
                        @if($venue->website_url)<li>🌐 <a class="underline" href="{{ $venue->website_url }}" target="_blank" rel="noopener">Website</a></li>@endif
                        @if($venue->facebook_url)<li>📘 <a class="underline" href="{{ $venue->facebook_url }}" target="_blank" rel="noopener">Facebook</a></li>@endif
                        @if($venue->instagram_url)<li>📸 <a class="underline" href="{{ $venue->instagram_url }}" target="_blank" rel="noopener">Instagram</a></li>@endif
                        @if($venue->tiktok_url)<li>🎵 <a class="underline" href="{{ $venue->tiktok_url }}" target="_blank" rel="noopener">TikTok</a></li>@endif
                    </ul>
                </div>
                @if($venue->established_at)
                    <div class="p-3 bg-white border rounded-lg">
                        <div class="mb-1 font-semibold">On the market since</div>
                        <div>{{ $venue->established_at->format('Y-m-d') }}</div>
                    </div>
                @endif
            </div>

            @if($venue->lat && $venue->lng)
                <div>
                    <iframe
                        width="100%" height="280" style="border:0" loading="lazy" allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps?q={{ $venue->lat }},{{ $venue->lng }}&hl=en&z=15&output=embed">
                    </iframe>
                </div>
            @endif

            @if($venue->description)
                <div class="p-4 prose bg-white border rounded-lg max-w-none">{!! $venue->description !!}</div>
            @endif
        </div>
    </div>
</x-filament::section>
