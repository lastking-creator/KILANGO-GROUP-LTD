@extends('documents.layout')

@php use App\Support\Fmt; @endphp

@section('doc_title', 'EXPENSE VOUCHER')

@section('doc_meta')
    <div><strong>Date:</strong> {{ \Illuminate\Support\Carbon::parse($expense->expense_date)->format('d M Y') }}</div>
@endsection

@section('body')
    <table class="grid" style="margin-top: 14px;">
        <thead>
            <tr>
                <th>Category</th>
                <th>Description</th>
                <th>Charged to</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $expense->category }}</td>
                <td>{{ $expense->description ?: '-' }}</td>
                <td>{{ $expense->outlet?->name ?? 'General / head office' }}</td>
                <td class="num">{{ Fmt::money($expense->amount) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="r">Total paid</td><td class="num">{{ Fmt::money($expense->amount) }}</td></tr>
        </tfoot>
    </table>

    <div class="box"><strong>Paid to:</strong> ______________________________________________</div>
@endsection

@section('signatures')
    <table class="sign">
        <tr>
            <td><div class="line">Prepared by: {{ $expense->user?->name }}</div></td>
            <td><div class="line">Approved by</div></td>
            <td><div class="line">Received by (payee)</div></td>
        </tr>
    </table>
@endsection
