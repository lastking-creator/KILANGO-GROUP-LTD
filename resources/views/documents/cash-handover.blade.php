@extends('documents.layout')

@php
    use App\Support\Fmt;
    $diff = round((float) $shift->counted_cash - (float) $shift->expected_cash, 2);
@endphp

@section('doc_title', 'CASH HANDOVER NOTE')

@section('doc_meta')
    <div><strong>Outlet:</strong> {{ $shift->outlet?->name }}</div>
    <div><strong>Cashier:</strong> {{ $shift->user?->name }}</div>
@endsection

@section('body')
    <table class="meta" style="margin-top: 10px;">
        <tr>
            <td><strong>Shift opened:</strong> {{ $shift->opened_at->format('d M Y H:i') }}</td>
            <td class="r"><strong>Shift closed:</strong> {{ $shift->closed_at->format('d M Y H:i') }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-top: 12px;">
        <thead>
            <tr><th>Item</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            <tr><td>Sales made ({{ $salesCount }} sales)</td><td class="num">{{ Fmt::money($salesTotal) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Received in cash</td><td class="num">{{ Fmt::money($byMethod['cash'] ?? 0) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Received by mobile money</td><td class="num">{{ Fmt::money($byMethod['mobile_money'] ?? 0) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Received by card</td><td class="num">{{ Fmt::money($byMethod['card'] ?? 0) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Sold on account (not yet paid)</td><td class="num">{{ Fmt::money($byMethod['on_account'] ?? 0) }}</td></tr>
        </tbody>
    </table>

    <table class="grid" style="margin-top: 14px;">
        <tbody>
            <tr><td>Cash in the drawer at the start</td><td class="num">{{ Fmt::money($shift->opening_cash) }}</td></tr>
            <tr><td>Cash that should be in the drawer</td><td class="num">{{ Fmt::money($shift->expected_cash) }}</td></tr>
            <tr><td><strong>Cash counted and handed over</strong></td><td class="num"><strong>{{ Fmt::money($shift->counted_cash) }}</strong></td></tr>
        </tbody>
        <tfoot>
            <tr>
                <td>Difference</td>
                <td class="num">{{ $diff == 0 ? 'None, the cash matches' : ($diff > 0 ? 'Over by '.Fmt::money($diff) : 'Short by '.Fmt::money(abs($diff))) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="box"><strong>Mobile money and card payments</strong> are not cash. They are listed above for your records only.</div>
@endsection

@section('signatures')
    <table class="sign">
        <tr>
            <td><div class="line">Handed over by: {{ $shift->user?->name }}</div></td>
            <td><div class="line">Received by (name and signature)</div></td>
            <td><div class="line">Date and time received</div></td>
        </tr>
    </table>
@endsection
