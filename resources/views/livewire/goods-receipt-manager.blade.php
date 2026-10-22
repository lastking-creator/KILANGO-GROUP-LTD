@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $money = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $productsById = $products->keyBy('id');
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg" level="2">{{ __('Goods received') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Record goods arriving from a supplier. They are added to stock in the block you choose.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Receive goods') }}</flux:button>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    {{-- New receipt --}}
    @if ($showForm)
        <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="grid gap-4 md:grid-cols-4">
                <flux:select wire:model.live="godownId" :label="__('Receive into godown')">
                    <flux:select.option value="">{{ __('Choose a godown') }}</flux:select.option>
                    @foreach ($godowns as $godown)
                        <flux:select.option value="{{ $godown->id }}">{{ $godown->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="supplierId" :label="__('Supplier (optional)')">
                    <flux:select.option value="">{{ __('No supplier') }}</flux:select.option>
                    @foreach ($suppliers as $supplier)
                        <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="receivedDate" type="date" :label="__('Date received')" />
                <flux:input wire:model="reference" :label="__('Invoice / delivery note no.')" />
            </div>

            {{-- Lines --}}
            <div class="flex flex-col gap-3">
                <div class="hidden grid-cols-12 gap-2 text-xs font-medium text-neutral-500 md:grid">
                    <div class="col-span-3">{{ __('Product') }}</div>
                    <div class="col-span-2">{{ __('Block') }}</div>
                    <div class="col-span-2">{{ __('Batch number') }}</div>
                    <div class="col-span-2">{{ __('Expiry date') }}</div>
                    <div class="col-span-1">{{ __('Quantity') }}</div>
                    <div class="col-span-1">{{ __('Unit cost') }}</div>
                    <div class="col-span-1"></div>
                </div>

                @foreach ($lines as $i => $line)
                    @php $lineProduct = $productsById->get((int) ($line['productId'] ?: 0)); @endphp
                    <div class="grid grid-cols-1 items-start gap-2 md:grid-cols-12" wire:key="line-{{ $i }}">
                        <div class="md:col-span-3">
                            <flux:select wire:model.live="lines.{{ $i }}.productId">
                                <flux:select.option value="">{{ __('Choose a product') }}</flux:select.option>
                                @foreach ($products as $product)
                                    <flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-2">
                            <flux:select wire:model="lines.{{ $i }}.blockId">
                                <flux:select.option value="">{{ $godownId === '' ? __('Pick a godown first') : __('Choose a block') }}</flux:select.option>
                                @foreach ($blocks as $block)
                                    <flux:select.option value="{{ $block->id }}">{{ $block->code }}{{ $block->name ? ' - '.$block->name : '' }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model="lines.{{ $i }}.batchNo" placeholder="{{ __('Batch') }}" />
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model="lines.{{ $i }}.expiry" type="date" />
                        </div>
                        <div class="md:col-span-1">
                            <flux:input wire:model="lines.{{ $i }}.quantity" type="number" step="any" min="0" placeholder="0" />
                            @if ($lineProduct)
                                <div class="mt-1 text-xs text-neutral-500">{{ $lineProduct->baseUnit?->abbreviation }}</div>
                            @endif
                        </div>
                        <div class="md:col-span-1">
                            <flux:input wire:model="lines.{{ $i }}.unitCost" type="number" step="any" min="0" placeholder="0" />
                        </div>
                        <div class="md:col-span-1 md:text-end">
                            @if (count($lines) > 1)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $i }})" />
                            @endif
                        </div>
                    </div>
                @endforeach

                <div>
                    <flux:button size="sm" icon="plus" type="button" wire:click="addLine">{{ __('Add another product') }}</flux:button>
                </div>
            </div>

            <flux:input wire:model="notes" :label="__('Notes (optional)')" />

            @if ($capacityWarning)
                <div class="flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <div>{{ $capacityWarning }}</div>
                    <flux:checkbox wire:model="overrideCapacity" :label="__('Receive anyway')" />
                </div>
            @endif

            @if ($errors->has('lines') || $errors->has('godownId'))
                <div class="text-sm text-red-600">{{ $errors->first('lines') ?: $errors->first('godownId') }}</div>
            @endif

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Add to stock') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- Receipts list --}}
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Receipt') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Supplier') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Godown') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Invoice no.') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Total cost') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Received by') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($receipts as $receipt)
                    <tr wire:key="receipt-{{ $receipt->id }}" class="{{ $viewReceipt && $viewReceipt->id === $receipt->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $receipt->receipt_no }}</td>
                        <td class="px-4 py-3">{{ $receipt->received_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $receipt->supplier?->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $receipt->godown?->name }}</td>
                        <td class="px-4 py-3">{{ $receipt->reference ?: '-' }}</td>
                        <td class="px-4 py-3 text-end">{{ $money($receipt->total_cost) }}</td>
                        <td class="px-4 py-3">{{ $receipt->receiver?->name }}</td>
                        <td class="px-4 py-3 text-end">
                            <flux:button size="sm" wire:click="viewReceipt({{ $receipt->id }})">{{ __('View') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-neutral-500">{{ __('No goods received yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $receipts->links() }}</div>

    {{-- Receipt details --}}
    @if ($viewReceipt)
        <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg" level="3">{{ $viewReceipt->receipt_no }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $viewReceipt->received_date->format('d M Y') }} · {{ $viewReceipt->godown?->name }}
                        @if ($viewReceipt->supplier) · {{ $viewReceipt->supplier->name }} @endif
                        @if ($viewReceipt->reference) · {{ __('Invoice') }} {{ $viewReceipt->reference }} @endif
                    </flux:text>
                    @if ($viewReceipt->notes)
                        <flux:text class="mt-1">{{ $viewReceipt->notes }}</flux:text>
                    @endif
                </div>
                <flux:button size="sm" wire:click="closeReceipt">{{ __('Close') }}</flux:button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-start text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Product') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Block') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Batch') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Expiry') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Quantity') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Unit cost') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($viewReceipt->items as $item)
                            <tr wire:key="item-{{ $item->id }}">
                                <td class="px-4 py-3 font-medium">{{ $item->batch?->product?->name }}</td>
                                <td class="px-4 py-3">{{ $item->block?->code }}</td>
                                <td class="px-4 py-3">{{ $item->batch?->batch_no }}</td>
                                <td class="px-4 py-3">{{ $item->batch?->expiry_date ? $item->batch->expiry_date->format('d M Y') : '-' }}</td>
                                <td class="px-4 py-3 text-end">{{ $fmt($item->quantity) }} {{ $item->batch?->product?->baseUnit?->abbreviation }}</td>
                                <td class="px-4 py-3 text-end">{{ $money($item->unit_cost) }}</td>
                                <td class="px-4 py-3 text-end">{{ $money($item->quantity * $item->unit_cost) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-neutral-50 font-medium dark:bg-neutral-900">
                            <td colspan="6" class="px-4 py-3">{{ __('Total cost') }}</td>
                            <td class="px-4 py-3 text-end">{{ $money($viewReceipt->total_cost) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
