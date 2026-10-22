<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Employees list') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Everyone who works in your godowns and outlets.') }}</flux:text>
        </div>

        @if ($isAdmin)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add employee') }}</flux:button>
        @endif
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-3">
            <flux:input wire:model="employeeNo" :label="__('Employee number')" placeholder="EMP001" />
            <flux:input wire:model="fullName" :label="__('Full name')" />
            <flux:input wire:model="position" :label="__('Position')" placeholder="{{ __('e.g. Storekeeper, Cashier') }}" />
            <flux:input wire:model="phone" :label="__('Phone')" />
            <flux:input wire:model="email" type="email" :label="__('Email')" />
            <flux:input wire:model="hireDate" type="date" :label="__('Hire date')" />

            <flux:select wire:model="assignment" :label="__('Works at')">
                <flux:select.option value="">{{ __('Not assigned') }}</flux:select.option>
                @foreach ($godowns as $godown)
                    <flux:select.option value="godown:{{ $godown->id }}">{{ __('Godown') }}: {{ $godown->name }}</flux:select.option>
                @endforeach
                @foreach ($outlets as $outlet)
                    <flux:select.option value="outlet:{{ $outlet->id }}">{{ __('Outlet') }}: {{ $outlet->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="deviceNo" :label="__('Attendance device ID (optional)')" placeholder="{{ __('ID enrolled on the device') }}" />

            <div class="flex gap-2 md:col-span-3">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search name, number or phone') }}" />
        </div>
        <div class="w-44">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('No.') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Position') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Works at') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Device ID') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    @if ($isAdmin)
                        <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($employees as $employee)
                    <tr wire:key="employee-{{ $employee->id }}">
                        <td class="px-4 py-3 font-medium">{{ $employee->employee_no }}</td>
                        <td class="px-4 py-3">
                            <div>{{ $employee->full_name }}</div>
                            @if ($employee->phone)
                                <div class="text-xs text-neutral-500">{{ $employee->phone }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $employee->position ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($employee->godown)
                                {{ __('Godown') }}: {{ $employee->godown->name }}
                            @elseif ($employee->outlet)
                                {{ __('Outlet') }}: {{ $employee->outlet->name }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $employee->device_employee_no ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($employee->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </td>
                        @if ($isAdmin)
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $employee->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggle({{ $employee->id }})">
                                        {{ $employee->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-neutral-500">{{ __('No employees found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $employees->links() }}</div>
</div>
