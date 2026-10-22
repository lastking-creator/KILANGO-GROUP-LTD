@php
    $statusLabels = ['present' => __('Present'), 'late' => __('Late'), 'absent' => __('Absent'), 'leave' => __('On leave')];
    $statusColors = ['present' => 'green', 'late' => 'amber', 'absent' => 'red', 'leave' => 'blue'];
    $time = fn ($value) => $value ? substr((string) $value, 0, 5) : '-';
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Attendance list') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Record who came to work each day.') }}</flux:text>
    </div>

    {{-- Date picker --}}
    <div class="flex flex-wrap items-center gap-2">
        <flux:button size="sm" icon="chevron-left" wire:click="previousDay" />
        <div class="w-44">
            <flux:input type="date" wire:model.live="date" />
        </div>
        <flux:button size="sm" icon="chevron-right" wire:click="nextDay" />
        <flux:button size="sm" wire:click="today">{{ __('Today') }}</flux:button>
        <span class="ms-2 text-sm text-neutral-500">{{ $dayLabel }}</span>
        <flux:button size="sm" icon="arrow-path" class="ms-auto" wire:click="syncDevice" wire:loading.attr="disabled" wire:target="syncDevice">
            <span wire:loading.remove wire:target="syncDevice">{{ __('Sync from device') }}</span>
            <span wire:loading wire:target="syncDevice">{{ __('Reading the device...') }}</span>
        </flux:button>
    </div>

    {{-- Summary --}}
    <div class="flex flex-wrap gap-2">
        <flux:badge color="green">{{ __('Present') }}: {{ $counts['present'] }}</flux:badge>
        <flux:badge color="amber">{{ __('Late') }}: {{ $counts['late'] }}</flux:badge>
        <flux:badge color="red">{{ __('Absent') }}: {{ $counts['absent'] }}</flux:badge>
        <flux:badge color="blue">{{ __('On leave') }}: {{ $counts['leave'] }}</flux:badge>
        <flux:badge color="zinc">{{ __('Not recorded') }}: {{ $counts['none'] }}</flux:badge>
    </div>

    @if ($error)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    {{-- Edit form --}}
    @if ($showForm)
        <form wire:submit="saveForm" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-4">
            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="present">{{ __('Present') }}</flux:select.option>
                <flux:select.option value="late">{{ __('Late') }}</flux:select.option>
                <flux:select.option value="absent">{{ __('Absent') }}</flux:select.option>
                <flux:select.option value="leave">{{ __('On leave') }}</flux:select.option>
            </flux:select>

            @if (in_array($status, ['present', 'late'], true))
                <flux:input wire:model="checkIn" type="time" :label="__('Check in')" />
                <flux:input wire:model="checkOut" type="time" :label="__('Check out')" />
            @endif

            <flux:input wire:model="note" :label="__('Note')" />

            <div class="flex gap-2 md:col-span-4">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="closeForm">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- Attendance table --}}
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Employee') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Works at') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Check in') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Check out') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Source') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($employees as $employee)
                    @php $record = $records->get($employee->id); @endphp
                    <tr wire:key="attendance-{{ $employee->id }}" class="{{ $formEmployeeId === $employee->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $employee->full_name }}</div>
                            <div class="text-xs text-neutral-500">{{ $employee->employee_no }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($employee->godown)
                                {{ $employee->godown->name }}
                            @elseif ($employee->outlet)
                                {{ $employee->outlet->name }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($record)
                                <flux:badge :color="$statusColors[$record->status]" size="sm">{{ $statusLabels[$record->status] }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Not recorded') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $time($record?->check_in) }}</td>
                        <td class="px-4 py-3">{{ $time($record?->check_out) }}</td>
                        <td class="px-4 py-3">
                            {{ $record ? ($record->source === 'device' ? __('Device') : __('Manual')) : '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" wire:click="markPresent({{ $employee->id }})">{{ __('Present') }}</flux:button>
                                <flux:button size="sm" wire:click="markAbsent({{ $employee->id }})">{{ __('Absent') }}</flux:button>
                                @if ($isToday && $record && $record->check_in && ! $record->check_out && in_array($record->status, ['present', 'late'], true))
                                    <flux:button size="sm" wire:click="checkOutNow({{ $employee->id }})">{{ __('Check out') }}</flux:button>
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="editRecord({{ $employee->id }})">{{ __('Edit') }}</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-neutral-500">
                            {{ __('No active employees yet. Add them in the Employees list first.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
