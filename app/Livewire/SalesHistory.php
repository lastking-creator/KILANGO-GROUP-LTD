<?php

namespace App\Livewire;

use App\Models\CustomerLedger;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\InsufficientStockException;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SalesHistory extends Component
{
    use WithPagination;

    public string $from = '';
    public string $to = '';
    public string $outletFilter = '';
    public string $statusFilter = '';
    public string $search = '';

    public ?int $viewSaleId = null;
    public string $voidReason = '';

    public string $message = '';
    public string $error = '';

    private const ROLES = ['admin', 'outlet_manager', 'cashier'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
        $this->from = now()->toDateString();
        $this->to = now()->toDateString();
    }

    #[On('sale-completed')]
    public function refreshList(): void
    {
        // The list is re-read on every render, so receiving the event is enough.
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingOutletFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function visibleSales()
    {
        $user = auth()->user();
        $query = Sale::query();

        if ($user->role === 'admin') {
            $query->when($this->outletFilter !== '', fn ($q) => $q->where('outlet_id', (int) $this->outletFilter));
        } else {
            $query->where('outlet_id', $user->outlet_id ?? 0);

            if ($user->role === 'cashier') {
                $query->where('cashier_id', $user->id);
            }
        }

        return $query;
    }

    private function filteredSales()
    {
        $term = trim($this->search);

        return $this->visibleSales()
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($term !== '', fn ($q) => $q->where('receipt_no', 'like', "%{$term}%"));
    }

    private function canVoid(Sale $sale): bool
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'outlet_manager' && $user->outlet_id === $sale->outlet_id;
    }

    // ---------- Details ----------

    public function viewSale(int $id): void
    {
        $this->viewSaleId = $this->visibleSales()->findOrFail($id)->id;
        $this->voidReason = '';
        $this->message = '';
        $this->error = '';
    }

    public function closeSale(): void
    {
        $this->viewSaleId = null;
    }

    public function voidSale(int $id): void
    {
        $this->message = '';
        $this->error = '';

        $sale = $this->visibleSales()->with(['items', 'shift'])->findOrFail($id);
        abort_unless($this->canVoid($sale), 403);

        if ($sale->status !== 'completed') {
            $this->error = 'This sale is already voided.';

            return;
        }

        if ($sale->shift && $sale->shift->closed_at) {
            $this->error = 'This sale belongs to a shift that is already closed, so it can no longer be voided.';

            return;
        }

        $this->validate(['voidReason' => ['required', 'string', 'max:255']], [], ['voidReason' => 'reason']);

        try {
            DB::transaction(function () use ($sale) {
                $stock = app(StockService::class);

                foreach ($sale->items as $item) {
                    $stock->change(
                        $item->batch_id, 'outlet', $sale->outlet_id, (float) $item->quantity,
                        'sale_return', 'sale', $sale->id, auth()->id(), 'Sale voided'
                    );
                }

                if ($sale->customer_id && (float) $sale->amount_on_account > 0) {
                    CustomerLedger::create([
                        'customer_id' => $sale->customer_id,
                        'entry_type' => 'credit',
                        'amount' => $sale->amount_on_account,
                        'sale_id' => $sale->id,
                        'note' => 'Sale '.$sale->receipt_no.' voided',
                    ]);
                }

                $sale->update([
                    'status' => 'voided',
                    'voided_by' => auth()->id(),
                    'voided_at' => now(),
                    'void_reason' => trim($this->voidReason),
                ]);
            });
        } catch (InsufficientStockException $e) {
            $this->error = 'Could not return the stock for this sale.';

            return;
        }

        $this->voidReason = '';
        $this->message = "Sale {$sale->receipt_no} voided. The stock went back to the outlet.";
    }

    // ---------- Render ----------

    public function render()
    {
        $user = auth()->user();

        $sales = $this->filteredSales()
            ->with(['outlet', 'cashier', 'customer'])
            ->orderByDesc('id')
            ->paginate(15);

        $completed = $this->filteredSales()->where('status', 'completed');

        $summary = (clone $completed)->selectRaw('COUNT(*) as c, COALESCE(SUM(total), 0) as t')->first();

        $byMethod = Payment::whereIn('sale_id', (clone $completed)->select('id'))
            ->groupBy('method')
            ->selectRaw('method, SUM(amount) as total')
            ->pluck('total', 'method');

        $viewSale = $this->viewSaleId
            ? $this->visibleSales()->with(['outlet', 'cashier', 'customer', 'voider', 'items.product.baseUnit', 'items.batch', 'payments', 'shift'])->find($this->viewSaleId)
            : null;

        return view('livewire.sales-history', [
            'sales' => $sales,
            'count' => (int) ($summary->c ?? 0),
            'sum' => (float) ($summary->t ?? 0),
            'byMethod' => $byMethod,
            'viewSale' => $viewSale,
            'canVoid' => $viewSale ? $this->canVoid($viewSale) : false,
            'outlets' => $user->role === 'admin' ? Outlet::orderBy('code')->get() : collect(),
        ]);
    }
}
