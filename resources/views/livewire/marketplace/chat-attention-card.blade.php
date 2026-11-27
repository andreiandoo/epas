<a class="ep-att {{ $unclaimed > 0 ? 'tone-amber' : '' }}" href="/marketplace/chat-console" wire:poll.30s>
    <span class="n">{{ number_format($unclaimed) }}</span>
    <div>
        <div class="t">{{ __('Chat-uri nepreluate') }}</div>
        <div class="ep-hint">{{ number_format($active) }} {{ __('active') }} | {{ number_format($today) }} {{ __('azi') }}</div>
    </div>
</a>
