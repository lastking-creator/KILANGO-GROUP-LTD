@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg" level="2">{{ __('Expenses') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Rent, salaries, transport and the other costs of running the business.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add expense') }}</flux:button>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">{{ $message }}</div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-3">
            <flux:input wire:model="expenseDate" type="date" :label="__('Date')" />
            <flux:select wire:model="category" :label="__('Category')">
                @foreach ($categories as $c)
                    <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="amount" type="number" step="any" min="0" :label="__('Amount')" />
            <div class="md:col-span-2">
                <flux:input wire:model="description" :label="__('Description (optional)')" />
            </div>
            @if ($isAdmin)
                <flux:select wire:model="outletId" :label="__('For')">
                    <flux:select.option value="">{{ __('General / head office') }}</flux:select.option>
                    @foreach ($outlets as $o)
                        <flux:select.option value="{{ $o->id }}">{{ $o->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <div class="flex gap-2 md:col-span-3">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-44"><flux:input wire:model.live="from" type="date" :label="__('From')" /></div>
        <div class="w-44"><flux:input wire:model.live="to" type="date" :label="__('To')" /></div>
        <div class="w-52">
            <flux:select wire:model.live="categoryFilter" :label="__('Category')">
                <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                @foreach ($categories as $c)
                    <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($isAdmin)
            <div class="w-52">
                <flux:select wire:model.live="outletFilter" :label="__('For')">
                    <flux:select.option value="">{{ __('Everything') }}</flux:select.option>
                    <flux:select.option value="general">{{ __('General / head office') }}</flux:select.option>
                    @foreach ($outlets as $o)
                        <flux:select.option value="{{ $o->id }}">{{ $o->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:badge color="red">{{ __('Total expenses') }}: {{ $money($total) }}</flux:badge>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Category') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('For') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Recorded by') }}</th>
                    @if ($isAdmin)
                        <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($expenses as $expense)
                    <tr wire:key="expense-{{ $expense->id }}">
                        <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($expense->expense_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $expense->category }}</td>
                        <td class="px-4 py-3">{{ $expense->description ?: '-' }}</td>
                        <td class="px-4 py-3">{{ $expense->outlet?->name ?? __('General') }}</td>
                        <td class="px-4 py-3 text-end">{{ $money($expense->amount) }}</td>
                        <td class="px-4 py-3">{{ $expense->user?->name }}</td>
                        @if ($isAdmin)
                            <td class="px-4 py-3 text-end">
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $expense->id }})" wire:confirm="{{ __('Delete this expense?') }}" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-neutral-500">{{ __('No expenses found for these filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $expenses->links() }}</div>
</div>
