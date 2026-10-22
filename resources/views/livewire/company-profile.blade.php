<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Company letterhead') }}</flux:heading>
        <flux:text class="mt-1">{{ __('These details are printed at the top of every invoice, voucher, note and report.') }}</flux:text>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">{{ $message }}</div>
    @endif

    <form wire:submit="save" class="grid max-w-3xl gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-2">
        <flux:input wire:model="name" :label="__('Company name')" />
        <flux:input wire:model="tin" :label="__('Tax / registration number')" />
        <flux:input wire:model="address" :label="__('Address')" />
        <flux:input wire:model="phone" :label="__('Phone')" />
        <flux:input wire:model="email" type="email" :label="__('Email')" />
        <flux:input wire:model="footer" :label="__('Line at the bottom of documents')" placeholder="{{ __('e.g. Thank you for your business') }}" />

        <div class="flex flex-col gap-2 md:col-span-2">
            <flux:input wire:model="logo" type="file" accept="image/*" :label="__('Logo (PNG or JPG, up to 1 MB)')" />
            @if ($logo)
                <div class="text-xs text-neutral-500">{{ __('Click Save to use this logo.') }}</div>
            @elseif ($company['logo'])
                <div class="flex items-center gap-4">
                    <img src="{{ $company['logo'] }}" alt="" class="max-h-16 max-w-40 rounded bg-white p-1">
                    <flux:button size="sm" type="button" variant="ghost" icon="trash" wire:click="removeLogo" wire:confirm="{{ __('Remove the logo?') }}">{{ __('Remove logo') }}</flux:button>
                </div>
            @endif
        </div>

        <div class="md:col-span-2">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
