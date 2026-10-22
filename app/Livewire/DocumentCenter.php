<?php

namespace App\Livewire;

use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shift;
use App\Support\Fmt;
use Livewire\Component;

class DocumentCenter extends Component
{
    public const TYPES = [
        'invoice' => ['label' => 'Invoices', 'roles' => ['admin', 'outlet_manager', 'cashier']],
        'grn' => ['label' => 'Goods received notes (GRN)', 'roles' => ['admin', 'godown_keeper']],
        'expense-voucher' => ['label' => 'Expense vouchers', 'roles' => ['admin', 'outlet_manager']],
        'customer-settlement' => ['label' => 'Customer settlements', 'roles' => ['admin', 'outlet_manager', 'cashier']],
        'cash-handover' => ['label' => 'Cash handover notes', 'roles' => ['admin', 'outlet_manager', 'cashier']],
    ];

    public string $type = '';
    public string $from = '';
    public string $to = '';
    public string $search = '';

    public function mount(): void
    {
        $available = $this->available();
        abort_if(! $available, 403);

        $this->type = array_key_first($available);
        $this->from = now()->subDays(29)->toDateString();
        $this->to = now()->toDateString();
    }

    private function available(): array
    {
        $role = auth()->user()?->role;

        return array_filter(self::TYPES, fn ($t) => in_array($role, $t['roles'], true));
    }

    public function render()
    {
        $available = $this->available();

        if (! array_key_exists($this->type, $available)) {
            $this->type = array_key_first($available);
        }

        return view('livewire.document-center', [
            'available' => $available,
            'table' => $this->table($this->type),
        ]);
    }

    private function table(string $type): array
    {
        $user = auth()->user();
        $term = trim($this->search);
        $from = $this->from ?: now()->subDays(29)->toDateString();
        $to = $this->to ?: now()->toDateString();

        switch ($type) {
            case 'invoice':
                $sales = Sale::with(['outlet', 'customer'])
                    ->when($user->role !== 'admin', fn ($q) => $q->where('outlet_id', $user->outlet_id ?? 0))
                    ->when($user->role === 'cashier', fn ($q) => $q->where('cashier_id', $user->id))
                    ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
                    ->when($term !== '', fn ($q) => $q->where('receipt_no', 'like', "%{$term}%"))
                    ->orderByDesc('id')->limit(50)->get();

                return [
                    'columns' => ['Invoice no.', 'Date', 'Outlet', 'Customer', 'Total', 'Balance due', 'Status'],
                    'rows' => $sales->map(fn ($s) => ['id' => $s->id, 'cells' => [
                        $s->receipt_no, $s->created_at->format('d M Y H:i'), $s->outlet?->name, $s->customer?->name ?? '-',
                        Fmt::money($s->total), Fmt::money($s->amount_on_account), $s->status === 'voided' ? 'Voided' : 'Completed',
                    ]])->all(),
                ];

            case 'grn':
                $receipts = GoodsReceipt::with(['supplier', 'godown'])
                    ->when($user->role === 'godown_keeper' && $user->godown_id, fn ($q) => $q->where('godown_id', $user->godown_id))
                    ->whereDate('received_date', '>=', $from)->whereDate('received_date', '<=', $to)
                    ->when($term !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('receipt_no', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")))
                    ->orderByDesc('id')->limit(50)->get();

                return [
                    'columns' => ['GRN no.', 'Date', 'Godown', 'Supplier', 'Invoice no.', 'Total cost'],
                    'rows' => $receipts->map(fn ($r) => ['id' => $r->id, 'cells' => [
                        $r->receipt_no, $r->received_date->format('d M Y'), $r->godown?->name, $r->supplier?->name ?? '-',
                        $r->reference ?: '-', Fmt::money($r->total_cost),
                    ]])->all(),
                ];

            case 'expense-voucher':
                $expenses = Expense::with('outlet')
                    ->when($user->role === 'outlet_manager', fn ($q) => $q->where('outlet_id', $user->outlet_id ?? 0))
                    ->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)
                    ->when($term !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('category', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")))
                    ->orderByDesc('expense_date')->orderByDesc('id')->limit(50)->get();

                return [
                    'columns' => ['Voucher no.', 'Date', 'Category', 'Description', 'For', 'Amount'],
                    'rows' => $expenses->map(fn ($e) => ['id' => $e->id, 'cells' => [
                        'EV-'.str_pad((string) $e->id, 6, '0', STR_PAD_LEFT),
                        \Illuminate\Support\Carbon::parse($e->expense_date)->format('d M Y'),
                        $e->category, $e->description ?: '-', $e->outlet?->name ?? 'General', Fmt::money($e->amount),
                    ]])->all(),
                ];

            case 'customer-settlement':
                $payments = Payment::query()
                    ->join('customers', 'customers.id', '=', 'payments.customer_id')
                    ->leftJoin('shifts', 'shifts.id', '=', 'payments.shift_id')
                    ->whereNull('payments.sale_id')
                    ->when($user->role !== 'admin', fn ($q) => $q->where('shifts.outlet_id', $user->outlet_id ?? 0))
                    ->when($user->role === 'cashier', fn ($q) => $q->where('payments.user_id', $user->id))
                    ->whereDate('payments.created_at', '>=', $from)->whereDate('payments.created_at', '<=', $to)
                    ->when($term !== '', fn ($q) => $q->where('customers.name', 'like', "%{$term}%"))
                    ->orderByDesc('payments.id')->limit(50)
                    ->select('payments.id', 'payments.created_at', 'payments.method', 'payments.amount', 'customers.name as customer_name')
                    ->get();

                $methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'card' => 'Card'];

                return [
                    'columns' => ['Settlement no.', 'Date', 'Customer', 'Paid by', 'Amount'],
                    'rows' => $payments->map(fn ($p) => ['id' => $p->id, 'cells' => [
                        'CS-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT),
                        \Illuminate\Support\Carbon::parse($p->created_at)->format('d M Y H:i'),
                        $p->customer_name, $methods[$p->method] ?? $p->method, Fmt::money($p->amount),
                    ]])->all(),
                ];

            default: // cash-handover
                $shifts = Shift::with(['outlet', 'user'])
                    ->whereNotNull('closed_at')
                    ->when($user->role !== 'admin', fn ($q) => $q->where('outlet_id', $user->outlet_id ?? 0))
                    ->when($user->role === 'cashier', fn ($q) => $q->where('user_id', $user->id))
                    ->whereDate('closed_at', '>=', $from)->whereDate('closed_at', '<=', $to)
                    ->when($term !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$term}%")))
                    ->orderByDesc('id')->limit(50)->get();

                return [
                    'columns' => ['Note no.', 'Closed', 'Outlet', 'Cashier', 'Expected cash', 'Counted cash', 'Difference'],
                    'rows' => $shifts->map(function ($s) {
                        $diff = round((float) $s->counted_cash - (float) $s->expected_cash, 2);

                        return ['id' => $s->id, 'cells' => [
                            'CH-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT), $s->closed_at->format('d M Y H:i'),
                            $s->outlet?->name, $s->user?->name, Fmt::money($s->expected_cash), Fmt::money($s->counted_cash),
                            $diff == 0 ? 'Matches' : ($diff > 0 ? '+' : '').Fmt::money($diff),
                        ]];
                    })->all(),
                ];
        }
    }
}
