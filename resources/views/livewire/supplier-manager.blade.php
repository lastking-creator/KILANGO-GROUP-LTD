<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg" level="2">{{ __('Suppliers') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The companies and people you buy goods from.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add supplier') }}</flux:button>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('Name')" />
            <flux:input wire:model="phone" :label="__('Phone')" />
            <flux:input wire:model="email" type="email" :label="__('Email')" />
            <flux:input wire:model="address" :label="__('Address')" />

            <div class="flex gap-2 md:col-span-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Phone') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Address') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($suppliers as $supplier)
                    <tr wire:key="supplier-{{ $supplier->id }}">
                        <td class="px-4 py-3 font-medium">{{ $supplier->name }}</td>
                        <td class="px-4 py-3">{{ $supplier->phone ?: '-' }}</td>
                        <td class="px-4 py-3">{{ $supplier->email ?: '-' }}</td>
                        <td class="px-4 py-3">{{ $supplier->address ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($supplier->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="edit({{ $supplier->id }})">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggle({{ $supplier->id }})">
                                    {{ $supplier->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No suppliers yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
