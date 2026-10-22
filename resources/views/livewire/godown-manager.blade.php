@php
    $storageLabels = ['dry' => __('Dry store'), 'cold' => __('Cold room'), 'freezer' => __('Freezer')];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Godowns') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your godowns and the blocks inside each one.') }}</flux:text>
        </div>

        @if ($isAdmin)
            <flux:button variant="primary" icon="plus" wire:click="createGodown">{{ __('Add godown') }}</flux:button>
        @endif
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    {{-- Godown form --}}
    @if ($showGodownForm)
        <form wire:submit="saveGodown" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-3">
            <flux:input wire:model="code" :label="__('Code')" placeholder="G1" />
            <flux:input wire:model="name" :label="__('Name')" />
            <flux:input wire:model="location" :label="__('Location')" />

            <div class="flex gap-2 md:col-span-3">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancelGodown">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- Godowns list --}}
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Location') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Blocks') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($godowns as $godown)
                    <tr wire:key="godown-{{ $godown->id }}" class="{{ $selectedGodownId === $godown->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $godown->code }}</td>
                        <td class="px-4 py-3">{{ $godown->name }}</td>
                        <td class="px-4 py-3">{{ $godown->location ?: '-' }}</td>
                        <td class="px-4 py-3">{{ $godown->blocks_count }}</td>
                        <td class="px-4 py-3">
                            @if ($godown->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" wire:click="selectGodown({{ $godown->id }})">{{ __('Blocks') }}</flux:button>
                                @if ($isAdmin)
                                    <flux:button size="sm" variant="ghost" wire:click="editGodown({{ $godown->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleGodown({{ $godown->id }})">
                                        {{ $godown->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No godowns yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Blocks panel --}}
    @if ($selected)
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg" level="2">{{ __('Blocks in :name', ['name' => $selected->name]) }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Capacity, storage type and how full each block is. Open a block to see the products in it.') }}</flux:text>
                </div>
                <div class="flex gap-2">
                    @if ($isAdmin)
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="createBlock">{{ __('Add block') }}</flux:button>
                    @endif
                    <flux:button size="sm" wire:click="closeBlocks">{{ __('Close') }}</flux:button>
                </div>
            </div>

            @if ($showBlockForm)
                <form wire:submit="saveBlock" class="grid gap-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-3">
                    <flux:input wire:model="blockCode" :label="__('Code')" placeholder="A" />
                    <flux:input wire:model="blockName" :label="__('Name')" />
                    <flux:select wire:model="storageType" :label="__('Storage type')">
                        <flux:select.option value="dry">{{ __('Dry store') }}</flux:select.option>
                        <flux:select.option value="cold">{{ __('Cold room') }}</flux:select.option>
                        <flux:select.option value="freezer">{{ __('Freezer') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="capacity" type="number" step="any" min="0" :label="__('Capacity')" placeholder="500" />
                    <flux:input wire:model="capacityUnit" :label="__('Capacity unit')" placeholder="bags" />

                    <div class="flex gap-2 md:col-span-3">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" wire:click="cancelBlock">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif

            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-start text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Block') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Storage type') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Capacity') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('In stock') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Available space') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse ($selected->blocks as $block)
                            @php
                                $inBlock = (float) ($used[$block->id] ?? 0);
                                $cap = $block->capacity === null ? null : (float) $block->capacity;
                                $unit = $block->capacity_unit;
                                $percent = $cap && $cap > 0 ? min(100, round($inBlock / $cap * 100)) : null;
                                $free = $cap === null ? null : max(0, $cap - $inBlock);
                            @endphp
                            <tr wire:key="block-{{ $block->id }}" class="{{ $viewBlockId === $block->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $block->code }}</div>
                                    @if ($block->name)
                                        <div class="text-xs text-neutral-500">{{ $block->name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $storageLabels[$block->storage_type] ?? $block->storage_type }}</td>
                                <td class="px-4 py-3">
                                    {{ $cap === null ? '-' : $fmt($cap).($unit ? ' '.$unit : '') }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $fmt($inBlock) }}{{ $unit ? ' '.$unit : '' }}
                                    @if ($percent !== null)
                                        <div class="mt-1 h-1.5 w-28 rounded-full bg-neutral-200 dark:bg-neutral-700">
                                            <div class="h-1.5 rounded-full {{ $percent >= 90 ? 'bg-red-500' : ($percent >= 70 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $percent }}%"></div>
                                        </div>
                                        <div class="text-xs text-neutral-500">{{ $percent }}% {{ __('full') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $free === null ? '-' : $fmt($free).($unit ? ' '.$unit : '') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($block->is_active)
                                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" wire:click="viewBlockStock({{ $block->id }})">{{ __('Products') }}</flux:button>
                                        @if ($isAdmin)
                                            <flux:button size="sm" variant="ghost" wire:click="editBlock({{ $block->id }})">{{ __('Edit') }}</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="toggleBlock({{ $block->id }})">
                                                {{ $block->is_active ? __('Deactivate') : __('Activate') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-neutral-500">{{ __('No blocks in this godown yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Products in the chosen block --}}
            @if ($viewBlock)
                @php
                    $blockUnit = $viewBlock->capacity_unit ? ' '.$viewBlock->capacity_unit : '';
                    $shareTitle = __('Stock report').' - '.$selected->name.' - '.__('Block').' '.$viewBlock->code;
                    $shareLines = [$shareTitle, ''];
                    foreach ($stockRows as $row) {
                        $shareLines[] = '- '.$row->name.': '.$fmt($row->quantity).' '.$row->unit;
                    }
                    $shareLines[] = '';
                    $shareLines[] = __('Total in stock').': '.$fmt($used[$viewBlock->id] ?? 0).$blockUnit;
                    if ($viewBlock->capacity !== null) {
                        $shareLines[] = __('Capacity').': '.$fmt($viewBlock->capacity).$blockUnit;
                    }
                    $shareText = implode("\n", $shareLines);
                @endphp
                <div class="flex flex-col gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <flux:heading size="lg" level="3">
                                {{ __('Products in block :code', ['code' => $viewBlock->code]) }}
                            </flux:heading>
                            <flux:text class="mt-1">
                                {{ __('Quantity available for each product, with all its batches added together.') }}
                            </flux:text>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <flux:button size="sm" icon="printer" href="{{ route('blocks.stock.print', $viewBlock) }}" target="_blank">{{ __('Print') }}</flux:button>
                            <flux:button size="sm" icon="arrow-down-tray" href="{{ route('blocks.stock.pdf', $viewBlock) }}">{{ __('Download PDF') }}</flux:button>
                            <flux:button size="sm" icon="table-cells" href="{{ route('blocks.stock.xlsx', $viewBlock) }}">{{ __('Export Excel') }}</flux:button>

                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" icon="share" icon-trailing="chevron-down">{{ __('Share') }}</flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="chat-bubble-left-right" href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank">
                                        {{ __('WhatsApp') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="envelope" href="mailto:?subject={{ rawurlencode($shareTitle) }}&body={{ rawurlencode($shareText) }}">
                                        {{ __('Email') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="clipboard-document" x-on:click="navigator.clipboard.writeText(@js($shareText))">
                                        {{ __('Copy summary') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="share" x-on:click="navigator.share ? navigator.share({ title: @js($shareTitle), text: @js($shareText) }) : navigator.clipboard.writeText(@js($shareText))">
                                        {{ __('Share to other apps') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>

                            <flux:button size="sm" wire:click="closeBlockStock">{{ __('Close') }}</flux:button>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <table class="w-full text-start text-sm">
                            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('Product') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('SKU') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('Batches') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('Quantity available') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('Earliest expiry') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                @forelse ($stockRows as $row)
                                    <tr wire:key="stock-{{ $viewBlock->id }}-{{ $row->id }}">
                                        <td class="px-4 py-3 font-medium">{{ $row->name }}</td>
                                        <td class="px-4 py-3">{{ $row->sku }}</td>
                                        <td class="px-4 py-3">{{ $row->batches }}</td>
                                        <td class="px-4 py-3">{{ $fmt($row->quantity) }} {{ $row->unit }}</td>
                                        <td class="px-4 py-3">
                                            @if ($row->next_expiry)
                                                {{ \Illuminate\Support\Carbon::parse($row->next_expiry)->format('d M Y') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-neutral-500">{{ __('No products in this block yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
