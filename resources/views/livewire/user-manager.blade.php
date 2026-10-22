@php
    $roleLabels = [
        'admin' => __('Admin'),
        'godown_keeper' => __('Godown keeper'),
        'outlet_manager' => __('Outlet manager'),
        'cashier' => __('Cashier'),
    ];
    $roleColors = ['admin' => 'purple', 'godown_keeper' => 'blue', 'outlet_manager' => 'amber', 'cashier' => 'zinc'];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The people who log in to this system, what each one can do, and where they work.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add user') }}</flux:button>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">{{ $message }}</div>
    @endif
    @if ($error)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">{{ $error }}</div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="name" :label="__('Full name')" />
                <flux:input wire:model="email" type="email" :label="__('Email (used to log in)')" />
                <flux:input wire:model="phone" :label="__('Phone')" />

                <flux:select wire:model.live="role" :label="__('Role')">
                    <flux:select.option value="admin">{{ __('Admin: everything') }}</flux:select.option>
                    <flux:select.option value="godown_keeper">{{ __('Godown keeper: godowns, purchases, transfers') }}</flux:select.option>
                    <flux:select.option value="outlet_manager">{{ __('Outlet manager: an outlet, its sales and money') }}</flux:select.option>
                    <flux:select.option value="cashier">{{ __('Cashier: selling at an outlet') }}</flux:select.option>
                </flux:select>

                @if ($role === 'godown_keeper')
                    <flux:select wire:model="godownId" :label="__('Godown')">
                        <flux:select.option value="">{{ __('All godowns') }}</flux:select.option>
                        @foreach ($godowns as $godown)
                            <flux:select.option value="{{ $godown->id }}">{{ $godown->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif (in_array($role, ['outlet_manager', 'cashier'], true))
                    <flux:select wire:model="outletId" :label="__('Outlet')">
                        <flux:select.option value="">{{ __('Choose an outlet') }}</flux:select.option>
                        @foreach ($outlets as $outlet)
                            <flux:select.option value="{{ $outlet->id }}">{{ $outlet->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <div class="flex flex-col gap-2">
                    <flux:input wire:model="password" type="text" autocomplete="off"
                        :label="$userId ? __('New password (leave empty to keep the current one)') : __('Password')" />
                    <div>
                        <flux:button size="sm" type="button" icon="key" wire:click="generatePassword">{{ __('Generate a password') }}</flux:button>
                    </div>
                </div>
            </div>

            <flux:text class="text-xs">
                {{ __('Write the password down and give it to the person. They can change it themselves under Settings after logging in.') }}
            </flux:text>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search name or email') }}" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="roleFilter">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach ($roleLabels as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-40">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Deactivated') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Works at') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <flux:badge color="zinc" size="sm">{{ __('You') }}</flux:badge>
                                @endif
                            </div>
                            @if ($user->phone)
                                <div class="text-xs text-neutral-500">{{ $user->phone }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$roleColors[$user->role] ?? 'zinc'" size="sm">{{ $roleLabels[$user->role] ?? $user->role }}</flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->outlet_id)
                                {{ __('Outlet') }}: {{ $outletNames[$user->outlet_id] ?? '-' }}
                            @elseif ($user->godown_id)
                                {{ __('Godown') }}: {{ $godownNames[$user->godown_id] ?? '-' }}
                            @elseif ($user->role === 'godown_keeper')
                                {{ __('All godowns') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Deactivated') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</flux:button>
                                @if ($user->id !== auth()->id())
                                    <flux:button size="sm" variant="ghost" wire:click="toggle({{ $user->id }})">
                                        {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No users found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>
</div>
