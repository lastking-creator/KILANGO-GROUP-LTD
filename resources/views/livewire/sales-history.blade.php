@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $methodLabels = ['cash' => __('Cash'), 'mobile_money' => __('Mobile money'), 'card' => __('Card'), 'on_account' => __('On account')];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Sales history') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every sale, with its receipt. Managers can void a sale from an open shift.') }}</flux:text>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4">
        <div class="w-44"><flux:input wire:model.live="from" type="date" :label="__('From')" /></div>
        <div class="w-44"><flux:input wire:model.live="to" type="date" :label="__('To')" /></div>
        @if ($outlets->isNotEmpty())
            <div class="w-48">
                <flux:select wire:model.live="outletFilter" :label="__('Outlet')">
                    <flux:select.option value="">{{ __('All outlets') }}</flux:select.option>
                    @foreach ($outlets as $o)
                        <flux:select.option value="{{ $o->id }}">{{ $o->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
        <div class="w-40">
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
                <flux:select.option value="voided">{{ __('Voided') }}</flux:select.option>
            </flux:select>
        </div>
        <div class="w-52"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Receipt number')" /></div>
    </div>

    {{-- Summary --}}
    <div class="flex flex-wrap gap-2">
        <flux:badge color="zinc">{{ __('Sales') }}: {{ $count }}</flux:badge>
        <flux:badge color="green">{{ __('Total') }}: {{ $money($sum) }}</flux:badge>
        @foreach ($methodLabels as $key => $label)
            <flux:badge color="zinc">{{ $label }}: {{ $money($byMethod[$key] ?? 0) }}</flux:badge>
        @endforeach
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">{{ $message }}</div>
    @endif
    @if ($error)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">{{ $error }}</div>
    @endif

    {{-- List --}}
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Receipt') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Outlet') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Cashier') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Customer') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Total') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('On account') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($sales as $sale)
                    <tr wire:key="sale-{{ $sale->id }}" class="{{ $viewSale && $viewSale->id === $sale->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $sale->receipt_no }}</td>
                        <td class="px-4 py-3">{{ $sale->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $sale->outlet?->name }}</td>
                        <td class="px-4 py-3">{{ $sale->cashier?->name }}</td>
                        <td class="px-4 py-3">{{ $sale->customer?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-end">{{ $money($sale->total) }}</td>
                        <td class="px-4 py-3 text-end">{{ (float) $sale->amount_on_account > 0 ? $money($sale->amount_on_account) : '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($sale->status === 'completed')
                                <flux:badge color="green" size="sm">{{ __('Completed') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Voided') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end">
                            <flux:button size="sm" wire:click="viewSale({{ $sale->id }})">{{ __('Open') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-neutral-500">{{ __('No sales found for these filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $sales->links() }}</div>

    {{-- Sale details --}}
    @if ($viewSale)
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg" level="2">{{ $viewSale->receipt_no }}</flux:heading>
                        @if ($viewSale->status === 'completed')
                            <flux:badge color="green" size="sm">{{ __('Completed') }}</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">{{ __('Voided') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="mt-1">
                        {{ $viewSale->created_at->format('d M Y H:i') }} · {{ $viewSale->outlet?->name }} · {{ __('Cashier') }}: {{ $viewSale->cashier?->name }}
                        @if ($viewSale->customer) · {{ __('Customer') }}: {{ $viewSale->customer->name }} @endif
                    </flux:text>
                    @if ($viewSale->status === 'voided')
                        <flux:text class="mt-1 text-red-600 dark:text-red-400">
                            {{ __('Voided by :name on :date', ['name' => $viewSale->voider?->name, 'date' => $viewSale->voided_at?->format('d M Y H:i')]) }}
                            @if ($viewSale->void_reason) · {{ $viewSale->void_reason }} @endif
                        </flux:text>
                    @endif
                </div>

                <div class="flex gap-2">
                    <flux:button size="sm" icon="printer" href="{{ route('sales.receipt', $viewSale) }}" target="_blank">{{ __('Print receipt') }}</flux:button>
                    <flux:button size="sm" wire:click="closeSale">{{ __('Close') }}</flux:button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-start text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Product') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Batch') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Quantity') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Price') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($viewSale->items as $item)
                            <tr wire:key="sitem-{{ $item->id }}">
                                <td class="px-4 py-3 font-medium">{{ $item->product?->name }}</td>
                                <td class="px-4 py-3">{{ $item->batch?->batch_no }}</td>
                                <td class="px-4 py-3 text-end">{{ $fmt($item->quantity) }} {{ $item->product?->baseUnit?->abbreviation }}</td>
                                <td class="px-4 py-3 text-end">{{ $money($item->unit_price) }}</td>
                                <td class="px-4 py-3 text-end">{{ $money($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1 text-sm">
                    <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ $money($viewSale->subtotal) }}</span></div>
                    @if ((float) $viewSale->discount > 0)
                        <div class="flex justify-between"><span>{{ __('Discount') }}</span><span>-{{ $money($viewSale->discount) }}</span></div>
                    @endif
                    <div class="flex justify-between text-base font-semibold"><span>{{ __('Total') }}</span><span>{{ $money($viewSale->total) }}</span></div>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    @foreach ($viewSale->payments as $payment)
                        <div class="flex justify-between">
                            <span>{{ $methodLabels[$payment->method] ?? $payment->method }}@if ($payment->reference) ({{ $payment->reference }})@endif</span>
                            <span>{{ $money($payment->amount) }}</span>
                        </div>
                    @endforeach
                    @if ((float) $viewSale->change_given > 0)
                        <div class="flex justify-between"><span>{{ __('Change given') }}</span><span>{{ $money($viewSale->change_given) }}</span></div>
                    @endif
                </div>
            </div>

            @if ($canVoid && $viewSale->status === 'completed')
                @if ($viewSale->shift && $viewSale->shift->closed_at)
                    <flux:text class="text-xs">{{ __('This sale belongs to a closed shift, so it can no longer be voided.') }}</flux:text>
                @else
                    <div class="flex flex-wrap items-end gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                        <div class="w-full max-w-sm">
                            <flux:input wire:model="voidReason" :label="__('Reason for voiding')" />
                        </div>
                        <flux:button variant="danger" wire:click="voidSale({{ $viewSale->id }})" wire:confirm="{{ __('Void this sale? The stock goes back to the outlet.') }}">
                            {{ __('Void sale') }}
                        </flux:button>
                    </div>
                    @error('voidReason')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                @endif
            @endif
        </div>
    @endif
</div>
