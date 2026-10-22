@extends('documents.layout')

@php use App\Support\Fmt; @endphp

@section('doc_title', 'GOODS RECEIVED NOTE')

@section('doc_meta')
    <div><strong>Date received:</strong> {{ $receipt->received_date->format('d M Y') }}</div>
    <div><strong>Godown:</strong> {{ $receipt->godown?->name }}</div>
@endsection

@section('body')
    <table class="meta" style="margin-top: 10px;">
        <tr>
            <td><strong>Supplier:</strong> {{ $receipt->supplier?->name ?? 'Not recorded' }}</td>
            <td class="r"><strong>Invoice / delivery note no.:</strong> {{ $receipt->reference ?: '-' }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-top: 12px;">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Product</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th>Block</th>
                <th class="num">Quantity</th>
                <th class="num">Unit cost</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->batch?->product?->name }}<br><span class="muted">{{ $item->batch?->product?->sku }}</span></td>
                    <td>{{ $item->batch?->batch_no }}</td>
                    <td>{{ $item->batch?->expiry_date ? $item->batch->expiry_date->format('d M Y') : '-' }}</td>
                    <td>{{ $item->block?->code }}</td>
                    <td class="num">{{ Fmt::qty($item->quantity) }} {{ $item->batch?->product?->baseUnit?->abbreviation }}</td>
                    <td class="num">{{ Fmt::money($item->unit_cost) }}</td>
                    <td class="num">{{ Fmt::money($item->quantity * $item->unit_cost) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="7" class="r">Total cost</td><td class="num">{{ Fmt::money($receipt->total_cost) }}</td></tr>
        </tfoot>
    </table>

    @if ($receipt->notes)
        <div class="box"><strong>Notes:</strong> {{ $receipt->notes }}</div>
    @endif
@endsection

@section('signatures')
    <table class="sign">
        <tr>
            <td><div class="line">Received by: {{ $receipt->receiver?->name }}</div></td>
            <td><div class="line">Delivered by (supplier representative)</div></td>
            <td><div class="line">Approved by</div></td>
        </tr>
    </table>
@endsection
