<?php

namespace App\Livewire;

use App\Models\AttendanceRecord;
use App\Models\Block;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Employee;
use App\Models\Godown;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\Transfer;
use Livewire\Component;

class DashboardOverview extends Component
{
    public string $period = 'month';
    public ?int $selectedOutletId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->role === 'outlet_manager' && $user->outlet_id) {
            $this->selectedOutletId = (int) $user->outlet_id;
        }
    }

    public function selectOutlet(int $id): void
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            $this->selectedOutletId = Outlet::findOrFail($id)->id;
        } elseif ($user->role === 'outlet_manager') {
            $this->selectedOutletId = (int) $user->outlet_id;
        }
    }

    public function closeOutlet(): void
    {
        if (auth()->user()->role === 'admin') {
            $this->selectedOutletId = null;
        }
    }

    private function periodRange(): array
    {
        $to = now()->toDateString();

        return match ($this->period) {
            'today' => [$to, $to],
            'week' => [now()->subDays(6)->toDateString(), $to],
            'last30' => [now()->subDays(29)->toDateString(), $to],
            default => [now()->startOfMonth()->toDateString(), $to],
        };
    }

    private function salesQuery(?int $outletId, string $from, string $to)
    {
        return Sale::where('status', 'completed')
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId));
    }

    /** Stock rows (with their batch) that this person is allowed to see. */
    private function stockBase(string $role, ?int $outletId, ?int $godownId)
    {
        $query = StockLevel::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.quantity', '>', 0);

        if ($role === 'admin') {
            return $query;
        }

        if ($outletId !== null) {
            return $query->where('stock_levels.location_type', 'outlet')->where('stock_levels.location_id', $outletId);
        }

        $query->where('stock_levels.location_type', 'block');

        if ($godownId) {
            $query->whereIn('stock_levels.location_id', Block::where('godown_id', $godownId)->pluck('id'));
        }

        return $query;
    }

    public function render()
    {
        $user = auth()->user();
        $role = $user->role;
        $today = now()->toDateString();
        [$from, $to] = $this->periodRange();

        $outletId = in_array($role, ['outlet_manager', 'cashier'], true) ? (int) ($user->outlet_id ?? 0) : null;
        $godownId = $role === 'godown_keeper' && $user->godown_id ? (int) $user->godown_id : null;

        $data = [
            'role' => $role,
            'firstName' => explode(' ', trim($user->name))[0] ?? $user->name,
            'periodLabel' => ['today' => 'today', 'week' => 'the last 7 days', 'last30' => 'the last 30 days', 'month' => 'this month'][$this->period] ?? 'this month',
        ];

        // ---------- Cashier: a small personal dashboard ----------
        if ($role === 'cashier') {
            $mine = Sale::where('status', 'completed')->where('cashier_id', $user->id)->whereDate('created_at', $today);

            $data['shift'] = Shift::where('user_id', $user->id)->whereNull('closed_at')->latest('id')->first();
            $data['myCount'] = (clone $mine)->count();
            $data['myTotal'] = (float) (clone $mine)->sum('total');
            $data['outletName'] = $outletId ? Outlet::find($outletId)?->name : null;

            return view('livewire.dashboard-overview', $data);
        }

        $showSales = in_array($role, ['admin', 'outlet_manager'], true);
        $showGodowns = in_array($role, ['admin', 'godown_keeper'], true);
        $data['showSales'] = $showSales;
        $data['showGodowns'] = $showGodowns;

        // ---------- Sales ----------
        if ($showSales) {
            $todaySales = $this->salesQuery($outletId, $today, $today);
            $periodSales = $this->salesQuery($outletId, $from, $to);

            $data['salesToday'] = ['count' => (clone $todaySales)->count(), 'total' => (float) (clone $todaySales)->sum('total')];
            $periodTotal = (float) (clone $periodSales)->sum('total');
            $data['salesPeriod'] = ['count' => (clone $periodSales)->count(), 'total' => $periodTotal];

            $cogs = (float) SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('product_batches as pb', 'pb.id', '=', 'sale_items.batch_id')
                ->where('sales.status', 'completed')
                ->whereDate('sales.created_at', '>=', $from)->whereDate('sales.created_at', '<=', $to)
                ->when($outletId !== null, fn ($q) => $q->where('sales.outlet_id', $outletId))
                ->selectRaw('COALESCE(SUM(sale_items.quantity * pb.unit_cost), 0) as c')
                ->value('c');
            $data['grossProfit'] = round($periodTotal - $cogs, 2);

            // Last 14 days
            $raw = Sale::where('status', 'completed')
                ->whereDate('created_at', '>=', now()->subDays(13)->toDateString())
                ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId))
                ->selectRaw('DATE(created_at) as d, SUM(total) as t')
                ->groupBy('d')->pluck('t', 'd');

            $trend = collect(range(13, 0))->map(function ($i) use ($raw) {
                $day = now()->subDays($i);

                return ['date' => $day, 'total' => (float) ($raw[$day->toDateString()] ?? 0)];
            });
            $data['trend'] = $trend;
            $data['trendMax'] = max(1, (float) $trend->max('total'));

            $data['topProducts'] = SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products as p', 'p.id', '=', 'sale_items.product_id')
                ->join('units as u', 'u.id', '=', 'p.base_unit_id')
                ->where('sales.status', 'completed')
                ->whereDate('sales.created_at', '>=', $from)->whereDate('sales.created_at', '<=', $to)
                ->when($outletId !== null, fn ($q) => $q->where('sales.outlet_id', $outletId))
                ->groupBy('p.id', 'p.name', 'u.abbreviation')
                ->selectRaw('p.name as name, u.abbreviation as unit, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue')
                ->orderByDesc('revenue')->limit(5)->get();

            $data['recentSales'] = Sale::with(['outlet', 'cashier'])
                ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId))
                ->orderByDesc('id')->limit(6)->get();

            $data['owedTotal'] = (float) CustomerLedger::query()
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END), 0) as b")
                ->value('b');

            $data['topDebtors'] = CustomerLedger::query()
                ->join('customers', 'customers.id', '=', 'customer_ledger.customer_id')
                ->groupBy('customers.id', 'customers.name')
                ->havingRaw("SUM(CASE WHEN customer_ledger.entry_type = 'debit' THEN customer_ledger.amount ELSE -customer_ledger.amount END) > 0.004")
                ->selectRaw("customers.name as name, SUM(CASE WHEN customer_ledger.entry_type = 'debit' THEN customer_ledger.amount ELSE -customer_ledger.amount END) as balance")
                ->orderByDesc('balance')->limit(5)->get();
        }

        // ---------- Stock ----------
        $base = $this->stockBase($role, $outletId, $godownId);

        $data['stockValue'] = (float) (clone $base)->selectRaw('COALESCE(SUM(stock_levels.quantity * pb.unit_cost), 0) as v')->value('v');
        $data['stockProducts'] = (int) (clone $base)->selectRaw('COUNT(DISTINCT pb.product_id) as c')->value('c');

        $soon = now()->addDays(30)->toDateString();
        $data['expiringCount'] = (clone $base)->whereNotNull('pb.expiry_date')
            ->whereDate('pb.expiry_date', '>=', $today)->whereDate('pb.expiry_date', '<=', $soon)->count();
        $data['expiredCount'] = (clone $base)->whereNotNull('pb.expiry_date')->whereDate('pb.expiry_date', '<', $today)->count();

        $data['expiringList'] = (clone $base)
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoin('blocks as b', function ($j) {
                $j->on('b.id', '=', 'stock_levels.location_id')->where('stock_levels.location_type', '=', 'block');
            })
            ->leftJoin('godowns as g', 'g.id', '=', 'b.godown_id')
            ->leftJoin('outlets as o', function ($j) {
                $j->on('o.id', '=', 'stock_levels.location_id')->where('stock_levels.location_type', '=', 'outlet');
            })
            ->whereNotNull('pb.expiry_date')->whereDate('pb.expiry_date', '<=', $soon)
            ->orderBy('pb.expiry_date')->limit(6)
            ->select(
                'p.name as name', 'pb.batch_no', 'pb.expiry_date', 'u.abbreviation as unit',
                'stock_levels.quantity', 'stock_levels.location_type',
                'b.code as block_code', 'g.name as godown_name', 'o.name as outlet_name'
            )->get();

        // Low stock (reorder levels are set for the whole business, so only admin and godown keepers see it)
        if ($showGodowns) {
            $totals = StockLevel::query()
                ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
                ->groupBy('pb.product_id')
                ->selectRaw('pb.product_id as product_id, SUM(stock_levels.quantity) as total')
                ->pluck('total', 'product_id');

            $low = Product::with('baseUnit')->where('is_active', true)->where('reorder_level', '>', 0)->orderBy('name')->get()
                ->filter(fn ($p) => (float) ($totals[$p->id] ?? 0) <= (float) $p->reorder_level)->values();

            $data['lowCount'] = $low->count();
            $data['lowList'] = $low->take(6)->map(fn ($p) => [
                'name' => $p->name,
                'have' => (float) ($totals[$p->id] ?? 0),
                'reorder' => (float) $p->reorder_level,
                'unit' => $p->baseUnit?->abbreviation,
            ]);
        }

        // ---------- Employees ----------
        $employees = Employee::where('is_active', true)
            ->when($role === 'outlet_manager', fn ($q) => $q->where('outlet_id', $outletId))
            ->when($godownId, fn ($q) => $q->where('godown_id', $godownId));

        $data['employeeCount'] = (clone $employees)->count();
        $data['presentToday'] = AttendanceRecord::where('work_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->whereIn('employee_id', (clone $employees)->select('id'))->count();

        // ---------- Transfers ----------
        $transfers = Transfer::query();
        if ($role === 'outlet_manager') {
            $transfers->where('to_type', 'outlet')->where('to_id', $outletId);
        } elseif ($role === 'godown_keeper' && $godownId) {
            $blockIds = Block::where('godown_id', $godownId)->pluck('id');
            $transfers->where(function ($q) use ($blockIds) {
                $q->whereIn('from_block_id', $blockIds)
                    ->orWhere(fn ($q2) => $q2->where('to_type', 'block')->whereIn('to_id', $blockIds));
            });
        }

        $data['pendingTransfers'] = (clone $transfers)->where('status', 'pending')->count();
        $data['transitTransfers'] = (clone $transfers)->where('status', 'in_transit')->count();

        $recent = (clone $transfers)->with('fromBlock.godown')->orderByDesc('id')->limit(5)->get();
        $blockMap = Block::with('godown')->whereIn('id', $recent->where('to_type', 'block')->pluck('to_id'))->get()->keyBy('id');
        $outletMap = Outlet::whereIn('id', $recent->where('to_type', 'outlet')->pluck('to_id'))->get()->keyBy('id');
        $data['recentTransfers'] = $recent;
        $data['transferTo'] = $recent->mapWithKeys(function ($t) use ($blockMap, $outletMap) {
            if ($t->to_type === 'block') {
                $b = $blockMap->get($t->to_id);

                return [$t->id => $b ? $b->godown->name.' · Block '.$b->code : '-'];
            }

            return [$t->id => $outletMap->get($t->to_id)?->name ?? '-'];
        });

        // ---------- Outlets overview ----------
        if ($showSales) {
            $outlets = Outlet::where('is_active', true)
                ->when($outletId !== null, fn ($q) => $q->where('id', $outletId))
                ->orderBy('code')->get();

            $empBy = Employee::where('is_active', true)->whereNotNull('outlet_id')
                ->groupBy('outlet_id')->selectRaw('outlet_id, COUNT(*) as c')->pluck('c', 'outlet_id');

            $presentBy = AttendanceRecord::query()
                ->join('employees', 'employees.id', '=', 'attendance_records.employee_id')
                ->where('employees.is_active', true)->whereNotNull('employees.outlet_id')
                ->where('attendance_records.work_date', $today)
                ->whereIn('attendance_records.status', ['present', 'late'])
                ->groupBy('employees.outlet_id')
                ->selectRaw('employees.outlet_id as oid, COUNT(*) as c')->pluck('c', 'oid');

            $salesTodayBy = Sale::where('status', 'completed')->whereDate('created_at', $today)
                ->groupBy('outlet_id')->selectRaw('outlet_id, SUM(total) as t')->pluck('t', 'outlet_id');

            $salesPeriodBy = Sale::where('status', 'completed')
                ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
                ->groupBy('outlet_id')->selectRaw('outlet_id, SUM(total) as t')->pluck('t', 'outlet_id');

            $stockBy = StockLevel::query()
                ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
                ->where('stock_levels.location_type', 'outlet')->where('stock_levels.quantity', '>', 0)
                ->groupBy('stock_levels.location_id')
                ->selectRaw('stock_levels.location_id as oid, COUNT(DISTINCT pb.product_id) as products, SUM(stock_levels.quantity * pb.unit_cost) as value')
                ->get()->keyBy('oid');

            $shiftsBy = Shift::whereNull('closed_at')->groupBy('outlet_id')
                ->selectRaw('outlet_id, COUNT(*) as c')->pluck('c', 'outlet_id');

            $data['outletRows'] = $outlets->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'employees' => (int) ($empBy[$o->id] ?? 0),
                'present' => (int) ($presentBy[$o->id] ?? 0),
                'salesToday' => (float) ($salesTodayBy[$o->id] ?? 0),
                'salesPeriod' => (float) ($salesPeriodBy[$o->id] ?? 0),
                'products' => (int) ($stockBy[$o->id]->products ?? 0),
                'stockValue' => (float) ($stockBy[$o->id]->value ?? 0),
                'openShifts' => (int) ($shiftsBy[$o->id] ?? 0),
            ]);
        }

        // ---------- Godowns overview ----------
        if ($showGodowns) {
            $godowns = Godown::where('is_active', true)
                ->when($godownId, fn ($q) => $q->where('id', $godownId))
                ->orderBy('code')->get();

            $blocksBy = Block::where('is_active', true)->groupBy('godown_id')
                ->selectRaw('godown_id, COUNT(*) as blocks, SUM(capacity) as capacity')->get()->keyBy('godown_id');

            $stockByG = StockLevel::query()
                ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
                ->join('blocks as b', 'b.id', '=', 'stock_levels.location_id')
                ->where('stock_levels.location_type', 'block')->where('stock_levels.quantity', '>', 0)
                ->groupBy('b.godown_id')
                ->selectRaw(
                    'b.godown_id as gid, COUNT(DISTINCT pb.product_id) as products, '
                    .'SUM(stock_levels.quantity * pb.unit_cost) as value, '
                    .'SUM(CASE WHEN b.capacity IS NOT NULL THEN stock_levels.quantity ELSE 0 END) as used_capped'
                )->get()->keyBy('gid');

            $empG = Employee::where('is_active', true)->whereNotNull('godown_id')
                ->groupBy('godown_id')->selectRaw('godown_id, COUNT(*) as c')->pluck('c', 'godown_id');

            $data['godownRows'] = $godowns->map(function ($g) use ($blocksBy, $stockByG, $empG) {
                $capacity = (float) ($blocksBy[$g->id]->capacity ?? 0);
                $used = (float) ($stockByG[$g->id]->used_capped ?? 0);

                return [
                    'name' => $g->name,
                    'blocks' => (int) ($blocksBy[$g->id]->blocks ?? 0),
                    'employees' => (int) ($empG[$g->id] ?? 0),
                    'products' => (int) ($stockByG[$g->id]->products ?? 0),
                    'stockValue' => (float) ($stockByG[$g->id]->value ?? 0),
                    'fill' => $capacity > 0 ? min(100, (int) round($used / $capacity * 100)) : null,
                ];
            });
        }

        // ---------- One outlet in detail ----------
        $detailId = null;
        if ($role === 'outlet_manager') {
            $detailId = $outletId ?: null;
        } elseif ($role === 'admin') {
            $detailId = $this->selectedOutletId;
        }

        if ($detailId && $detail = Outlet::find($detailId)) {
            $staff = Employee::where('is_active', true)->where('outlet_id', $detail->id)->orderBy('full_name')->get();
            $attendance = AttendanceRecord::where('work_date', $today)->whereIn('employee_id', $staff->pluck('id'))->get()->keyBy('employee_id');

            $data['detail'] = [
                'outlet' => $detail,
                'staff' => $staff,
                'attendance' => $attendance,
                'stock' => StockLevel::query()
                    ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
                    ->join('products as p', 'p.id', '=', 'pb.product_id')
                    ->join('units as u', 'u.id', '=', 'p.base_unit_id')
                    ->where('stock_levels.location_type', 'outlet')->where('stock_levels.location_id', $detail->id)
                    ->where('stock_levels.quantity', '>', 0)
                    ->groupBy('p.id', 'p.name', 'p.sku', 'u.abbreviation')
                    ->selectRaw('p.name as name, p.sku as sku, u.abbreviation as unit, SUM(stock_levels.quantity) as qty, SUM(stock_levels.quantity * pb.unit_cost) as value, MIN(pb.expiry_date) as next_expiry')
                    ->orderBy('p.name')->get(),
            ];
        }

        $data['canPickOutlet'] = $role === 'admin';

        return view('livewire.dashboard-overview', $data);
    }
}
