<x-layouts::app :title="__('Finance overview')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Finance overview') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Money in, money out, profit, and what customers owe.') }}</flux:text>
        </div>

        <livewire:finance-overview />
    </div>
</x-layouts::app>
