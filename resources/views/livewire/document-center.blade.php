<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Print center') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Find any document and print it or save it as a PDF. Each one carries your company letterhead.') }}</flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-72">
            <flux:select wire:model.live="type" :label="__('Document')">
                @foreach ($available as $key => $item)
                    <flux:select.option value="{{ $key }}">{{ __($item['label']) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-44"><flux:input wire:model.live="from" type="date" :label="__('From')" /></div>
        <div class="w-44"><flux:input wire:model.live="to" type="date" :label="__('To')" /></div>
        <div class="w-56"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" /></div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    @foreach ($table['columns'] as $label)
                        <th class="px-4 py-3 text-start font-medium">{{ __($label) }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($table['rows'] as $row)
                    <tr wire:key="doc-{{ $type }}-{{ $row['id'] }}">
                        @foreach ($row['cells'] as $i => $cell)
                            <td class="px-4 py-3 {{ $i === 0 ? 'font-medium' : '' }}">{{ $cell }}</td>
                        @endforeach
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" icon="printer" href="{{ route('documents.show', ['type' => $type, 'id' => $row['id'], 'format' => 'print']) }}" target="_blank">{{ __('Print') }}</flux:button>
                                <flux:button size="sm" icon="arrow-down-tray" href="{{ route('documents.show', ['type' => $type, 'id' => $row['id'], 'format' => 'pdf']) }}">{{ __('PDF') }}</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($table['columns']) + 1 }}" class="px-4 py-6 text-center text-neutral-500">{{ __('No documents found for these filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:text class="text-xs">{{ __('Showing the latest 50. Narrow the dates or search to find older ones. Cash handover notes appear after a shift is closed.') }}</flux:text>
</div>
