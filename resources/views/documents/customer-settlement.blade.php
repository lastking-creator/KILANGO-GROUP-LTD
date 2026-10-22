@extends('documents.layout')

@php
    use App\Support\Fmt;
    $methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'card' => 'Card'];
@endphp

@section('doc_title', 'CUSTOMER SETTLEMENT')

@section('doc_meta')
    <div><strong>Date:</strong> {{ $payment->created_at->format('d M Y H:i') }}</div>
    @if ($outlet)<div><strong>Outlet:</strong> {{ $outlet->name }}</div>@endif
@endsection

@section('body')
    <div class="box">
        <strong>Received from:</strong> {{ $customer->name }}@if ($customer->phone), {{ $customer->phone }}@endif
    </div>

    <table class="grid" style="margin-top: 14px;">
        <tbody>
            @if ($balanceBefore !== null)
                <tr><td>Balance owed before this payment</td><td class="num">{{ Fmt::money($balanceBefore) }}</td></tr>
            @endif
            <tr>
                <td>
                    Amount received by {{ $methods[$payment->method] ?? $payment->method }}
                    @if ($payment->reference) (ref. {{ $payment->reference }})@endif
                </td>
                <td class="num"><strong>{{ Fmt::money($payment->amount) }}</strong></td>
            </tr>
        </tbody>
        @if ($balanceAfter !== null)
            <tfoot>
                <tr><td>Balance still owed</td><td class="num">{{ Fmt::money(max(0, $balanceAfter)) }}</td></tr>
            </tfoot>
        @endif
    </table>

    @if ($note && $note !== 'Payment received')
        <div class="box"><strong>Note:</strong> {{ $note }}</div>
    @endif
@endsection

@section('signatures')
    <table class="sign">
        <tr>
            <td><div class="line">Received by: {{ $receiver?->name }}</div></td>
            <td><div class="line">Customer signature</div></td>
            <td></td>
        </tr>
    </table>
@endsection
