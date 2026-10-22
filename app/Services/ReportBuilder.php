<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds every report as plain data (columns + rows), so the screen, the print page,
 * the PDF and the Excel file all show exactly the same numbers.
 */
class ReportBuilder
{
    public const TYPES = [
        'sales_by_day' => ['label' => 'Sales by day', 'roles' => ['admin', 'outlet_manager'], 'range' => true, 'outlet' => true],
        'sales_by_product' => ['label' => 'Sales by product', 'roles' => ['admin', 'outlet_manager'], 'range' => true, 'outlet' => true],
        'stock_summary' => ['label' => 'Stock summary and value', 'roles' => ['admin', 'godown_keeper']],
        'expiry' => ['label' => 'Expiring and expired stock', 'roles' => ['admin', 'godown_keeper'], 'days' => true],
        'low_stock' => ['label' => 'Low stock', 'roles' => ['admin', 'godown_keeper']],
        'debtors' => ['label' => 'Customers who owe money', 'roles' => ['admin', 'outlet_manager']],
        'stock_movements' => ['label' => 'Stock movements (audit trail)', 'roles' => ['admin', 'godown_keeper'], 'range' => true],
    ];

    public static function availableFor(?string $role): array
    {
        return array_filter(self::TYPES, fn ($t) => in_array($role, $t['roles'], true));
    }

    /** Clean, safe filters from raw input. Outlet managers are always limited to their own outlet. */
    public static function filters(array $input, $user): array
    {
        $date = function ($value, $default) {
            try {
                return Carbon::createFromFormat('Y-m-d', (string) $value)->toDateString();
            } catch (\Throwable $e) {
                return $default;
            }
        };

        $outlet = null;
        if ($user->role === 'outlet_manager') {
            $outlet = (int) ($user->outlet_id ?? 0);
        } elseif ($user->role === 'admin' && ! empty($input['outlet'])) {
            $outlet = (int) $input['outlet'];
        }

        return [
            'from' => $date($input['from'] ?? null, now()->startOfMonth()->toDateString()),
            'to' => $date($input['to'] ?? null, now()->toDateString()),
            'outlet' => $outlet ?: null,
            'days' => max(1, min(365, (int) ($input['days'] ?? 30))),
        ];
    }

    public static function build(string $type, array $f): array
    {
        return match ($type) {
            'sales_by_day' => self::salesByDay($f),
            'sales_by_product' => self::salesByProduct($f),
            'stock_summary' => self::stockSummary(),
            'expiry' => self::expiry($f),
            'low_stock' => self::lowStock(),
            'debtors' => self::debtors(),
            'stock_movements' => self::stockMovements($f),
            default => abort(404),
        };
    }

    public static function format(string $type, $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2, '.', ','),
            'qty' => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0',
            'int' => number_format((float) $value, 0, '.', ','),
            default => (string) $value,
        };
    }

    private static function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    private static function subtitleRange(array $f): string
    {
        $text = Carbon::parse($f['from'])->format('d M Y').' to '.Carbon::parse($f['to'])->format('d M Y');

        if ($f['outlet']) {
            $text .= ' · '.(Outlet::find($f['outlet'])?->name ?? 'Outlet');
        } else {
            $text .= ' · All outlets';
        }

        return $text;
    }

    private static function totals(array $rows, array $keys): array
    {
        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = array_sum(array_map(fn ($r) => (float) ($r[$key] ?? 0), $rows));
        }

        return $totals;
    }

    // ---------- Sales ----------

    private static function salesByDay(array $f): array
    {
        $days = Sale::query()
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c, SUM(total) as t')
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $f['from'])->whereDate('created_at', '<=', $f['to'])
            ->when($f['outlet'], fn ($q) => $q->where('outlet_id', $f['outlet']))
            ->groupBy('d')->orderBy('d')->get();

        $pay = DB::table('payments')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', '>=', $f['from'])->whereDate('sales.created_at', '<=', $f['to'])
            ->when($f['outlet'], fn ($q) => $q->where('sales.outlet_id', $f['outlet']))
            ->selectRaw('DATE(sales.created_at) as d, payments.method as m, SUM(payments.amount) as a')
            ->groupBy('d', 'm')->get();

        $map = [];
        foreach ($pay as $p) {
            $map[$p->d][$p->m] = (float) $p->a;
        }

        $rows = [];
        foreach ($days as $day) {
            $rows[] = [
                'date' => Carbon::parse($day->d)->format('D d M Y'),
                'sales' => (int) $day->c,
                'total' => (float) $day->t,
                'cash' => $map[$day->d]['cash'] ?? 0,
                'mobile' => $map[$day->d]['mobile_money'] ?? 0,
                'card' => $map[$day->d]['card'] ?? 0,
                'account' => $map[$day->d]['on_account'] ?? 0,
            ];
        }

        return [
            'title' => 'Sales by day',
            'subtitle' => self::subtitleRange($f),
            'columns' => [
                self::col('date', 'Date'), self::col('sales', 'Sales', 'int'), self::col('total', 'Total', 'money'),
                self::col('cash', 'Cash', 'money'), self::col('mobile', 'Mobile money', 'money'),
                self::col('card', 'Card', 'money'), self::col('account', 'On account', 'money'),
            ],
            'rows' => $rows,
            'totals' => $rows ? ['date' => 'Total'] + self::totals($rows, ['sales', 'total', 'cash', 'mobile', 'card', 'account']) : null,
            'note' => 'Completed sales only. Voided sales are left out.',
        ];
    }

    private static function salesByProduct(array $f): array
    {
        $data = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products as p', 'p.id', '=', 'sale_items.product_id')
            ->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('product_batches as pb', 'pb.id', '=', 'sale_items.batch_id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', '>=', $f['from'])->whereDate('sales.created_at', '<=', $f['to'])
            ->when($f['outlet'], fn ($q) => $q->where('sales.outlet_id', $f['outlet']))
            ->groupBy('p.id', 'p.name', 'p.sku', 'u.abbreviation')
            ->selectRaw('p.name as name, p.sku as sku, u.abbreviation as unit, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * pb.unit_cost) as cost')
            ->orderByDesc('revenue')->get();

        $rows = $data->map(fn ($r) => [
            'name' => $r->name,
            'sku' => $r->sku,
            'unit' => $r->unit,
            'qty' => (float) $r->qty,
            'revenue' => (float) $r->revenue,
            'cost' => (float) $r->cost,
            'profit' => round((float) $r->revenue - (float) $r->cost, 2),
        ])->all();

        return [
            'title' => 'Sales by product',
            'subtitle' => self::subtitleRange($f),
            'columns' => [
                self::col('name', 'Product'), self::col('sku', 'SKU'), self::col('unit', 'Unit'),
                self::col('qty', 'Quantity sold', 'qty'), self::col('revenue', 'Sales', 'money'),
                self::col('cost', 'Cost', 'money'), self::col('profit', 'Profit', 'money'),
            ],
            'rows' => $rows,
            'totals' => $rows ? ['name' => 'Total'] + self::totals($rows, ['revenue', 'cost', 'profit']) : null,
            'note' => 'Cost is what the goods cost when they were received. Receive goods with their real unit cost for accurate profit.',
        ];
    }

    // ---------- Stock ----------

    private static function stockSummary(): array
    {
        $data = StockLevel::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('stock_levels.quantity', '>', 0)
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.selling_price', 'u.abbreviation')
            ->selectRaw(
                "p.name as name, p.sku as sku, u.abbreviation as unit, p.selling_price as price, "
                ."SUM(CASE WHEN stock_levels.location_type = 'block' THEN stock_levels.quantity ELSE 0 END) as in_godowns, "
                ."SUM(CASE WHEN stock_levels.location_type = 'outlet' THEN stock_levels.quantity ELSE 0 END) as in_outlets, "
                .'SUM(stock_levels.quantity) as total, SUM(stock_levels.quantity * pb.unit_cost) as cost_value'
            )
            ->orderBy('p.name')->get();

        $rows = $data->map(fn ($r) => [
            'name' => $r->name,
            'sku' => $r->sku,
            'unit' => $r->unit,
            'in_godowns' => (float) $r->in_godowns,
            'in_outlets' => (float) $r->in_outlets,
            'total' => (float) $r->total,
            'cost_value' => (float) $r->cost_value,
            'sell_value' => round((float) $r->total * (float) $r->price, 2),
        ])->all();

        return [
            'title' => 'Stock summary and value',
            'subtitle' => 'Stock on hand today · '.now()->format('d M Y'),
            'columns' => [
                self::col('name', 'Product'), self::col('sku', 'SKU'), self::col('unit', 'Unit'),
                self::col('in_godowns', 'In godowns', 'qty'), self::col('in_outlets', 'In outlets', 'qty'),
                self::col('total', 'Total', 'qty'), self::col('cost_value', 'Cost value', 'money'),
                self::col('sell_value', 'Selling value', 'money'),
            ],
            'rows' => $rows,
            'totals' => $rows ? ['name' => 'Total'] + self::totals($rows, ['cost_value', 'sell_value']) : null,
            'note' => null,
        ];
    }

    private static function locationJoins($query)
    {
        return $query
            ->leftJoin('blocks as b', function ($j) {
                $j->on('b.id', '=', 'stock_levels.location_id')->where('stock_levels.location_type', '=', 'block');
            })
            ->leftJoin('godowns as g', 'g.id', '=', 'b.godown_id')
            ->leftJoin('outlets as o', function ($j) {
                $j->on('o.id', '=', 'stock_levels.location_id')->where('stock_levels.location_type', '=', 'outlet');
            });
    }

    private static function expiry(array $f): array
    {
        $limit = now()->addDays($f['days'])->toDateString();

        $query = StockLevel::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->join('units as u', 'u.id', '=', 'p.base_unit_id');

        $data = self::locationJoins($query)
            ->where('stock_levels.quantity', '>', 0)
            ->whereNotNull('pb.expiry_date')
            ->whereDate('pb.expiry_date', '<=', $limit)
            ->orderBy('pb.expiry_date')
            ->select(
                'p.name as name', 'pb.batch_no', 'pb.expiry_date', 'u.abbreviation as unit',
                'stock_levels.quantity', 'stock_levels.location_type',
                'b.code as block_code', 'g.name as godown_name', 'o.name as outlet_name'
            )->get();

        $today = now()->startOfDay();
        $rows = $data->map(function ($r) use ($today) {
            $expiry = Carbon::parse($r->expiry_date)->startOfDay();
            $left = (int) $today->diffInDays($expiry, false);

            return [
                'name' => $r->name,
                'batch' => $r->batch_no,
                'expiry' => $expiry->format('d M Y'),
                'left' => $left,
                'status' => $left < 0 ? 'Expired' : ($left === 0 ? 'Expires today' : 'Expires soon'),
                'location' => $r->location_type === 'block'
                    ? $r->godown_name.' · Block '.$r->block_code
                    : 'Outlet: '.$r->outlet_name,
                'qty' => (float) $r->quantity,
                'unit' => $r->unit,
            ];
        })->all();

        return [
            'title' => 'Expiring and expired stock',
            'subtitle' => "Expired, or expiring within {$f['days']} days · ".now()->format('d M Y'),
            'columns' => [
                self::col('name', 'Product'), self::col('batch', 'Batch'), self::col('expiry', 'Expiry date'),
                self::col('left', 'Days left', 'int'), self::col('status', 'Status'), self::col('location', 'Location'),
                self::col('qty', 'Quantity', 'qty'), self::col('unit', 'Unit'),
            ],
            'rows' => $rows,
            'totals' => null,
            'note' => 'A negative number of days means the batch has already expired.',
        ];
    }

    private static function lowStock(): array
    {
        $totals = StockLevel::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
            ->groupBy('pb.product_id')
            ->selectRaw('pb.product_id as product_id, SUM(stock_levels.quantity) as total')
            ->pluck('total', 'product_id');

        $rows = Product::with('baseUnit')
            ->where('is_active', true)->where('reorder_level', '>', 0)
            ->orderBy('name')->get()
            ->filter(fn ($p) => (float) ($totals[$p->id] ?? 0) <= (float) $p->reorder_level)
            ->map(function ($p) use ($totals) {
                $have = (float) ($totals[$p->id] ?? 0);

                return [
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->baseUnit?->abbreviation,
                    'have' => $have,
                    'reorder' => (float) $p->reorder_level,
                    'short' => round((float) $p->reorder_level - $have, 3),
                ];
            })->values()->all();

        return [
            'title' => 'Low stock',
            'subtitle' => 'Products at or below their reorder level · '.now()->format('d M Y'),
            'columns' => [
                self::col('name', 'Product'), self::col('sku', 'SKU'), self::col('unit', 'Unit'),
                self::col('have', 'In stock', 'qty'), self::col('reorder', 'Reorder level', 'qty'),
                self::col('short', 'Below level by', 'qty'),
            ],
            'rows' => $rows,
            'totals' => null,
            'note' => null,
        ];
    }

    private static function stockMovements(array $f): array
    {
        $labels = [
            'receipt' => 'Goods received', 'transfer_out' => 'Transfer out', 'transfer_in' => 'Transfer in',
            'sale' => 'Sale', 'sale_return' => 'Sale returned', 'adjustment' => 'Adjustment', 'wastage' => 'Wastage',
        ];

        $query = StockMovement::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_movements.batch_id')
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->join('users as us', 'us.id', '=', 'stock_movements.user_id')
            ->leftJoin('blocks as b', function ($j) {
                $j->on('b.id', '=', 'stock_movements.location_id')->where('stock_movements.location_type', '=', 'block');
            })
            ->leftJoin('godowns as g', 'g.id', '=', 'b.godown_id')
            ->leftJoin('outlets as o', function ($j) {
                $j->on('o.id', '=', 'stock_movements.location_id')->where('stock_movements.location_type', '=', 'outlet');
            });

        $data = $query
            ->whereDate('stock_movements.created_at', '>=', $f['from'])->whereDate('stock_movements.created_at', '<=', $f['to'])
            ->orderByDesc('stock_movements.id')
            ->limit(1000)
            ->select(
                'stock_movements.created_at', 'stock_movements.movement_type', 'stock_movements.quantity',
                'stock_movements.location_type', 'stock_movements.reason',
                'p.name as product', 'pb.batch_no', 'us.name as user_name',
                'b.code as block_code', 'g.name as godown_name', 'o.name as outlet_name'
            )->get();

        $rows = $data->map(fn ($r) => [
            'when' => Carbon::parse($r->created_at)->format('d M Y H:i'),
            'type' => $labels[$r->movement_type] ?? $r->movement_type,
            'product' => $r->product,
            'batch' => $r->batch_no,
            'location' => $r->location_type === 'block' ? $r->godown_name.' · Block '.$r->block_code : 'Outlet: '.$r->outlet_name,
            'qty' => (float) $r->quantity,
            'user' => $r->user_name,
            'reason' => $r->reason ?: '',
        ])->all();

        return [
            'title' => 'Stock movements',
            'subtitle' => Carbon::parse($f['from'])->format('d M Y').' to '.Carbon::parse($f['to'])->format('d M Y'),
            'columns' => [
                self::col('when', 'Date and time'), self::col('type', 'Type'), self::col('product', 'Product'),
                self::col('batch', 'Batch'), self::col('location', 'Location'), self::col('qty', 'Quantity', 'qty'),
                self::col('user', 'By'), self::col('reason', 'Reason'),
            ],
            'rows' => $rows,
            'totals' => null,
            'note' => count($rows) >= 1000
                ? 'Showing the latest 1,000 movements. Narrow the dates to see the rest.'
                : 'A plus sign means stock came in, a minus sign means it went out.',
        ];
    }

    // ---------- Debtors ----------

    private static function debtors(): array
    {
        $data = CustomerLedger::query()
            ->join('customers', 'customers.id', '=', 'customer_ledger.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'customers.credit_limit')
            ->havingRaw("SUM(CASE WHEN customer_ledger.entry_type = 'debit' THEN customer_ledger.amount ELSE -customer_ledger.amount END) > 0.004")
            ->selectRaw(
                "customers.name as name, customers.phone as phone, customers.credit_limit as credit_limit, "
                ."SUM(CASE WHEN customer_ledger.entry_type = 'debit' THEN customer_ledger.amount ELSE -customer_ledger.amount END) as balance, "
                .'MAX(customer_ledger.created_at) as last_activity'
            )
            ->orderByDesc('balance')->get();

        $rows = $data->map(fn ($r) => [
            'name' => $r->name,
            'phone' => $r->phone ?: '',
            'limit' => (float) $r->credit_limit,
            'owes' => (float) $r->balance,
            'last' => Carbon::parse($r->last_activity)->format('d M Y'),
        ])->all();

        return [
            'title' => 'Customers who owe money',
            'subtitle' => 'Balances as at '.now()->format('d M Y'),
            'columns' => [
                self::col('name', 'Customer'), self::col('phone', 'Phone'), self::col('limit', 'Credit limit', 'money'),
                self::col('owes', 'Owes', 'money'), self::col('last', 'Last activity'),
            ],
            'rows' => $rows,
            'totals' => $rows ? ['name' => 'Total owed'] + self::totals($rows, ['owes']) : null,
            'note' => null,
        ];
    }
}
