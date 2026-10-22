<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Support\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DocumentController
{
    private const ACCESS = [
        'invoice' => ['admin', 'outlet_manager', 'cashier'],
        'grn' => ['admin', 'godown_keeper'],
        'expense-voucher' => ['admin', 'outlet_manager'],
        'customer-settlement' => ['admin', 'outlet_manager', 'cashier'],
        'cash-handover' => ['admin', 'outlet_manager', 'cashier'],
    ];

    public function __invoke(string $type, int $id, string $format)
    {
        $user = auth()->user();

        abort_unless(in_array($user->role, self::ACCESS[$type] ?? [], true), 403);
        abort_unless(in_array($format, ['print', 'pdf'], true), 404);

        [$view, $data, $number] = match ($type) {
            'invoice' => $this->invoice($id, $user),
            'grn' => $this->grn($id, $user),
            'expense-voucher' => $this->expenseVoucher($id, $user),
            'customer-settlement' => $this->settlement($id, $user),
            'cash-handover' => $this->handover($id, $user),
            default => abort(404),
        };

        $data += [
            'company' => Company::profile(),
            'number' => $number,
            'forPdf' => $format === 'pdf',
            'autoPrint' => $format === 'print',
        ];

        if ($format === 'pdf') {
            return Pdf::loadView($view, $data)->setPaper('a4')->download(Str::slug($type.' '.$number).'.pdf');
        }

        return view($view, $data);
    }

    private function invoice(int $id, User $user): array
    {
        $sale = Sale::with(['outlet', 'cashier', 'customer', 'items.product.baseUnit', 'payments'])->findOrFail($id);

        if ($user->role !== 'admin') {
            abort_unless($user->outlet_id === $sale->outlet_id, 403);

            if ($user->role === 'cashier') {
                abort_unless($sale->cashier_id === $user->id, 403);
            }
        }

        return ['documents.invoice', ['sale' => $sale], $sale->receipt_no];
    }

    private function grn(int $id, User $user): array
    {
        $receipt = GoodsReceipt::with(['supplier', 'godown', 'receiver', 'items.batch.product.baseUnit', 'items.block'])->findOrFail($id);

        if ($user->role === 'godown_keeper' && $user->godown_id) {
            abort_unless($receipt->godown_id === $user->godown_id, 403);
        }

        return ['documents.grn', ['receipt' => $receipt], $receipt->receipt_no];
    }

    private function expenseVoucher(int $id, User $user): array
    {
        $expense = Expense::with(['outlet', 'user'])->findOrFail($id);

        if ($user->role === 'outlet_manager') {
            abort_unless($user->outlet_id === $expense->outlet_id, 403);
        }

        return ['documents.expense-voucher', ['expense' => $expense], 'EV-'.str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT)];
    }

    private function settlement(int $id, User $user): array
    {
        $payment = Payment::whereNull('sale_id')->whereNotNull('customer_id')->findOrFail($id);
        $shift = $payment->shift_id ? Shift::with('outlet')->find($payment->shift_id) : null;

        if ($user->role !== 'admin') {
            abort_unless($shift && $shift->outlet_id === $user->outlet_id, 403);

            if ($user->role === 'cashier') {
                abort_unless($payment->user_id === $user->id, 403);
            }
        }

        $customer = Customer::findOrFail($payment->customer_id);
        $entry = CustomerLedger::where('payment_id', $payment->id)->first();

        $after = null;
        if ($entry) {
            $after = (float) CustomerLedger::where('customer_id', $customer->id)
                ->where('id', '<=', $entry->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END), 0) as b")
                ->value('b');
        }

        return [
            'documents.customer-settlement',
            [
                'payment' => $payment,
                'customer' => $customer,
                'receiver' => User::find($payment->user_id),
                'outlet' => $shift?->outlet,
                'balanceAfter' => $after,
                'balanceBefore' => $after === null ? null : round($after + (float) $payment->amount, 2),
                'note' => $entry?->note,
            ],
            'CS-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
        ];
    }

    private function handover(int $id, User $user): array
    {
        $shift = Shift::with(['outlet', 'user'])->findOrFail($id);

        abort_unless($shift->closed_at, 404, 'Close the shift first, then print the handover note.');

        if ($user->role !== 'admin') {
            abort_unless($user->outlet_id === $shift->outlet_id, 403);

            if ($user->role === 'cashier') {
                abort_unless($shift->user_id === $user->id, 403);
            }
        }

        $byMethod = Payment::query()
            ->leftJoin('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('payments.shift_id', $shift->id)
            ->where(fn ($q) => $q->whereNull('payments.sale_id')->orWhere('sales.status', 'completed'))
            ->groupBy('payments.method')
            ->selectRaw('payments.method as method, SUM(payments.amount) as total')
            ->pluck('total', 'method');

        $sales = Sale::where('shift_id', $shift->id)->where('status', 'completed');

        return [
            'documents.cash-handover',
            [
                'shift' => $shift,
                'byMethod' => $byMethod,
                'salesCount' => (clone $sales)->count(),
                'salesTotal' => (float) (clone $sales)->sum('total'),
            ],
            'CH-'.str_pad((string) $shift->id, 5, '0', STR_PAD_LEFT),
        ];
    }
}
