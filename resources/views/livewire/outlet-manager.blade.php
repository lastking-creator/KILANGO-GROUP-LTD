<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Outlets') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage the outlets that receive stock from the godowns and sell to customers.') }}</flux:text>
        </div>

        @if ($isAdmin)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add outlet') }}</flux:button>
        @endif
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-2">
            <flux:input wire:model="code" :label="__('Code')" placeholder="O1" />
            <flux:input wire:model="name" :label="__('Name')" />
            <flux:input wire:model="location" :label="__('Location')" />
            <flux:input wire:model="phone" :label="__('Phone')" />

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
                    <th class="px-4 py-3 text-start font-medium">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Location') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Phone') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    @if ($isAdmin)
                        <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($outlets as $outlet)
                    <tr wire:key="outlet-{{ $outlet->id }}">
                        <td class="px-4 py-3 font-medium">{{ $outlet->code }}</td>
                        <td class="px-4 py-3">{{ $outlet->name }}</td>
                        <td class="px-4 py-3">{{ $outlet->location ?: '-' }}</td>
                        <td class="px-4 py-3">{{ $outlet->phone ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($outlet->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </td>
                        @if ($isAdmin)
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $outlet->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggle({{ $outlet->id }})">
                                        {{ $outlet->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No outlets yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
