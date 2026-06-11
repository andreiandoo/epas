<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="saveSmtp">
            {{ $this->smtpForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Save SMTP Settings
                </x-filament::button>
            </div>
        </form>

        <form wire:submit="saveEmail">
            {{ $this->emailForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Save Email Settings
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
