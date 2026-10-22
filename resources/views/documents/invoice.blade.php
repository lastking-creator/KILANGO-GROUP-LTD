@extends('documents.layout')

@php
    use App\Support\Fmt;
    $methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'card' => 'Card', 'on_account' => 'On account'];
    $lines = $sale->items->groupBy(fn ($i) => $i->product_id.'|'.$i->unit_price);
    $balanceDue = (float) $sale->amount_on_account;
@endphp

@section('doc_title', 'INVOICE')

@section('doc_meta')
    <div><strong>Date:</strong> {{ $sale->created_at->format('d M Y') }}</div>
    <div><strong>Outlet:</strong> {{ $sale->outlet?->name }}</div>
    @if ($sale->status === 'voided')<div class="stamp">VOIDED</div>@endif
@endsection

@section('body')
    <div class="box">
        <strong>Bill to:</strong>
        @if ($sale->customer)
            {{ $sale->customer->name }}@if ($sale->customer->phone), {{ $sale->customer->phone }}@endif
        @else
            Walk-in customer
        @endif
        <span class="muted"> · Served by {{ $sale->cashier?->name }}</span>
    </div>

    <table class="grid" style="margin-top: 14px;">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Description</th>
                <th class="num">Quantity</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines->values() as $i => $group)
                @php $first = $group->first(); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $first->product?->name }}</td>
                    <td class="num">{{ Fmt::qty($group->sum('quantity')) }} {{ $first->product?->baseUnit?->abbreviation }}</td>
                    <td class="num">{{ Fmt::money($first->unit_price) }}</td>
                    <td class="num">{{ Fmt::money($group->sum('line_total')) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="4" class="r">Subtotal</td><td class="num">{{ Fmt::money($sale->subtotal) }}</td></tr>
            @if ((float) $sale->discount > 0)
                <tr><td colspan="4" class="r">Discount</td><td class="num">-{{ Fmt::money($sale->discount) }}</td></tr>
            @endif
            <tr><td colspan="4" class="r">Total</td><td class="num">{{ Fmt::money($sale->total) }}</td></tr>
        </tfoot>
    </table>

    <table class="meta" style="margin-top: 14px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <strong>Payments received</strong><br>
                @forelse ($sale->payments->where('method', '!=', 'on_account') as $payment)
                    {{ $methods[$payment->method] ?? $payment->method }}@if ($payment->reference) ({{ $payment->reference }})@endif:
                    {{ Fmt::money($payment->amount) }}<br>
                @empty
                    None<br>
                @endforelse
            </td>
            <td class="r" style="vertical-align: top;">
                <strong>Balance due: {{ Fmt::money($balanceDue) }}</strong><br>
                @if ($balanceDue > 0)<span class="muted">Sold on account. Please pay the balance as agreed.</span>@else<span class="muted">Paid in full.</span>@endif
            </td>
        </tr>
    </table>
@endsection

@section('signatures')
    <table class="sign">
        <tr>
            <td><div class="line">Prepared by: {{ $sale->cashier?->name }}</div></td>
            <td><div class="line">Customer signature</div></td>
            <td></td>
        </tr>
    </table>
@endsection
