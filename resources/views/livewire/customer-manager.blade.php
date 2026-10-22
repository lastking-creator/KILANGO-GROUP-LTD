@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
    $methodLabels = ['cash' => __('Cash'), 'mobile_money' => __('Mobile money'), 'card' => __('Card')];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Customers') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your customers, what each one owes, and payments received.') }}</flux:text>
        </div>

        @if ($canEdit)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add customer') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:badge color="amber">{{ __('Customers who owe') }}: {{ $owingCount }}</flux:badge>
        <flux:badge color="red">{{ __('Total owed') }}: {{ $money($owingTotal) }}</flux:badge>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">{{ $message }}</div>
    @endif
    @if ($error)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">{{ $error }}</div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-3">
            <flux:input wire:model="name" :label="__('Name')" />
            <flux:input wire:model="phone" :label="__('Phone')" />
            <flux:input wire:model="creditLimit" type="number" step="any" min="0" :label="__('Credit limit (0 = no credit)')" />
            <div class="flex gap-2 md:col-span-3">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search name or phone') }}" />
        </div>
        <div class="w-40">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            </flux:select>
        </div>
        <flux:checkbox wire:model.live="owingOnly" :label="__('Only customers who owe')" />
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Customer') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Phone') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Credit limit') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Owes') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Credit left') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($customers as $customer)
                    @php
                        $owes = max(0, round((float) ($balances[$customer->id] ?? 0), 2));
                        $left = max(0, (float) $customer->credit_limit - $owes);
                    @endphp
                    <tr wire:key="customer-{{ $customer->id }}" class="{{ $statementCustomer && $statementCustomer->id === $customer->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $customer->name }}</td>
                        <td class="px-4 py-3">{{ $customer->phone ?: '-' }}</td>
                        <td class="px-4 py-3 text-end">{{ (float) $customer->credit_limit > 0 ? $money($customer->credit_limit) : '-' }}</td>
                        <td class="px-4 py-3 text-end {{ $owes > 0 ? 'font-medium text-red-600 dark:text-red-400' : '' }}">{{ $owes > 0 ? $money($owes) : '-' }}</td>
                        <td class="px-4 py-3 text-end">{{ (float) $customer->credit_limit > 0 ? $money($left) : '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($customer->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" wire:click="openStatement({{ $customer->id }})">{{ __('Statement') }}</flux:button>
                                @if ($owes > 0)
                                    <flux:button size="sm" variant="primary" wire:click="startPayment({{ $customer->id }})">{{ __('Receive payment') }}</flux:button>
                                @endif
                                @if ($canEdit)
                                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $customer->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggle({{ $customer->id }})">
                                        {{ $customer->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-neutral-500">{{ __('No customers found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $customers->links() }}</div>

    {{-- Statement and payment --}}
    @if ($statementCustomer)
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg" level="2">{{ __('Statement: :name', ['name' => $statementCustomer->name]) }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Owes now') }}: <strong>{{ $money(max(0, $statementBalance)) }}</strong>
                        @if ((float) $statementCustomer->credit_limit > 0) · {{ __('Credit limit') }}: {{ $money($statementCustomer->credit_limit) }} @endif
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    @if ($statementBalance > 0.004 && ! $showPayment)
                        <flux:button size="sm" variant="primary" wire:click="startPayment({{ $statementCustomer->id }})">{{ __('Receive payment') }}</flux:button>
                    @endif
                    <flux:button size="sm" wire:click="closeStatement">{{ __('Close') }}</flux:button>
                </div>
            </div>

            @if ($showPayment)
                <form wire:submit="savePayment" class="grid gap-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-4">
                    <flux:input wire:model="payAmount" type="number" step="any" min="0" :label="__('Amount received')" />
                    <flux:select wire:model="payMethod" :label="__('Paid by')">
                        @foreach ($methodLabels as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="payReference" :label="__('Reference (optional)')" />
                    <flux:input wire:model="payNote" :label="__('Note (optional)')" />
                    <div class="flex gap-2 md:col-span-4">
                        <flux:button type="submit" variant="primary">{{ __('Save payment') }}</flux:button>
                        <flux:button type="button" wire:click="cancelPayment">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif

            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-start text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Details') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Bought on account') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Paid') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse ($entries as $entry)
                            <tr wire:key="ledger-{{ $entry->id }}">
                                <td class="px-4 py-3">{{ $entry->created_at->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    {{ $entry->note }}
                                    @if ($entry->sale) · {{ __('Receipt') }} {{ $entry->sale->receipt_no }} @endif
                                    @if ($entry->payment && ! $entry->sale) · {{ $methodLabels[$entry->payment->method] ?? $entry->payment->method }}@if ($entry->payment->reference) ({{ $entry->payment->reference }})@endif @endif
                                </td>
                                <td class="px-4 py-3 text-end">{{ $entry->entry_type === 'debit' ? $money($entry->amount) : '' }}</td>
                                <td class="px-4 py-3 text-end">{{ $entry->entry_type === 'credit' ? $money($entry->amount) : '' }}</td>
                                <td class="px-4 py-3 text-end">{{ $money($entry->running) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-neutral-500">{{ __('No activity on this account yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
