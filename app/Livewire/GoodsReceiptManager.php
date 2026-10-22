<?php

namespace App\Livewire;

use App\Models\Block;
use App\Models\Godown;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class GoodsReceiptManager extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public string $supplierId = '';
    public string $godownId = '';
    public string $receivedDate = '';
    public string $reference = '';
    public string $notes = '';
    public array $lines = [];

    public bool $overrideCapacity = false;
    public string $capacityWarning = '';

    public ?int $viewReceiptId = null;
    public string $message = '';

    private const ROLES = ['admin', 'godown_keeper'];

    public function mount(): void
    {
        $this->ensureAllowed();
    }

    private function ensureAllowed(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
    }

    private function emptyLine(): array
    {
        return ['productId' => '', 'blockId' => '', 'batchNo' => '', 'expiry' => '', 'quantity' => '', 'unitCost' => ''];
    }

    private function storageLabels(): array
    {
        return ['dry' => 'a dry store', 'cold' => 'a cold room', 'freezer' => 'a freezer'];
    }

    private function allowedGodowns()
    {
        $user = auth()->user();
        $query = Godown::where('is_active', true)->orderBy('code');

        if ($user->role === 'godown_keeper' && $user->godown_id) {
            $query->where('id', $user->godown_id);
        }

        return $query->get();
    }

    // ---------- Form ----------

    public function create(): void
    {
        $this->ensureAllowed();
        $this->resetForm();

        $godowns = $this->allowedGodowns();
        $this->godownId = $godowns->count() === 1 ? (string) $godowns->first()->id : '';
        $this->receivedDate = now()->toDateString();
        $this->lines = [$this->emptyLine()];
        $this->showForm = true;
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if (! $this->lines) {
            $this->lines = [$this->emptyLine()];
        }
    }

    public function updatedGodownId(): void
    {
        foreach ($this->lines as $i => $line) {
            $this->lines[$i]['blockId'] = '';
        }
        $this->overrideCapacity = false;
        $this->capacityWarning = '';
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', (string) $key, 2);
        $index = $parts[0];
        $field = $parts[1] ?? null;

        if ($field === 'productId' && $value !== '' && isset($this->lines[$index])) {
            $product = Product::find((int) $value);

            if ($product) {
                if (($this->lines[$index]['batchNo'] ?? '') === '') {
                    $this->lines[$index]['batchNo'] = $product->sku.'-'.now()->format('ymd');
                }
                if (($this->lines[$index]['unitCost'] ?? '') === '') {
                    $this->lines[$index]['unitCost'] = (string) (float) $product->cost_price;
                }
            }
        }

        $this->capacityWarning = '';
        $this->overrideCapacity = false;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureAllowed();

        $this->validate([
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'godownId' => ['required', 'integer', 'exists:godowns,id'],
            'receivedDate' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.productId' => ['required', 'integer', 'exists:products,id'],
            'lines.*.blockId' => ['required', 'integer', 'exists:blocks,id'],
            'lines.*.batchNo' => ['required', 'string', 'max:60'],
            'lines.*.expiry' => ['nullable', 'date', 'after_or_equal:today'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unitCost' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'godownId' => 'godown',
            'receivedDate' => 'received date',
            'lines.*.productId' => 'product',
            'lines.*.blockId' => 'block',
            'lines.*.batchNo' => 'batch number',
            'lines.*.expiry' => 'expiry date',
            'lines.*.quantity' => 'quantity',
            'lines.*.unitCost' => 'unit cost',
        ]);

        // The user may only receive into a godown they are allowed to use.
        abort_unless($this->allowedGodowns()->contains('id', (int) $this->godownId), 403);

        $products = Product::with('baseUnit')
            ->whereIn('id', collect($this->lines)->pluck('productId')->map(fn ($v) => (int) $v))
            ->get()->keyBy('id');
        $blocks = Block::whereIn('id', collect($this->lines)->pluck('blockId')->map(fn ($v) => (int) $v))
            ->get()->keyBy('id');

        $labels = $this->storageLabels();
        $hasError = false;
        $seenBatches = [];

        foreach ($this->lines as $i => $line) {
            $product = $products->get((int) $line['productId']);
            $block = $blocks->get((int) $line['blockId']);

            if (! $product || ! $block) {
                continue;
            }

            if (! $product->is_active) {
                $this->addError("lines.$i.productId", 'This product is inactive.');
                $hasError = true;
            }

            if ($block->godown_id !== (int) $this->godownId || ! $block->is_active) {
                $this->addError("lines.$i.blockId", 'Choose an active block of the selected godown.');
                $hasError = true;
            } elseif ($block->storage_type !== $product->storage_type) {
                $this->addError(
                    "lines.$i.blockId",
                    "{$product->name} is kept in {$labels[$product->storage_type]}, but block {$block->code} is {$labels[$block->storage_type]}."
                );
                $hasError = true;
            }

            $batchNo = trim($line['batchNo']);
            $expiry = trim((string) $line['expiry']) ?: null;

            if ($product->is_perishable && $expiry === null) {
                $this->addError("lines.$i.expiry", 'Expiry date is required for a perishable product.');
                $hasError = true;
            }

            // A batch number belongs to one expiry date.
            $key = $product->id.'|'.$batchNo;
            $existing = ProductBatch::where('product_id', $product->id)->where('batch_no', $batchNo)->first();
            $known = $existing
                ? ($existing->expiry_date ? $existing->expiry_date->format('Y-m-d') : null)
                : ($seenBatches[$key] ?? null);

            if ($expiry !== null && $known !== null && $expiry !== $known) {
                $this->addError("lines.$i.expiry", "Batch {$batchNo} already has the expiry date {$known}.");
                $hasError = true;
            }

            if (! isset($seenBatches[$key]) || ($seenBatches[$key] === null && $expiry !== null)) {
                $seenBatches[$key] = $expiry;
            }
        }

        if ($hasError) {
            return;
        }

        // Capacity check per block (soft: can be overridden)
        $incoming = [];
        foreach ($this->lines as $line) {
            $incoming[(int) $line['blockId']] = ($incoming[(int) $line['blockId']] ?? 0) + (float) $line['quantity'];
        }

        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
        $over = [];

        foreach ($incoming as $blockId => $qty) {
            $block = $blocks->get($blockId);

            if (! $block || $block->capacity === null) {
                continue;
            }

            $used = (float) StockLevel::where('location_type', 'block')->where('location_id', $blockId)->sum('quantity');

            if ($used + $qty > (float) $block->capacity) {
                $unit = $block->capacity_unit ? ' '.$block->capacity_unit : '';
                $over[] = "Block {$block->code} holds {$fmt($used)}{$unit} and you are adding {$fmt($qty)}, but its capacity is {$fmt($block->capacity)}{$unit}.";
            }
        }

        if ($over && ! $this->overrideCapacity) {
            $this->capacityWarning = implode(' ', $over);

            return;
        }

        $this->capacityWarning = '';

        $receipt = DB::transaction(function () use ($products) {
            $receipt = GoodsReceipt::create([
                'receipt_no' => 'TMP-'.Str::uuid(),
                'supplier_id' => $this->supplierId !== '' ? (int) $this->supplierId : null,
                'godown_id' => (int) $this->godownId,
                'received_by' => auth()->id(),
                'received_date' => $this->receivedDate,
                'reference' => trim($this->reference) ?: null,
                'notes' => trim($this->notes) ?: null,
            ]);

            $total = 0;

            foreach ($this->lines as $line) {
                $product = $products->get((int) $line['productId']);
                $quantity = (float) $line['quantity'];
                $cost = (float) ($line['unitCost'] ?: 0);
                $blockId = (int) $line['blockId'];

                $batch = ProductBatch::firstOrCreate(
                    ['product_id' => $product->id, 'batch_no' => trim($line['batchNo'])],
                    [
                        'supplier_id' => $receipt->supplier_id,
                        'received_date' => $this->receivedDate,
                        'expiry_date' => trim((string) $line['expiry']) ?: null,
                        'unit_cost' => $cost,
                    ]
                );

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'batch_id' => $batch->id,
                    'block_id' => $blockId,
                    'quantity' => $quantity,
                    'unit_cost' => $cost,
                ]);

                $level = StockLevel::firstOrNew([
                    'batch_id' => $batch->id,
                    'location_type' => 'block',
                    'location_id' => $blockId,
                ]);
                $level->quantity = (float) ($level->quantity ?? 0) + $quantity;
                $level->save();

                StockMovement::create([
                    'batch_id' => $batch->id,
                    'movement_type' => 'receipt',
                    'location_type' => 'block',
                    'location_id' => $blockId,
                    'quantity' => $quantity,
                    'reference_type' => 'goods_receipt',
                    'reference_id' => $receipt->id,
                    'user_id' => auth()->id(),
                ]);

                $total += $quantity * $cost;
            }

            $receipt->update([
                'receipt_no' => 'GR-'.str_pad((string) $receipt->id, 5, '0', STR_PAD_LEFT),
                'total_cost' => $total,
            ]);

            return $receipt;
        });

        $this->message = "Goods received and added to stock: {$receipt->receipt_no}.";
        $this->resetForm(keepMessage: true);
        $this->viewReceiptId = $receipt->id;
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset([
            'showForm', 'supplierId', 'godownId', 'receivedDate', 'reference', 'notes',
            'lines', 'overrideCapacity', 'capacityWarning',
        ]);
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    // ---------- Details ----------

    public function viewReceipt(int $id): void
    {
        $this->viewReceiptId = GoodsReceipt::findOrFail($id)->id;
    }

    public function closeReceipt(): void
    {
        $this->viewReceiptId = null;
    }

    public function render()
    {
        $user = auth()->user();
        $restrictGodown = $user->role === 'godown_keeper' && $user->godown_id;

        $receipts = GoodsReceipt::with(['supplier', 'godown', 'receiver'])
            ->when($restrictGodown, fn ($q) => $q->where('godown_id', $user->godown_id))
            ->orderByDesc('id')
            ->paginate(10);

        $viewReceipt = null;
        if ($this->viewReceiptId) {
            $viewReceipt = GoodsReceipt::with(['supplier', 'godown', 'receiver', 'items.batch.product.baseUnit', 'items.block'])
                ->when($restrictGodown, fn ($q) => $q->where('godown_id', $user->godown_id))
                ->find($this->viewReceiptId);
        }

        return view('livewire.goods-receipt-manager', [
            'receipts' => $receipts,
            'viewReceipt' => $viewReceipt,
            'godowns' => $this->allowedGodowns(),
            'blocks' => $this->godownId !== ''
                ? Block::where('godown_id', (int) $this->godownId)->where('is_active', true)->orderBy('code')->get()
                : collect(),
            'products' => Product::with('baseUnit')->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
