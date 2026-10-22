@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $storageLabels = ['dry' => 'Dry store', 'cold' => 'Cold room', 'freezer' => 'Freezer'];
    $unitText = $unit ? ' '.$unit : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock report - {{ $godown->name }} - Block {{ $block->code }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; }
        th { background: #eee; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #f6f6f6; }
        .summary td { border: none; padding: 2px 16px 2px 0; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 6px 14px; margin-right: 8px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
        </div>
    @endunless

    <h1>Stock report</h1>
    <div class="muted">Generated {{ $generatedAt->format('d M Y H:i') }}</div>

    <table class="summary" style="margin-top: 12px;">
        <tr>
            <td><strong>Godown:</strong> {{ $godown->name }} ({{ $godown->code }})</td>
            <td><strong>Block:</strong> {{ $block->code }}{{ $block->name ? ' - '.$block->name : '' }}</td>
            <td><strong>Storage type:</strong> {{ $storageLabels[$block->storage_type] ?? $block->storage_type }}</td>
        </tr>
        <tr>
            <td><strong>Capacity:</strong> {{ $capacity === null ? '-' : $fmt($capacity).$unitText }}</td>
            <td><strong>In stock:</strong> {{ $fmt($total).$unitText }}</td>
            <td><strong>Available space:</strong> {{ $available === null ? '-' : $fmt($available).$unitText }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th class="num">Batches</th>
                <th class="num">Quantity available</th>
                <th>Earliest expiry</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->sku }}</td>
                    <td class="num">{{ $row->batches }}</td>
                    <td class="num">{{ $fmt($row->quantity) }} {{ $row->unit }}</td>
                    <td>{{ $row->next_expiry ? \Illuminate\Support\Carbon::parse($row->next_expiry)->format('d M Y') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;">No products in this block.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->count())
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="num">{{ $fmt($total) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
