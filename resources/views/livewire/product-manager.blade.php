@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $money = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $storageLabels = ['dry' => __('Dry store'), 'cold' => __('Cold room'), 'freezer' => __('Freezer')];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Inventory') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your products, how much of each is in stock, and where it is.') }}</flux:text>
        </div>

        @if ($canEdit)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add product') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:badge color="zinc">{{ __('Active products') }}: {{ $activeCount }}</flux:badge>
        <flux:badge color="amber">{{ __('Low stock') }}: {{ $lowCount }}</flux:badge>
        <flux:badge color="red">{{ __('Out of stock') }}: {{ $outCount }}</flux:badge>
    </div>

    @if ($message)
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ $message }}
        </div>
    @endif

    {{-- Product form --}}
    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:grid-cols-3">
            <flux:input wire:model="sku" :label="__('SKU (product code)')" />
            <flux:input wire:model="barcode" :label="__('Barcode (optional)')" />
            <flux:input wire:model="name" :label="__('Product name')" placeholder="{{ __('e.g. Rice 50kg') }}" />

            <div class="flex flex-col gap-2">
                <flux:select wire:model="categoryId" :label="__('Category')">
                    <flux:select.option value="">{{ __('No category') }}</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="newCategory" placeholder="{{ __('...or type a new category') }}" />
            </div>

            <div class="flex flex-col gap-2">
                <flux:select wire:model="unitId" :label="__('Unit stock is counted in')">
                    <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                    @foreach ($units as $unit)
                        <flux:select.option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->abbreviation }})</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2">
                        <flux:input wire:model="newUnit" placeholder="{{ __('...or type a new unit') }}" />
                    </div>
                    <flux:input wire:model="newUnitAbbr" placeholder="{{ __('Short') }}" />
                </div>
                <flux:text class="text-xs">{{ __('The unit cannot be changed once the product has stock.') }}</flux:text>
            </div>

            <div class="flex flex-col gap-2">
                <flux:select wire:model="storageType" :label="__('Stored in')">
                    <flux:select.option value="dry">{{ __('Dry store') }}</flux:select.option>
                    <flux:select.option value="cold">{{ __('Cold room') }}</flux:select.option>
                    <flux:select.option value="freezer">{{ __('Freezer') }}</flux:select.option>
                </flux:select>
                <flux:checkbox wire:model="isPerishable" :label="__('Perishable (has an expiry date)')" />
            </div>

            <flux:input wire:model="costPrice" type="number" step="any" min="0" :label="__('Cost price')" />
            <flux:input wire:model="sellingPrice" type="number" step="any" min="0" :label="__('Selling price')" />
            <flux:input wire:model="reorderLevel" type="number" step="any" min="0" :label="__('Reorder level (warn when stock falls to this)')" />

            <div class="flex gap-2 md:col-span-3">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search name, SKU or barcode') }}" />
        </div>
        <div class="w-52">
            <flux:select wire:model.live="categoryFilter">
                <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-40">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            </flux:select>
        </div>
        <flux:checkbox wire:model.live="lowOnly" :label="__('Low stock only')" />
    </div>

    {{-- Products --}}
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Product') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Category') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Stored in') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Selling price') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('In stock') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Reorder level') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($products as $product)
                    @php
                        $total = (float) ($totals[$product->id] ?? 0);
                        $reorder = (float) $product->reorder_level;
                    @endphp
                    <tr wire:key="product-{{ $product->id }}" class="{{ $viewProduct && $viewProduct->id === $product->id ? 'bg-neutral-50 dark:bg-neutral-900' : '' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $product->name }}</div>
                            <div class="text-xs text-neutral-500">{{ $product->sku }}@if ($product->barcode) · {{ $product->barcode }}@endif</div>
                        </td>
                        <td class="px-4 py-3">{{ $product->category?->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $storageLabels[$product->storage_type] ?? $product->storage_type }}</td>
                        <td class="px-4 py-3 text-end">{{ $money($product->selling_price) }}</td>
                        <td class="px-4 py-3">
                            <div>{{ $fmt($total) }} {{ $product->baseUnit?->abbreviation }}</div>
                            @if ($product->is_active)
                                @if ($total <= 0)
                                    <flux:badge color="red" size="sm">{{ __('Out of stock') }}</flux:badge>
                                @elseif ($reorder > 0 && $total <= $reorder)
                                    <flux:badge color="amber" size="sm">{{ __('Low stock') }}</flux:badge>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $reorder > 0 ? $fmt($reorder).' '.$product->baseUnit?->abbreviation : '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($product->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" wire:click="viewStock({{ $product->id }})">{{ __('Where') }}</flux:button>
                                @if ($canEdit)
                                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $product->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggle({{ $product->id }})">
                                        {{ $product->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-neutral-500">{{ __('No products found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $products->links() }}</div>

    {{-- Where is this product? --}}
    @if ($viewProduct)
        <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg" level="2">{{ __('Where :name is stored', ['name' => $viewProduct->name]) }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Each batch with its location, quantity and expiry date. Soonest expiry first.') }}</flux:text>
                </div>
                <flux:button size="sm" wire:click="closeStock">{{ __('Close') }}</flux:button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="w-full text-start text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Location') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Batch') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Expiry') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ __('Quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse ($locations as $row)
                            @php
                                $expiry = $row->expiry_date ? \Illuminate\Support\Carbon::parse($row->expiry_date) : null;
                                $expiryClass = $expiry
                                    ? ($expiry->isPast() ? 'text-red-600 dark:text-red-400' : ($expiry->lte(now()->addDays(30)) ? 'text-amber-600 dark:text-amber-400' : ''))
                                    : '';
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    @if ($row->location_type === 'block')
                                        {{ $row->godown_name }} · {{ __('Block') }} {{ $row->block_code }}
                                    @else
                                        {{ __('Outlet') }}: {{ $row->outlet_name }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row->batch_no }}</td>
                                <td class="px-4 py-3 {{ $expiryClass }}">{{ $expiry ? $expiry->format('d M Y') : '-' }}</td>
                                <td class="px-4 py-3 text-end">{{ $fmt($row->quantity) }} {{ $viewProduct->baseUnit?->abbreviation }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-neutral-500">{{ __('This product has no stock anywhere yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
