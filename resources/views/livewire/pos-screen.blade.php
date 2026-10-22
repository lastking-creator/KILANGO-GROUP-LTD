@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $methodLabels = ['cash' => __('Cash'), 'mobile_money' => __('Mobile money'), 'card' => __('Card'), 'on_account' => __('On account')];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Point of sale') }}</flux:heading>
            <flux:text class="mt-1">
                @if ($outlet)
                    {{ $outlet->name }}
                    @if ($shift) · {{ __('Shift open since :time', ['time' => $shift->opened_at->format('d M H:i')]) }} @endif
                @else
                    {{ __('Sell from an outlet to its customers.') }}
                @endif
            </flux:text>
        </div>

        <div class="flex items-end gap-3">
            @if ($outlets->isNotEmpty())
                <div class="w-52">
                    <flux:select wire:model.live="outletId" :label="__('Outlet')">
                        <flux:select.option value="">{{ __('Choose an outlet') }}</flux:select.option>
                        @foreach ($outlets as $o)
                            <flux:select.option value="{{ $o->id }}">{{ $o->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            @if ($shift && ! $showCloseShift)
                <flux:button wire:click="startCloseShift">{{ __('Close shift') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
            @if ($lastSale)
                <a href="{{ route('sales.receipt', $lastSale) }}" target="_blank" class="ms-2 font-medium underline">{{ __('Print receipt') }}</a>
            @endif
        </div>
    @endif

    @if ($error)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    @if ($staffWithoutOutlet)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            {{ __('Your account is not assigned to an outlet yet. Ask an admin to assign you to one.') }}
        </div>
    @elseif (! $outlet)
        <div class="rounded-lg border border-neutral-200 px-4 py-6 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Choose an outlet to begin.') }}
        </div>
    @elseif (! $shift)
        {{-- Open a shift --}}
        <form wire:submit="openShift" class="flex max-w-md flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:heading size="lg" level="2">{{ __('Open your shift') }}</flux:heading>
            <flux:text>{{ __('Count the cash in the drawer and enter it. This is used to check the cash when you close the shift.') }}</flux:text>
            <flux:input wire:model="openingCash" type="number" step="any" min="0" :label="__('Cash in the drawer')" placeholder="0" />
            <div>
                <flux:button type="submit" variant="primary">{{ __('Open shift') }}</flux:button>
            </div>
        </form>
    @else
        {{-- Close shift --}}
        @if ($showCloseShift)
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="lg" level="2">{{ __('Close your shift') }}</flux:heading>

                <div class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>{{ __('Sales') }}: <strong>{{ $shiftTotals['count'] }}</strong></div>
                    <div>{{ __('Sales total') }}: <strong>{{ $money($shiftTotals['total']) }}</strong></div>
                    @foreach ($methodLabels as $key => $label)
                        <div>{{ $label }}: <strong>{{ $money($shiftTotals['byMethod'][$key] ?? 0) }}</strong></div>
                    @endforeach
                    <div>{{ __('Opening cash') }}: <strong>{{ $money($shift->opening_cash) }}</strong></div>
                </div>

                <div class="rounded-lg bg-neutral-50 px-4 py-3 text-sm dark:bg-neutral-900">
                    {{ __('Cash that should be in the drawer') }}: <strong>{{ $money($shiftTotals['expectedCash']) }}</strong>
                </div>

                <form wire:submit="confirmCloseShift" class="flex flex-col gap-3">
                    <div class="max-w-xs">
                        <flux:input wire:model="countedCash" type="number" step="any" min="0" :label="__('Cash you counted')" />
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Close shift') }}</flux:button>
                        <flux:button type="button" wire:click="cancelCloseShift">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Products --}}
            <div class="flex flex-col gap-4 lg:col-span-3">
                <flux:input wire:model.live.debounce.250ms="search" wire:keydown.enter="addByCode" icon="magnifying-glass"
                    placeholder="{{ __('Search a product, or scan a barcode and press Enter') }}" autofocus />

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($products as $product)
                        <button type="button" wire:key="tile-{{ $product->id }}" wire:click="addToCart({{ $product->id }})"
                            class="flex flex-col items-start gap-1 rounded-xl border border-neutral-200 p-3 text-start transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-900">
                            <span class="font-medium">{{ $product->name }}</span>
                            <span class="text-sm">{{ $money($product->selling_price) }}</span>
                            <span class="text-xs text-neutral-500">{{ $fmt($availability[$product->id] ?? 0) }} {{ $product->baseUnit?->abbreviation }} {{ __('in stock') }}</span>
                        </button>
                    @empty
                        <div class="col-span-full rounded-lg border border-neutral-200 px-4 py-6 text-center text-neutral-500 dark:border-neutral-700">
                            {{ __('No products with stock at this outlet. Move stock here from a godown with a stock transfer.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Cart and payment --}}
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" level="2">{{ __('Cart') }}</flux:heading>
                    @if ($cart)
                        <flux:button size="sm" variant="ghost" wire:click="clearCart">{{ __('Clear') }}</flux:button>
                    @endif
                </div>

                @forelse ($cart as $key => $line)
                    <div class="flex flex-col gap-1 border-b border-neutral-200 pb-3 dark:border-neutral-700" wire:key="cart-{{ $key }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-medium">{{ $line['name'] }}</div>
                                <div class="text-xs text-neutral-500">{{ $money($line['price']) }} / {{ $line['unit'] }}</div>
                            </div>
                            <div class="text-end font-medium">{{ $money((float) $line['price'] * (is_numeric($line['quantity']) ? (float) $line['quantity'] : 0)) }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" icon="minus" wire:click="decrement('{{ $key }}')" />
                            <div class="w-24">
                                <flux:input wire:model.live.debounce.500ms="cart.{{ $key }}.quantity" type="number" step="any" min="0" />
                            </div>
                            <flux:button size="sm" icon="plus" wire:click="increment('{{ $key }}')" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeFromCart('{{ $key }}')" />
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('Tap a product to add it.') }}</flux:text>
                @endforelse

                @if ($cart)
                    <div class="flex flex-col gap-1 text-sm">
                        <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ $money($totals['subtotal']) }}</span></div>
                        @if ($isManager)
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ __('Discount') }}</span>
                                <div class="w-32"><flux:input wire:model.live.debounce.400ms="discount" type="number" step="any" min="0" placeholder="0" /></div>
                            </div>
                        @endif
                        <div class="flex justify-between text-lg font-semibold"><span>{{ __('Total') }}</span><span>{{ $money($totals['total']) }}</span></div>
                    </div>

                    {{-- Payment --}}
                    <div class="flex flex-col gap-3 rounded-lg bg-neutral-50 p-3 dark:bg-neutral-900">
                        <flux:heading size="sm">{{ __('Payment') }}</flux:heading>

                        <div class="grid grid-cols-3 items-end gap-2">
                            <div class="col-span-2">
                                <flux:input wire:model.live.debounce.400ms="cashTendered" type="number" step="any" min="0" :label="__('Cash received')" />
                            </div>
                            <flux:button size="sm" wire:click="payExact">{{ __('Exact') }}</flux:button>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <flux:input wire:model.live.debounce.400ms="mobileAmount" type="number" step="any" min="0" :label="__('Mobile money')" />
                            <flux:input wire:model="mobileRef" :label="__('Reference')" />
                            <flux:input wire:model.live.debounce.400ms="cardAmount" type="number" step="any" min="0" :label="__('Card')" />
                            <flux:input wire:model="cardRef" :label="__('Reference')" />
                        </div>

                        <div class="grid grid-cols-3 items-end gap-2">
                            <div class="col-span-2">
                                <flux:input wire:model.live.debounce.400ms="accountAmount" type="number" step="any" min="0" :label="__('On account (debit)')" />
                            </div>
                            <flux:button size="sm" wire:click="putBalanceOnAccount">{{ __('Balance') }}</flux:button>
                        </div>

                        <div class="flex flex-col gap-2">
                            <flux:select wire:model.live="customerId" :label="__('Customer (needed for account sales)')">
                                <flux:select.option value="">{{ __('No customer') }}</flux:select.option>
                                @foreach ($customers as $c)
                                    <flux:select.option value="{{ $c->id }}">{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            @if ($customer)
                                <div class="text-xs text-neutral-500">
                                    {{ __('Owes') }} {{ $money($customerOwes) }} · {{ __('credit limit') }} {{ $money($customer->credit_limit) }}
                                </div>
                            @endif

                            @if ($isManager)
                                @if ($showNewCustomer)
                                    <div class="grid gap-2 rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
                                        <flux:input wire:model="newCustomerName" :label="__('Name')" />
                                        <flux:input wire:model="newCustomerPhone" :label="__('Phone')" />
                                        <flux:input wire:model="newCustomerLimit" type="number" step="any" min="0" :label="__('Credit limit')" />
                                        <div class="flex gap-2">
                                            <flux:button size="sm" variant="primary" wire:click="saveNewCustomer">{{ __('Save customer') }}</flux:button>
                                            <flux:button size="sm" wire:click="$set('showNewCustomer', false)">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </div>
                                @else
                                    <div>
                                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="$set('showNewCustomer', true)">{{ __('New customer') }}</flux:button>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <div class="flex flex-col gap-1 text-sm">
                            @if ($totals['over'])
                                <div class="text-red-600">{{ __('Mobile money, card and account add up to more than the total.') }}</div>
                            @elseif ($totals['short'] > 0)
                                <div class="text-amber-600 dark:text-amber-400">{{ __('Still to pay') }}: <strong>{{ $money($totals['short']) }}</strong></div>
                            @else
                                <div class="text-green-600 dark:text-green-400">{{ __('Change to give') }}: <strong>{{ $money($totals['change']) }}</strong></div>
                            @endif
                        </div>
                    </div>

                    @foreach (['discount', 'cashTendered', 'mobileAmount', 'cardAmount', 'accountAmount'] as $field)
                        @error($field)
                            <div class="text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    @endforeach

                    <flux:button variant="primary" class="w-full" wire:click="completeSale" wire:loading.attr="disabled" wire:target="completeSale">
                        {{ __('Complete sale') }}
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>
