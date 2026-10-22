@php
    $currency = config('pos.currency');
    $money = fn ($n) => number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'card' => 'Card', 'on_account' => 'On account'];
    $lines = $sale->items->groupBy(fn ($i) => $i->product_id.'|'.$i->unit_price);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->receipt_no }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; width: 300px; margin: 0 auto; padding: 12px; }
        h1 { font-size: 16px; text-align: center; margin: 0; }
        .center { text-align: center; }
        .muted { color: #444; }
        hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; font-size: 14px; }
        .toolbar { text-align: center; margin-bottom: 10px; }
        .void { text-align: center; font-weight: bold; border: 2px solid #000; padding: 4px; margin: 6px 0; }
        @media print { .toolbar { display: none; } body { width: auto; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <h1>{{ config('app.name') }}</h1>
    <div class="center">{{ $sale->outlet->name }}</div>
    @if ($sale->outlet->phone)
        <div class="center">{{ $sale->outlet->phone }}</div>
    @endif

    <hr>
    <div>Receipt: {{ $sale->receipt_no }}</div>
    <div>Date: {{ $sale->created_at->format('d M Y H:i') }}</div>
    <div>Cashier: {{ $sale->cashier?->name }}</div>
    @if ($sale->customer)
        <div>Customer: {{ $sale->customer->name }}</div>
    @endif
    @if ($sale->status === 'voided')
        <div class="void">VOIDED</div>
    @endif
    <hr>

    <table>
        @foreach ($lines as $group)
            @php
                $first = $group->first();
                $q = $group->sum('quantity');
                $lineTotal = $group->sum('line_total');
            @endphp
            <tr>
                <td colspan="2">{{ $first->product?->name }}</td>
            </tr>
            <tr>
                <td class="muted">{{ $qty($q) }} {{ $first->product?->baseUnit?->abbreviation }} x {{ number_format((float) $first->unit_price, 2, '.', ',') }}</td>
                <td class="r">{{ number_format((float) $lineTotal, 2, '.', ',') }}</td>
            </tr>
        @endforeach
    </table>

    <hr>
    <table>
        <tr><td>Subtotal</td><td class="r">{{ $money($sale->subtotal) }}</td></tr>
        @if ((float) $sale->discount > 0)
            <tr><td>Discount</td><td class="r">-{{ $money($sale->discount) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="r">{{ $money($sale->total) }}</td></tr>
    </table>

    <hr>
    <table>
        @foreach ($sale->payments as $payment)
            <tr>
                <td>{{ $methods[$payment->method] ?? $payment->method }}@if ($payment->reference) ({{ $payment->reference }})@endif</td>
                <td class="r">{{ $money($payment->amount) }}</td>
            </tr>
        @endforeach
        @if ((float) $sale->change_given > 0)
            <tr><td>Change</td><td class="r">{{ $money($sale->change_given) }}</td></tr>
        @endif
    </table>

    <hr>
    <div class="center">{{ config('pos.receipt_footer') }}</div>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
