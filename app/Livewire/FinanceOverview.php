<?php

namespace App\Livewire;

use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Livewire\Component;

class FinanceOverview extends Component
{
    public string $from = '';
    public string $to = '';
    public string $outletFilter = '';

    private const ROLES = ['admin', 'outlet_manager'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    private function outletId(): ?int
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return $this->outletFilter !== '' ? (int) $this->outletFilter : null;
        }

        return (int) ($user->outlet_id ?? 0);
    }

    public function render()
    {
        $user = auth()->user();
        $outletId = $this->outletId();
        $from = $this->from ?: now()->startOfMonth()->toDateString();
        $to = $this->to ?: now()->toDateString();

        // Sales
        $sales = Sale::where('status', 'completed')
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $salesCount = (clone $sales)->count();
        $salesTotal = (float) (clone $sales)->sum('total');
        $discounts = (float) (clone $sales)->sum('discount');
        $onAccount = (float) (clone $sales)->sum('amount_on_account');

        // Money received (cash, mobile money, card), including repayments of customer accounts
        $received = Payment::query()
            ->leftJoin('sales', 'sales.id', '=', 'payments.sale_id')
            ->leftJoin('shifts', 'shifts.id', '=', 'payments.shift_id')
            ->whereIn('payments.method', ['cash', 'mobile_money', 'card'])
            ->where(fn ($q) => $q->whereNull('payments.sale_id')->orWhere('sales.status', 'completed'))
            ->whereDate('payments.created_at', '>=', $from)->whereDate('payments.created_at', '<=', $to)
            ->when($outletId, fn ($q) => $q->whereRaw('COALESCE(sales.outlet_id, shifts.outlet_id) = ?', [$outletId]))
            ->groupBy('payments.method')
            ->selectRaw('payments.method as method, SUM(payments.amount) as total')
            ->pluck('total', 'method');

        $repaid = (float) Payment::query()
            ->leftJoin('shifts', 'shifts.id', '=', 'payments.shift_id')
            ->whereNull('payments.sale_id')
            ->whereIn('payments.method', ['cash', 'mobile_money', 'card'])
            ->whereDate('payments.created_at', '>=', $from)->whereDate('payments.created_at', '<=', $to)
            ->when($outletId, fn ($q) => $q->where('shifts.outlet_id', $outletId))
            ->sum('payments.amount');

        // Cost of the goods sold (what they cost when they were bought) and gross profit
        $cogs = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_batches as pb', 'pb.id', '=', 'sale_items.batch_id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', '>=', $from)->whereDate('sales.created_at', '<=', $to)
            ->when($outletId, fn ($q) => $q->where('sales.outlet_id', $outletId))
            ->selectRaw('COALESCE(SUM(sale_items.quantity * pb.unit_cost), 0) as cogs')
            ->value('cogs');

        $grossProfit = round($salesTotal - $cogs, 2);

        $expenses = (float) Expense::where('expense_date', '>=', $from)->where('expense_date', '<=', $to)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->sum('amount');

        $purchases = $user->role === 'admin' && ! $outletId
            ? (float) GoodsReceipt::whereDate('received_date', '>=', $from)->whereDate('received_date', '<=', $to)->sum('total_cost')
            : null;

        // Money customers owe now
        $debtors = CustomerLedger::query()
            ->join('customers', 'customers.id', '=', 'customer_ledger.customer_id')
            ->groupBy('customers.id', 'customers.name')
            ->havingRaw("SUM(CASE WHEN customer_ledger.entry_type = 'debit' THEN customer_ledger.amount ELSE -customer_ledger.amount END) > 0.004")
            ->selectRaw("customers.name as name, SUM(CASE WHEN customer_ledger.entry_type = 'debit' THEN customer_ledger.amount ELSE -customer_ledger.amount END) as balance")
            ->orderByDesc('balance')
            ->get();

        $shifts = Shift::with(['outlet', 'user'])
            ->whereDate('opened_at', '>=', $from)->whereDate('opened_at', '<=', $to)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('livewire.finance-overview', [
            'salesCount' => $salesCount,
            'salesTotal' => $salesTotal,
            'discounts' => $discounts,
            'onAccount' => $onAccount,
            'received' => $received,
            'repaid' => $repaid,
            'cogs' => $cogs,
            'grossProfit' => $grossProfit,
            'expenses' => $expenses,
            'netProfit' => round($grossProfit - $expenses, 2),
            'purchases' => $purchases,
            'owedTotal' => (float) $debtors->sum('balance'),
            'topDebtors' => $debtors->take(5),
            'debtorCount' => $debtors->count(),
            'shifts' => $shifts,
            'outlets' => $user->role === 'admin' ? Outlet::orderBy('code')->get() : collect(),
        ]);
    }
}
