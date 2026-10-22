<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockLevel;
use App\Models\Unit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ProductManager extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'active';
    public bool $lowOnly = false;

    // Product form
    public bool $showForm = false;
    public ?int $productId = null;
    public string $sku = '';
    public string $barcode = '';
    public string $name = '';
    public string $categoryId = '';
    public string $newCategory = '';
    public string $unitId = '';
    public string $newUnit = '';
    public string $newUnitAbbr = '';
    public string $costPrice = '';
    public string $sellingPrice = '';
    public string $reorderLevel = '';
    public string $storageType = 'dry';
    public bool $isPerishable = true;

    // Where-is-this-product panel
    public ?int $viewProductId = null;

    public string $message = '';

    private const EDIT_ROLES = ['admin', 'godown_keeper'];

    private function ensureCanEdit(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::EDIT_ROLES, true), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingLowOnly(): void
    {
        $this->resetPage();
    }

    // ---------- Product form ----------

    public function create(): void
    {
        $this->ensureCanEdit();
        $this->resetForm();
        $this->sku = 'SKU'.str_pad((string) ((int) Product::max('id') + 1), 4, '0', STR_PAD_LEFT);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->ensureCanEdit();
        $product = Product::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->productId = $product->id;
        $this->sku = $product->sku;
        $this->barcode = (string) $product->barcode;
        $this->name = $product->name;
        $this->categoryId = $product->category_id ? (string) $product->category_id : '';
        $this->newCategory = '';
        $this->unitId = (string) $product->base_unit_id;
        $this->newUnit = '';
        $this->newUnitAbbr = '';
        $this->costPrice = (string) (float) $product->cost_price;
        $this->sellingPrice = (string) (float) $product->selling_price;
        $this->reorderLevel = (string) (float) $product->reorder_level;
        $this->storageType = $product->storage_type;
        $this->isPerishable = (bool) $product->is_perishable;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureCanEdit();
        $this->sku = strtoupper(trim($this->sku));
        $this->barcode = trim($this->barcode);

        $data = $this->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($this->productId)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($this->productId)],
            'name' => ['required', 'string', 'max:150'],
            'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'newCategory' => ['nullable', 'string', 'max:100'],
            'unitId' => ['nullable', 'integer', 'exists:units,id'],
            'newUnit' => ['nullable', 'string', 'max:50'],
            'newUnitAbbr' => ['nullable', 'string', 'max:10'],
            'costPrice' => ['nullable', 'numeric', 'min:0'],
            'sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'reorderLevel' => ['nullable', 'numeric', 'min:0'],
            'storageType' => ['required', Rule::in(['dry', 'cold', 'freezer'])],
            'isPerishable' => ['boolean'],
        ]);

        $newUnit = trim((string) ($data['newUnit'] ?? ''));
        $newCategory = trim((string) ($data['newCategory'] ?? ''));

        if ($newUnit === '' && empty($data['unitId'])) {
            $this->addError('unitId', 'Choose a unit, or type a new one.');

            return;
        }

        $unit = $newUnit !== ''
            ? Unit::firstOrCreate(
                ['name' => $newUnit],
                ['abbreviation' => trim((string) ($data['newUnitAbbr'] ?? '')) ?: Str::limit($newUnit, 10, '')]
            )
            : Unit::findOrFail((int) $data['unitId']);

        // Stock is counted in the unit, so it cannot change once stock exists.
        if ($this->productId) {
            $existing = Product::findOrFail($this->productId);

            if ($existing->base_unit_id !== $unit->id && ProductBatch::where('product_id', $existing->id)->exists()) {
                $this->addError('unitId', 'The unit cannot be changed once this product has stock.');

                return;
            }
        }

        $categoryId = null;
        if ($newCategory !== '') {
            $categoryId = Category::firstOrCreate(['name' => $newCategory])->id;
        } elseif (! empty($data['categoryId'])) {
            $categoryId = (int) $data['categoryId'];
        }

        $fields = [
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?: null,
            'name' => $data['name'],
            'category_id' => $categoryId,
            'base_unit_id' => $unit->id,
            'cost_price' => (float) ($data['costPrice'] ?: 0),
            'selling_price' => (float) ($data['sellingPrice'] ?: 0),
            'reorder_level' => (float) ($data['reorderLevel'] ?: 0),
            'storage_type' => $data['storageType'],
            'is_perishable' => (bool) $data['isPerishable'],
        ];

        if ($this->productId) {
            Product::findOrFail($this->productId)->update($fields);
            $this->message = 'Product updated.';
        } else {
            Product::create($fields);
            $this->message = 'Product added.';
        }

        $this->resetForm(keepMessage: true);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $this->ensureCanEdit();
        $product = Product::findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);
        $this->message = $product->is_active ? 'Product activated.' : 'Product deactivated.';
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset([
            'showForm', 'productId', 'sku', 'barcode', 'name', 'categoryId', 'newCategory',
            'unitId', 'newUnit', 'newUnitAbbr', 'costPrice', 'sellingPrice', 'reorderLevel',
        ]);
        $this->storageType = 'dry';
        $this->isPerishable = true;
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    // ---------- Where is this product? ----------

    public function viewStock(int $id): void
    {
        $this->viewProductId = Product::findOrFail($id)->id;
    }

    public function closeStock(): void
    {
        $this->viewProductId = null;
    }

    public function render()
    {
        // Total quantity of every product across all blocks and outlets
        $totals = StockLevel::query()
            ->join('product_batches', 'product_batches.id', '=', 'stock_levels.batch_id')
            ->groupBy('product_batches.product_id')
            ->selectRaw('product_batches.product_id as product_id, SUM(stock_levels.quantity) as total')
            ->pluck('total', 'product_id');

        $active = Product::where('is_active', true)->get(['id', 'reorder_level']);
        $lowIds = $active
            ->filter(fn ($p) => (float) $p->reorder_level > 0 && (float) ($totals[$p->id] ?? 0) <= (float) $p->reorder_level)
            ->pluck('id');
        $outCount = $active->filter(fn ($p) => (float) ($totals[$p->id] ?? 0) <= 0)->count();

        $term = trim($this->search);

        $products = Product::with(['category', 'baseUnit'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%");
                });
            })
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->lowOnly, fn ($q) => $q->whereIn('id', $lowIds))
            ->orderBy('name')
            ->paginate(15);

        $viewProduct = null;
        $locations = collect();

        if ($this->viewProductId) {
            $viewProduct = Product::with('baseUnit')->find($this->viewProductId);

            if ($viewProduct) {
                $locations = StockLevel::query()
                    ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
                    ->leftJoin('blocks as b', function ($join) {
                        $join->on('b.id', '=', 'stock_levels.location_id')
                            ->where('stock_levels.location_type', '=', 'block');
                    })
                    ->leftJoin('godowns as g', 'g.id', '=', 'b.godown_id')
                    ->leftJoin('outlets as o', function ($join) {
                        $join->on('o.id', '=', 'stock_levels.location_id')
                            ->where('stock_levels.location_type', '=', 'outlet');
                    })
                    ->where('pb.product_id', $viewProduct->id)
                    ->where('stock_levels.quantity', '>', 0)
                    ->orderBy('pb.expiry_date')
                    ->select(
                        'stock_levels.quantity',
                        'stock_levels.location_type',
                        'pb.batch_no',
                        'pb.expiry_date',
                        'b.code as block_code',
                        'g.name as godown_name',
                        'o.name as outlet_name'
                    )
                    ->get();
            }
        }

        return view('livewire.product-manager', [
            'products' => $products,
            'totals' => $totals,
            'lowCount' => $lowIds->count(),
            'outCount' => $outCount,
            'activeCount' => $active->count(),
            'categories' => Category::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'viewProduct' => $viewProduct,
            'locations' => $locations,
            'canEdit' => in_array(auth()->user()?->role, self::EDIT_ROLES, true),
        ]);
    }
}
