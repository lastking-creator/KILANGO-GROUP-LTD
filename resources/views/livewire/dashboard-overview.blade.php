@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $roleTitles = ['admin' => __('Admin'), 'godown_keeper' => __('Godown keeper'), 'outlet_manager' => __('Outlet manager'), 'cashier' => __('Cashier')];
    $statusColors = ['pending' => 'amber', 'in_transit' => 'blue', 'received' => 'green', 'cancelled' => 'zinc'];
    $statusLabels = ['pending' => __('Pending'), 'in_transit' => __('In transit'), 'received' => __('Received'), 'cancelled' => __('Cancelled')];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => $firstName]) }}</flux:heading>
            <flux:text class="mt-1">{{ $roleTitles[$role] ?? $role }} · {{ now()->format('l, d F Y') }}</flux:text>
        </div>

        @if ($role !== 'cashier')
            <div class="w-52">
                <flux:select wire:model.live="period" :label="__('Show figures for')">
                    <flux:select.option value="today">{{ __('Today') }}</flux:select.option>
                    <flux:select.option value="week">{{ __('Last 7 days') }}</flux:select.option>
                    <flux:select.option value="month">{{ __('This month') }}</flux:select.option>
                    <flux:select.option value="last30">{{ __('Last 30 days') }}</flux:select.option>
                </flux:select>
            </div>
        @endif
    </div>

    {{-- ===================== CASHIER ===================== --}}
    @if ($role === 'cashier')
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card :label="__('Your shift')" :value="$shift ? __('Open') : __('Not open')" :tone="$shift ? 'good' : 'warn'"
                :hint="$shift ? __('Since :time', ['time' => $shift->opened_at->format('H:i')]) : ($outletName ? $outletName : __('Ask an admin to assign you to an outlet'))" />
            <x-stat-card :label="__('Your sales today')" :value="$myCount" />
            <x-stat-card :label="__('Your sales total today')" :value="$money($myTotal)" />
        </div>

        <div>
            <flux:button variant="primary" icon="shopping-cart" href="{{ route('sales') }}" wire:navigate>{{ __('Go to Sales') }}</flux:button>
        </div>
    @else
        {{-- ===================== KEY NUMBERS ===================== --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($showSales)
                <x-stat-card :label="__('Sales today')" :value="$money($salesToday['total'])" :hint="trans_choice('{1} :count sale|[0,*] :count sales', $salesToday['count'], ['count' => $salesToday['count']])" />
                <x-stat-card :label="__('Sales :period', ['period' => __($periodLabel)])" :value="$money($salesPeriod['total'])" :hint="trans_choice('{1} :count sale|[0,*] :count sales', $salesPeriod['count'], ['count' => $salesPeriod['count']])" />
                <x-stat-card :label="__('Gross profit :period', ['period' => __($periodLabel)])" :value="$money($grossProfit)" :tone="$grossProfit < 0 ? 'bad' : null" />
                <x-stat-card :label="__('Customers owe')" :value="$money($owedTotal)" :tone="$owedTotal > 0 ? 'warn' : null" :hint="$role === 'admin' ? __('across all outlets') : null" />
            @endif

            <x-stat-card :label="$role === 'outlet_manager' ? __('Goods at your outlet') : __('Goods in stock')" :value="$stockProducts.' '.__('products')" :hint="__('Worth :value at cost', ['value' => $money($stockValue)])" />
            <x-stat-card :label="__('Expiring in 30 days')" :value="$expiringCount" :tone="$expiringCount > 0 ? 'warn' : null" :hint="$expiredCount > 0 ? __(':count already expired', ['count' => $expiredCount]) : null" />

            @if ($showGodowns)
                <x-stat-card :label="__('Low stock products')" :value="$lowCount" :tone="$lowCount > 0 ? 'warn' : null" />
            @endif

            <x-stat-card :label="__('Employees')" :value="$employeeCount" :hint="__(':count present today', ['count' => $presentToday])" />
            <x-stat-card :label="__('Transfers waiting')" :value="$pendingTransfers + $transitTransfers"
                :hint="__(':pending to dispatch, :transit on the way', ['pending' => $pendingTransfers, 'transit' => $transitTransfers])" />
        </div>

        {{-- ===================== SALES TREND + TOP PRODUCTS ===================== --}}
        @if ($showSales)
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 lg:col-span-2">
                    <flux:heading size="lg" level="2">{{ __('Sales, last 14 days') }}</flux:heading>
                    <div class="flex h-44 items-end gap-1.5">
                        @foreach ($trend as $point)
                            @php $h = $point['total'] > 0 ? max(4, (int) round($point['total'] / $trendMax * 100)) : 2; @endphp
                            <div class="flex h-full flex-1 flex-col justify-end" title="{{ $point['date']->format('D d M') }}: {{ $money($point['total']) }}">
                                <div class="w-full rounded-t {{ $point['date']->isToday() ? 'bg-blue-500' : 'bg-neutral-400 dark:bg-neutral-500' }}" style="height: {{ $h }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex gap-1.5 text-center text-[10px] text-neutral-500">
                        @foreach ($trend as $point)
                            <div class="flex-1">{{ $point['date']->format('d') }}</div>
                        @endforeach
                    </div>
                    <flux:text class="text-xs">{{ __('Each bar is one day. Today is blue. Hover a bar to see the amount.') }}</flux:text>
                </div>

                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="lg" level="2">{{ __('Best sellers') }}</flux:heading>
                    @forelse ($topProducts as $i => $product)
                        <div class="flex items-start justify-between gap-3 text-sm">
                            <div>
                                <div class="font-medium">{{ $i + 1 }}. {{ $product->name }}</div>
                                <div class="text-xs text-neutral-500">{{ $fmt($product->qty) }} {{ $product->unit }}</div>
                            </div>
                            <div class="text-end">{{ $money($product->revenue) }}</div>
                        </div>
                    @empty
                        <flux:text>{{ __('No sales in this period yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- ===================== OUTLETS ===================== --}}
        @if ($showSales)
            <div class="flex flex-col gap-3">
                <flux:heading size="lg" level="2">{{ __('Outlets at a glance') }}</flux:heading>
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <table class="w-full text-start text-sm">
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium">{{ __('Outlet') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Employees') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Present today') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Products in stock') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Stock value') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Sales today') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Sales :period', ['period' => __($periodLabel)]) }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Open shifts') }}</th>
                                <th class="px-4 py-3 text-end font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @forelse ($outletRows as $row)
                                <tr wire:key="orow-{{ $row['id'] }}" class="{{ $selectedOutletId === $row['id'] ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                                    <td class="px-4 py-3 font-medium">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['employees'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['present'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['products'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $money($row['stockValue']) }}</td>
                                    <td class="px-4 py-3 text-end">{{ $money($row['salesToday']) }}</td>
                                    <td class="px-4 py-3 text-end">{{ $money($row['salesPeriod']) }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['openShifts'] }}</td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($canPickOutlet)
                                            <flux:button size="sm" wire:click="selectOutlet({{ $row['id'] }})">{{ __('Details') }}</flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-6 text-center text-neutral-500">{{ __('No outlets yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Outlet in detail: staff and goods available --}}
        @if (isset($detail))
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading size="lg" level="2">{{ $detail['outlet']->name }}</flux:heading>
                    @if ($canPickOutlet)
                        <flux:button size="sm" wire:click="closeOutlet">{{ __('Close') }}</flux:button>
                    @endif
                </div>

                <div class="grid gap-4 lg:grid-cols-5">
                    <div class="flex flex-col gap-2 lg:col-span-2">
                        <flux:heading size="sm">{{ __('Employees (:count)', ['count' => $detail['staff']->count()]) }}</flux:heading>
                        @forelse ($detail['staff'] as $person)
                            @php $att = $detail['attendance']->get($person->id); @endphp
                            <div class="flex items-center justify-between gap-2 text-sm" wire:key="staff-{{ $person->id }}">
                                <div>
                                    <div class="font-medium">{{ $person->full_name }}</div>
                                    <div class="text-xs text-neutral-500">{{ $person->position ?: '-' }}</div>
                                </div>
                                @if ($att)
                                    <flux:badge :color="['present' => 'green', 'late' => 'amber', 'absent' => 'red', 'leave' => 'blue'][$att->status] ?? 'zinc'" size="sm">
                                        {{ ['present' => __('Present'), 'late' => __('Late'), 'absent' => __('Absent'), 'leave' => __('On leave')][$att->status] ?? $att->status }}
                                    </flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Not recorded') }}</flux:badge>
                                @endif
                            </div>
                        @empty
                            <flux:text>{{ __('No employees are assigned to this outlet.') }}</flux:text>
                        @endforelse
                    </div>

                    <div class="flex flex-col gap-2 lg:col-span-3">
                        <flux:heading size="sm">{{ __('Goods available (:count products)', ['count' => $detail['stock']->count()]) }}</flux:heading>
                        <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                            <table class="w-full text-start text-sm">
                                <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-medium">{{ __('Product') }}</th>
                                        <th class="px-3 py-2 text-end font-medium">{{ __('Quantity') }}</th>
                                        <th class="px-3 py-2 text-end font-medium">{{ __('Value') }}</th>
                                        <th class="px-3 py-2 text-start font-medium">{{ __('Earliest expiry') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                    @forelse ($detail['stock'] as $item)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <div class="font-medium">{{ $item->name }}</div>
                                                <div class="text-xs text-neutral-500">{{ $item->sku }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-end">{{ $fmt($item->qty) }} {{ $item->unit }}</td>
                                            <td class="px-3 py-2 text-end">{{ $money($item->value) }}</td>
                                            <td class="px-3 py-2">{{ $item->next_expiry ? \Illuminate\Support\Carbon::parse($item->next_expiry)->format('d M Y') : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-6 text-center text-neutral-500">{{ __('No goods at this outlet yet. Move stock here with a stock transfer.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===================== GODOWNS ===================== --}}
        @if ($showGodowns)
            <div class="flex flex-col gap-3">
                <flux:heading size="lg" level="2">{{ __('Godowns at a glance') }}</flux:heading>
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <table class="w-full text-start text-sm">
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium">{{ __('Godown') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Blocks') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Employees') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Products in stock') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('Stock value') }}</th>
                                <th class="px-4 py-3 text-start font-medium">{{ __('How full') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @forelse ($godownRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['blocks'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['employees'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $row['products'] }}</td>
                                    <td class="px-4 py-3 text-end">{{ $money($row['stockValue']) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($row['fill'] !== null)
                                            <div class="h-1.5 w-28 rounded-full bg-neutral-200 dark:bg-neutral-700">
                                                <div class="h-1.5 rounded-full {{ $row['fill'] >= 90 ? 'bg-red-500' : ($row['fill'] >= 70 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $row['fill'] }}%"></div>
                                            </div>
                                            <div class="text-xs text-neutral-500">{{ $row['fill'] }}%</div>
                                        @else
                                            <span class="text-neutral-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No godowns yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <flux:text class="text-xs">{{ __('"How full" is based on the capacity you set for each block. Blocks without a capacity are not counted.') }}</flux:text>
            </div>
        @endif

        {{-- ===================== ATTENTION NEEDED ===================== --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="lg" level="2">{{ __('Expiring soon or expired') }}</flux:heading>
                @forelse ($expiringList as $row)
                    @php $left = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($row->expiry_date)->startOfDay(), false); @endphp
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $row->name }}</div>
                            <div class="text-xs text-neutral-500">
                                {{ __('Batch') }} {{ $row->batch_no }} ·
                                {{ $row->location_type === 'block' ? $row->godown_name.' · '.__('Block').' '.$row->block_code : $row->outlet_name }} ·
                                {{ $fmt($row->quantity) }} {{ $row->unit }}
                            </div>
                        </div>
                        <flux:badge :color="$left < 0 ? 'red' : 'amber'" size="sm">
                            {{ $left < 0 ? __('Expired') : ($left === 0 ? __('Today') : __(':days days', ['days' => $left])) }}
                        </flux:badge>
                    </div>
                @empty
                    <flux:text>{{ __('Nothing is close to expiring. Good.') }}</flux:text>
                @endforelse
            </div>

            @if ($showGodowns)
                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="lg" level="2">{{ __('Running low') }}</flux:heading>
                    @forelse ($lowList as $row)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="font-medium">{{ $row['name'] }}</div>
                            <div class="text-end">
                                <span class="{{ $row['have'] <= 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $fmt($row['have']) }} {{ $row['unit'] }}</span>
                                <span class="text-xs text-neutral-500">/ {{ __('reorder at') }} {{ $fmt($row['reorder']) }}</span>
                            </div>
                        </div>
                    @empty
                        <flux:text>{{ __('Every product is above its reorder level.') }}</flux:text>
                    @endforelse
                </div>
            @elseif ($showSales)
                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="lg" level="2">{{ __('Customers who owe') }}</flux:heading>
                    @forelse ($topDebtors as $debtor)
                        <div class="flex justify-between text-sm"><span class="font-medium">{{ $debtor->name }}</span><span>{{ $money($debtor->balance) }}</span></div>
                    @empty
                        <flux:text>{{ __('No customer owes anything.') }}</flux:text>
                    @endforelse
                </div>
            @endif
        </div>

        {{-- ===================== RECENT ACTIVITY ===================== --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @if ($showSales)
                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="lg" level="2">{{ __('Latest sales') }}</flux:heading>
                    @forelse ($recentSales as $sale)
                        <div class="flex items-start justify-between gap-3 text-sm" wire:key="rs-{{ $sale->id }}">
                            <div>
                                <div class="font-medium">{{ $sale->receipt_no }}</div>
                                <div class="text-xs text-neutral-500">{{ $sale->created_at->format('d M H:i') }} · {{ $sale->outlet?->name }} · {{ $sale->cashier?->name }}</div>
                            </div>
                            <div class="text-end {{ $sale->status === 'voided' ? 'text-red-600 line-through dark:text-red-400' : '' }}">{{ $money($sale->total) }}</div>
                        </div>
                    @empty
                        <flux:text>{{ __('No sales yet.') }}</flux:text>
                    @endforelse
                </div>
            @endif

            <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="lg" level="2">{{ __('Latest stock transfers') }}</flux:heading>
                @forelse ($recentTransfers as $transfer)
                    <div class="flex items-start justify-between gap-3 text-sm" wire:key="rt-{{ $transfer->id }}">
                        <div>
                            <div class="font-medium">{{ $transfer->transfer_no }}</div>
                            <div class="text-xs text-neutral-500">{{ $transfer->fromBlock?->godown?->name ?? '-' }} &rarr; {{ $transferTo[$transfer->id] ?? '-' }}</div>
                        </div>
                        <flux:badge :color="$statusColors[$transfer->status]" size="sm">{{ $statusLabels[$transfer->status] }}</flux:badge>
                    </div>
                @empty
                    <flux:text>{{ __('No transfers yet.') }}</flux:text>
                @endforelse
            </div>
        </div>
    @endif
</div>
