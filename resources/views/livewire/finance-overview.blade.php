@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end gap-4">
        <div class="w-44"><flux:input wire:model.live="from" type="date" :label="__('From')" /></div>
        <div class="w-44"><flux:input wire:model.live="to" type="date" :label="__('To')" /></div>
        @if ($outlets->isNotEmpty())
            <div class="w-52">
                <flux:select wire:model.live="outletFilter" :label="__('Outlet')">
                    <flux:select.option value="">{{ __('All outlets') }}</flux:select.option>
                    @foreach ($outlets as $o)
                        <flux:select.option value="{{ $o->id }}">{{ $o->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    {{-- Profit --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="text-sm text-neutral-500">{{ __('Sales') }} ({{ $salesCount }})</div>
            <div class="mt-1 text-xl font-semibold">{{ $money($salesTotal) }}</div>
            @if ($discounts > 0)
                <div class="mt-1 text-xs text-neutral-500">{{ __('after :amount of discounts', ['amount' => $money($discounts)]) }}</div>
            @endif
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="text-sm text-neutral-500">{{ __('Cost of goods sold') }}</div>
            <div class="mt-1 text-xl font-semibold">{{ $money($cogs) }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="text-sm text-neutral-500">{{ __('Gross profit') }}</div>
            <div class="mt-1 text-xl font-semibold {{ $grossProfit < 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $money($grossProfit) }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="text-sm text-neutral-500">{{ __('Expenses') }}</div>
            <div class="mt-1 text-xl font-semibold">{{ $money($expenses) }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <div class="text-sm text-neutral-500">{{ __('Net profit (gross profit minus expenses)') }}</div>
        <div class="mt-1 text-2xl font-semibold {{ $netProfit < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">{{ $money($netProfit) }}</div>
        <div class="mt-1 text-xs text-neutral-500">{{ __('Cost comes from the unit cost entered when goods were received. If costs were left at zero, profit looks higher than it really is.') }}</div>
    </div>

    {{-- Money in --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:heading size="lg" level="2">{{ __('Money received') }}</flux:heading>
            <div class="flex justify-between text-sm"><span>{{ __('Cash') }}</span><span>{{ $money($received['cash'] ?? 0) }}</span></div>
            <div class="flex justify-between text-sm"><span>{{ __('Mobile money') }}</span><span>{{ $money($received['mobile_money'] ?? 0) }}</span></div>
            <div class="flex justify-between text-sm"><span>{{ __('Card') }}</span><span>{{ $money($received['card'] ?? 0) }}</span></div>
            <div class="flex justify-between border-t border-neutral-200 pt-2 font-medium dark:border-neutral-700">
                <span>{{ __('Total received') }}</span>
                <span>{{ $money(($received['cash'] ?? 0) + ($received['mobile_money'] ?? 0) + ($received['card'] ?? 0)) }}</span>
            </div>
            <div class="text-xs text-neutral-500">{{ __('Includes :amount paid by customers towards their accounts.', ['amount' => $money($repaid)]) }}</div>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:heading size="lg" level="2">{{ __('Credit and purchases') }}</flux:heading>
            <div class="flex justify-between text-sm"><span>{{ __('Sold on account in this period') }}</span><span>{{ $money($onAccount) }}</span></div>
            <div class="flex justify-between text-sm"><span>{{ __('Customers owe you now') }}</span><span class="font-medium text-red-600 dark:text-red-400">{{ $money($owedTotal) }}</span></div>
            @if ($purchases !== null)
                <div class="flex justify-between text-sm"><span>{{ __('Goods bought from suppliers') }}</span><span>{{ $money($purchases) }}</span></div>
            @endif

            @if ($topDebtors->isNotEmpty())
                <div class="mt-1 border-t border-neutral-200 pt-2 text-sm dark:border-neutral-700">
                    <div class="mb-1 text-neutral-500">{{ __('Biggest debts') }} ({{ $debtorCount }} {{ __('customers') }})</div>
                    @foreach ($topDebtors as $debtor)
                        <div class="flex justify-between"><span>{{ $debtor->name }}</span><span>{{ $money($debtor->balance) }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Shifts --}}
    <div class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Cashier shifts') }}</flux:heading>
        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="w-full text-start text-sm">
                <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Opened') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Outlet') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Cashier') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('Opening cash') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('Expected') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('Counted') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('Difference') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($shifts as $shift)
                        @php $diff = $shift->closed_at ? round((float) $shift->counted_cash - (float) $shift->expected_cash, 2) : null; @endphp
                        <tr wire:key="shift-{{ $shift->id }}">
                            <td class="px-4 py-3">{{ $shift->opened_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $shift->outlet?->name }}</td>
                            <td class="px-4 py-3">{{ $shift->user?->name }}</td>
                            <td class="px-4 py-3 text-end">{{ $money($shift->opening_cash) }}</td>
                            @if ($shift->closed_at)
                                <td class="px-4 py-3 text-end">{{ $money($shift->expected_cash) }}</td>
                                <td class="px-4 py-3 text-end">{{ $money($shift->counted_cash) }}</td>
                                <td class="px-4 py-3 text-end font-medium {{ $diff < 0 ? 'text-red-600 dark:text-red-400' : ($diff > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400') }}">
                                    {{ $diff == 0 ? __('Matches') : ($diff > 0 ? '+' : '').$money($diff) }}
                                </td>
                            @else
                                <td colspan="3" class="px-4 py-3 text-end"><flux:badge color="blue" size="sm">{{ __('Open') }}</flux:badge></td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-neutral-500">{{ __('No shifts in this period.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
