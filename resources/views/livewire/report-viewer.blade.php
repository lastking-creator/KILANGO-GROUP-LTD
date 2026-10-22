<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Choose a report, then print it or save it as a PDF or Excel file.') }}</flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-72">
            <flux:select wire:model.live="type" :label="__('Report')">
                @foreach ($available as $key => $item)
                    <flux:select.option value="{{ $key }}">{{ __($item['label']) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if (! empty($meta['range']))
            <div class="w-44"><flux:input wire:model.live="from" type="date" :label="__('From')" /></div>
            <div class="w-44"><flux:input wire:model.live="to" type="date" :label="__('To')" /></div>
        @endif

        @if (! empty($meta['outlet']) && $outlets->isNotEmpty())
            <div class="w-52">
                <flux:select wire:model.live="outletId" :label="__('Outlet')">
                    <flux:select.option value="">{{ __('All outlets') }}</flux:select.option>
                    @foreach ($outlets as $o)
                        <flux:select.option value="{{ $o->id }}">{{ $o->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        @if (! empty($meta['days']))
            <div class="w-44">
                <flux:input wire:model.live.debounce.400ms="days" type="number" min="1" max="365" :label="__('Expiring within (days)')" />
            </div>
        @endif
    </div>

    <div class="flex flex-col gap-1">
        <flux:heading size="lg" level="2">{{ __($report['title']) }}</flux:heading>
        <flux:text>{{ $report['subtitle'] }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button size="sm" icon="printer" href="{{ route('reports.export', ['type' => $type, 'format' => 'print'] + $query) }}" target="_blank">{{ __('Print') }}</flux:button>
        <flux:button size="sm" icon="arrow-down-tray" href="{{ route('reports.export', ['type' => $type, 'format' => 'pdf'] + $query) }}">{{ __('Download PDF') }}</flux:button>
        <flux:button size="sm" icon="table-cells" href="{{ route('reports.export', ['type' => $type, 'format' => 'xlsx'] + $query) }}">{{ __('Export Excel') }}</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-start text-sm">
            <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    @foreach ($report['columns'] as $col)
                        <th class="px-4 py-3 font-medium {{ in_array($col['type'], ['money', 'qty', 'int'], true) ? 'text-end' : 'text-start' }}">{{ __($col['label']) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($report['rows'] as $row)
                    <tr>
                        @foreach ($report['columns'] as $col)
                            <td class="px-4 py-3 {{ in_array($col['type'], ['money', 'qty', 'int'], true) ? 'text-end' : '' }}">
                                {{ \App\Services\ReportBuilder::format($col['type'], $row[$col['key']] ?? null) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($report['columns']) }}" class="px-4 py-6 text-center text-neutral-500">{{ __('Nothing to show for these filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($report['totals'])
                <tfoot>
                    <tr class="bg-neutral-50 font-medium dark:bg-neutral-900">
                        @foreach ($report['columns'] as $col)
                            <td class="px-4 py-3 {{ in_array($col['type'], ['money', 'qty', 'int'], true) ? 'text-end' : '' }}">
                                @if (array_key_exists($col['key'], $report['totals']))
                                    {{ \App\Services\ReportBuilder::format($col['type'], $report['totals'][$col['key']]) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if ($report['note'])
        <flux:text class="text-xs">{{ $report['note'] }}</flux:text>
    @endif
</div>
