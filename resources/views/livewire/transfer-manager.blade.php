@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $statusLabels = ['pending' => __('Pending'), 'in_transit' => __('In transit'), 'received' => __('Received'), 'cancelled' => __('Cancelled')];
    $statusColors = ['pending' => 'amber', 'in_transit' => 'blue', 'received' => 'green', 'cancelled' => 'zinc'];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Stock transfers') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Move goods between blocks, between godowns, and from a godown to an outlet.') }}</flux:text>
        </div>

        @if ($canCreate)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New transfer') }}</flux:button>
        @endif
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    @if ($error)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    {{-- New transfer --}}
    @if ($showForm)
        <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                    <flux:heading size="sm">{{ __('From') }}</flux:heading>
                    <flux:select wire:model.live="fromGodownId" :label="__('Godown')">
                        <flux:select.option value="">{{ __('Choose a godown') }}</flux:select.option>
                        @foreach ($sourceGodowns as $godown)
                            <flux:select.option value="{{ $godown->id }}">{{ $godown->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="fromBlockId" :label="__('Block')">
                        <flux:select.option value="">{{ $fromGodownId === '' ? __('Pick a godown first') : __('Choose a block') }}</flux:select.option>
                        @foreach ($sourceBlocks as $block)
                            <flux:select.option value="{{ $block->id }}">{{ $block->code }}{{ $block->name ? ' - '.$block->name : '' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex flex-col gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                    <flux:heading size="sm">{{ __('To') }}</flux:heading>
                    <flux:select wire:model.live="toType" :label="__('Destination type')">
                        <flux:select.option value="block">{{ __('A block in a godown') }}</flux:select.option>
                        <flux:select.option value="outlet">{{ __('An outlet') }}</flux:select.option>
                    </flux:select>

                    @if ($toType === 'block')
                        <flux:select wire:model.live="toGodownId" :label="__('Godown')">
                            <flux:select.option value="">{{ __('Choose a godown') }}</flux:select.option>
                            @foreach ($allGodowns as $godown)
                                <flux:select.option value="{{ $godown->id }}">{{ $godown->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="toBlockId" :label="__('Block')">
                            <flux:select.option value="">{{ $toGodownId === '' ? __('Pick a godown first') : __('Choose a block') }}</flux:select.option>
                            @foreach ($destBlocks as $block)
                                <flux:select.option value="{{ $block->id }}">{{ $block->code }}{{ $block->name ? ' - '.$block->name : '' }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:select wire:model="toOutletId" :label="__('Outlet')">
                            <flux:select.option value="">{{ __('Choose an outlet') }}</flux:select.option>
                            @foreach ($outlets as $outlet)
                                <flux:select.option value="{{ $outlet->id }}">{{ $outlet->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>
            </div>

            {{-- Lines --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">{{ __('Goods to move') }}</flux:heading>

                @if ($fromBlockId !== '' && $sourceStock->isEmpty())
                    <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        {{ __('This block has no stock to move.') }}
                    </div>
                @endif

                @foreach ($lines as $i => $line)
                    @php $row = $sourceStock->firstWhere('batch_id', (int) ($line['batchId'] ?: 0)); @endphp
                    <div class="grid grid-cols-1 items-start gap-2 md:grid-cols-12" wire:key="line-{{ $i }}">
                        <div class="md:col-span-8">
                            <flux:select wire:model.live="lines.{{ $i }}.batchId">
                                <flux:select.option value="">{{ $fromBlockId === '' ? __('Pick a source block first') : __('Choose a product and batch') }}</flux:select.option>
                                @foreach ($sourceStock as $stock)
                                    <flux:select.option value="{{ $stock->batch_id }}">
                                        {{ $stock->product_name }} · {{ __('batch') }} {{ $stock->batch_no }}
                                        @if ($stock->expiry_date) · {{ __('exp') }} {{ \Illuminate\Support\Carbon::parse($stock->expiry_date)->format('d M Y') }} @endif
                                        · {{ $fmt($stock->quantity) }} {{ $stock->unit }} {{ __('available') }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-3">
                            <flux:input wire:model="lines.{{ $i }}.quantity" type="number" step="any" min="0" placeholder="{{ __('Quantity') }}" />
                            @if ($row)
                                <div class="mt-1 text-xs text-neutral-500">{{ __('Max') }} {{ $fmt($row->quantity) }} {{ $row->unit }}</div>
                            @endif
                        </div>
                        <div class="md:col-span-1 md:text-end">
                            @if (count($lines) > 1)
                                <flux:button size="sm" variant="ghost" icon="trash" type="button" wire:click="removeLine({{ $i }})" />
                            @endif
                        </div>
                    </div>
                @endforeach

                <div>
                    <flux:button size="sm" icon="plus" type="button" wire:click="addLine">{{ __('Add another product') }}</flux:button>
                </div>
            </div>

            <flux:input wire:model="notes" :label="__('Notes (optional)')" />

            @foreach (['fromGodownId', 'fromBlockId', 'toBlockId', 'toOutletId', 'lines'] as $field)
                @error($field)
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            @endforeach

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Create transfer') }}</flux:button>
                <flux:button type="button" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- Filter --}}
    <div class="w-48">
        <flux:select wire:model.live="statusFilter">
            <flux:select.option value="">{{ __('All transfers') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="in_transit">{{ __('In transit') }}</flux:select.option>
            <flux:select.option value="received">{{ __('Received') }}</flux:select.option>
            <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Transfers list --}}
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Transfer') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('From') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('To') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Items') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Requested by') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($transfers as $transfer)
                    <tr wire:key="transfer-{{ $transfer->id }}" class="{{ $viewTransfer && $viewTransfer->id === $transfer->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $transfer->transfer_no }}</td>
                        <td class="px-4 py-3">{{ $transfer->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $transfer->fromBlock?->godown?->name ?? '-' }} · {{ __('Block') }} {{ $transfer->fromBlock?->code }}</td>
                        <td class="px-4 py-3">{{ $destinations[$transfer->id] ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $transfer->items_count }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$statusColors[$transfer->status]" size="sm">{{ $statusLabels[$transfer->status] }}</flux:badge>
                        </td>
                        <td class="px-4 py-3">{{ $transfer->requester?->name }}</td>
                        <td class="px-4 py-3 text-end">
                            <flux:button size="sm" wire:click="viewTransfer({{ $transfer->id }})">{{ __('Open') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-neutral-500">{{ __('No transfers yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transfers->links() }}</div>

    {{-- Transfer details --}}
    @if ($viewTransfer)
        @php $receiving = $receiveTransferId === $viewTransfer->id; @endphp
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg" level="2">{{ $viewTransfer->transfer_no }}</flux:heading>
                        <flux:badge :color="$statusColors[$viewTransfer->status]" size="sm">{{ $statusLabels[$viewTransfer->status] }}</flux:badge>
                    </div>
                    <flux:text class="mt-1">
                        {{ $viewTransfer->fromBlock?->godown?->name ?? '-' }} · {{ __('Block') }} {{ $viewTransfer->fromBlock?->code }}
                        &rarr; {{ $destinations[$viewTransfer->id] ?? '-' }}
                    </flux:text>
                    <flux:text class="mt-1 text-xs">
                        {{ __('Requested by :name on :date', ['name' => $viewTransfer->requester?->name, 'date' => $viewTransfer->created_at->format('d M Y H:i')]) }}
                        @if ($viewTransfer->dispatched_at)
                            · {{ __('Dispatched by :name on :date', ['name' => $viewTransfer->dispatcher?->name, 'date' => $viewTransfer->dispatched_at->format('d M Y H:i')]) }}
                        @endif
                        @if ($viewTransfer->received_at)
                            · {{ __('Received by :name on :date', ['name' => $viewTransfer->receiver?->name, 'date' => $viewTransfer->received_at->format('d M Y H:i')]) }}
                        @endif
                    </flux:text>
                    @if ($viewTransfer->notes)
                        <flux:text class="mt-1">{{ $viewTransfer->notes }}</flux:text>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($viewTransfer->status === 'pending' && $canDispatch)
                        <flux:button size="sm" variant="primary" icon="truck" wire:click="dispatchTransfer({{ $viewTransfer->id }})" wire:confirm="{{ __('Dispatch this transfer? The stock will leave the source block.') }}">
                            {{ __('Dispatch') }}
                        </flux:button>
                        <flux:button size="sm" wire:click="cancelTransfer({{ $viewTransfer->id }})" wire:confirm="{{ __('Cancel this transfer?') }}">
                            {{ __('Cancel transfer') }}
                        </flux:button>
                    @endif

                    @if ($viewTransfer->status === 'in_transit' && $canReceive && ! $receiving)
                        <flux:button size="sm" variant="primary" icon="inbox-arrow-down" wire:click="startReceive({{ $viewTransfer->id }})">
                            {{ __('Receive') }}
                        </flux:button>
                    @endif

                    <flux:button size="sm" wire:click="closeTransfer">{{ __('Close') }}</flux:button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-start text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Product') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Batch') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Expiry') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Sent') }}</th>
                            @if ($receiving || $viewTransfer->status === 'received')
                                <th class="px-4 py-3 text-end font-medium">{{ __('Received') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($viewTransfer->items as $item)
                            @php $unit = $item->batch?->product?->baseUnit?->abbreviation; @endphp
                            <tr wire:key="titem-{{ $item->id }}">
                                <td class="px-4 py-3 font-medium">{{ $item->batch?->product?->name }}</td>
                                <td class="px-4 py-3">{{ $item->batch?->batch_no }}</td>
                                <td class="px-4 py-3">{{ $item->batch?->expiry_date ? $item->batch->expiry_date->format('d M Y') : '-' }}</td>
                                <td class="px-4 py-3 text-end">{{ $fmt($item->quantity) }} {{ $unit }}</td>
                                @if ($receiving)
                                    <td class="px-4 py-3">
                                        <div class="ms-auto w-32">
                                            <flux:input wire:model="receiveQuantities.{{ $item->id }}" type="number" step="any" min="0" />
                                        </div>
                                    </td>
                                @elseif ($viewTransfer->status === 'received')
                                    <td class="px-4 py-3 text-end {{ (float) $item->received_quantity < (float) $item->quantity ? 'text-red-600 dark:text-red-400' : '' }}">
                                        {{ $fmt($item->received_quantity) }} {{ $unit }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($receiving)
                @if ($capacityWarning)
                    <div class="flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        <div>{{ $capacityWarning }}</div>
                        <flux:checkbox wire:model="overrideCapacity" :label="__('Receive anyway')" />
                    </div>
                @endif

                @foreach ($viewTransfer->items as $item)
                    @error('receiveQuantities.'.$item->id)
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                @endforeach

                <flux:text class="text-xs">{{ __('If less arrived than was sent, change the received quantity. The difference is not added to stock.') }}</flux:text>

                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="confirmReceive">{{ __('Confirm received') }}</flux:button>
                    <flux:button wire:click="cancelReceive">{{ __('Cancel') }}</flux:button>
                </div>
            @endif
        </div>
    @endif
</div>
