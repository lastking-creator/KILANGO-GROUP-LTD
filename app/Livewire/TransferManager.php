<?php

namespace App\Livewire;

use App\Models\Block;
use App\Models\Godown;
use App\Models\Outlet;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\InsufficientStockException;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class TransferManager extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    // New transfer form
    public bool $showForm = false;
    public string $fromGodownId = '';
    public string $fromBlockId = '';
    public string $toType = 'block';
    public string $toGodownId = '';
    public string $toBlockId = '';
    public string $toOutletId = '';
    public string $notes = '';
    public array $lines = [];

    // Details and receiving
    public ?int $viewTransferId = null;
    public ?int $receiveTransferId = null;
    public array $receiveQuantities = [];
    public bool $overrideCapacity = false;
    public string $capacityWarning = '';

    public string $message = '';
    public string $error = '';

    private const ROLES = ['admin', 'godown_keeper', 'outlet_manager'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
    }

    // ---------- Permissions ----------

    private function canCreate(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'godown_keeper'], true);
    }

    private function ensureCanCreate(): void
    {
        abort_unless($this->canCreate(), 403);
    }

    private function canDispatch(Transfer $transfer): bool
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'godown_keeper'
            && (! $user->godown_id || $user->godown_id === $transfer->fromBlock?->godown_id);
    }

    private function canReceive(Transfer $transfer): bool
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'outlet_manager') {
            return $transfer->to_type === 'outlet' && $user->outlet_id === $transfer->to_id;
        }

        if ($user->role === 'godown_keeper' && $transfer->to_type === 'block') {
            return ! $user->godown_id || Block::find($transfer->to_id)?->godown_id === $user->godown_id;
        }

        return false;
    }

    private function visibleTransfers()
    {
        $user = auth()->user();
        $query = Transfer::query();

        if ($user->role === 'godown_keeper' && $user->godown_id) {
            $blockIds = Block::where('godown_id', $user->godown_id)->pluck('id');

            $query->where(function ($q) use ($blockIds) {
                $q->whereIn('from_block_id', $blockIds)
                    ->orWhere(fn ($q2) => $q2->where('to_type', 'block')->whereIn('to_id', $blockIds));
            });
        } elseif ($user->role === 'outlet_manager') {
            $query->where('to_type', 'outlet')->where('to_id', $user->outlet_id ?? 0);
        }

        return $query;
    }

    private function findVisible(int $id): Transfer
    {
        return $this->visibleTransfers()->with(['fromBlock.godown', 'items'])->findOrFail($id);
    }

    // ---------- Helpers ----------

    private function emptyLine(): array
    {
        return ['batchId' => '', 'quantity' => ''];
    }

    private function sourceGodowns()
    {
        $user = auth()->user();
        $query = Godown::where('is_active', true)->orderBy('code');

        if ($user->role === 'godown_keeper' && $user->godown_id) {
            $query->where('id', $user->godown_id);
        }

        return $query->get();
    }

    private function availableStock(int $blockId)
    {
        return StockLevel::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('stock_levels.location_type', 'block')
            ->where('stock_levels.location_id', $blockId)
            ->where('stock_levels.quantity', '>', 0)
            ->orderByRaw('pb.expiry_date IS NULL, pb.expiry_date')   // soonest expiry first
            ->orderBy('p.name')
            ->select(
                'pb.id as batch_id', 'pb.batch_no', 'pb.expiry_date',
                'p.id as product_id', 'p.name as product_name', 'p.storage_type',
                'u.abbreviation as unit', 'stock_levels.quantity'
            )
            ->get();
    }

    private function storageLabels(): array
    {
        return ['dry' => 'a dry store', 'cold' => 'a cold room', 'freezer' => 'a freezer'];
    }

    private function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    }

    // ---------- New transfer ----------

    public function create(): void
    {
        $this->ensureCanCreate();
        $this->resetForm();

        $godowns = $this->sourceGodowns();
        $this->fromGodownId = $godowns->count() === 1 ? (string) $godowns->first()->id : '';
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

    public function updatedFromGodownId(): void
    {
        $this->fromBlockId = '';
        $this->lines = [$this->emptyLine()];
    }

    public function updatedFromBlockId(): void
    {
        $this->lines = [$this->emptyLine()];
    }

    public function updatedToType(): void
    {
        $this->toGodownId = '';
        $this->toBlockId = '';
        $this->toOutletId = '';
    }

    public function updatedToGodownId(): void
    {
        $this->toBlockId = '';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureCanCreate();
        $this->message = '';
        $this->error = '';

        $this->validate([
            'fromGodownId' => ['required', 'integer', 'exists:godowns,id'],
            'fromBlockId' => ['required', 'integer', 'exists:blocks,id'],
            'toType' => ['required', 'in:block,outlet'],
            'toBlockId' => $this->toType === 'block' ? ['required', 'integer', 'exists:blocks,id'] : ['nullable'],
            'toOutletId' => $this->toType === 'outlet' ? ['required', 'integer', 'exists:outlets,id'] : ['nullable'],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.batchId' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [], [
            'fromGodownId' => 'source godown',
            'fromBlockId' => 'source block',
            'toBlockId' => 'destination block',
            'toOutletId' => 'destination outlet',
            'lines.*.batchId' => 'product',
            'lines.*.quantity' => 'quantity',
        ]);

        $user = auth()->user();
        abort_unless($this->sourceGodowns()->contains('id', (int) $this->fromGodownId), 403);

        $fromBlock = Block::findOrFail((int) $this->fromBlockId);
        if ($fromBlock->godown_id !== (int) $this->fromGodownId) {
            $this->addError('fromBlockId', 'This block is not in the chosen godown.');

            return;
        }

        $toBlock = null;
        if ($this->toType === 'block') {
            $toBlock = Block::findOrFail((int) $this->toBlockId);

            if ($toBlock->id === $fromBlock->id) {
                $this->addError('toBlockId', 'The destination must be a different block.');

                return;
            }
            if (! $toBlock->is_active) {
                $this->addError('toBlockId', 'This block is inactive.');

                return;
            }
        } else {
            if (! Outlet::where('id', (int) $this->toOutletId)->where('is_active', true)->exists()) {
                $this->addError('toOutletId', 'This outlet is inactive.');

                return;
            }
        }

        $available = $this->availableStock($fromBlock->id)->keyBy('batch_id');
        $labels = $this->storageLabels();
        $hasError = false;
        $seen = [];

        foreach ($this->lines as $i => $line) {
            $row = $available->get((int) $line['batchId']);

            if (! $row) {
                $this->addError("lines.$i.batchId", 'This product has no stock in the source block.');
                $hasError = true;

                continue;
            }

            if (isset($seen[$row->batch_id])) {
                $this->addError("lines.$i.batchId", 'This batch is already in the list. Combine the quantities on one line.');
                $hasError = true;

                continue;
            }
            $seen[$row->batch_id] = true;

            if ((float) $line['quantity'] > (float) $row->quantity + 0.0005) {
                $this->addError("lines.$i.quantity", 'Only '.$this->fmt($row->quantity).' '.$row->unit.' available.');
                $hasError = true;
            }

            if ($toBlock && $toBlock->storage_type !== $row->storage_type) {
                $this->addError(
                    "lines.$i.batchId",
                    "{$row->product_name} is kept in {$labels[$row->storage_type]}, but block {$toBlock->code} is {$labels[$toBlock->storage_type]}."
                );
                $hasError = true;
            }
        }

        if ($hasError) {
            return;
        }

        $transfer = DB::transaction(function () use ($fromBlock, $user) {
            $transfer = Transfer::create([
                'transfer_no' => 'TMP-'.Str::uuid(),
                'from_block_id' => $fromBlock->id,
                'to_type' => $this->toType,
                'to_id' => $this->toType === 'block' ? (int) $this->toBlockId : (int) $this->toOutletId,
                'status' => 'pending',
                'requested_by' => $user->id,
                'notes' => trim($this->notes) ?: null,
            ]);

            foreach ($this->lines as $line) {
                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'batch_id' => (int) $line['batchId'],
                    'quantity' => (float) $line['quantity'],
                ]);
            }

            $transfer->update(['transfer_no' => 'TR-'.str_pad((string) $transfer->id, 5, '0', STR_PAD_LEFT)]);

            return $transfer;
        });

        $this->resetForm();
        $this->message = "Transfer {$transfer->transfer_no} created. Stock has not moved yet. Dispatch it when the goods leave.";
        $this->viewTransferId = $transfer->id;
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm', 'fromGodownId', 'fromBlockId', 'toGodownId', 'toBlockId', 'toOutletId', 'notes', 'lines',
        ]);
        $this->toType = 'block';
        $this->resetValidation();
    }

    // ---------- Workflow ----------

    public function viewTransfer(int $id): void
    {
        $this->viewTransferId = $this->findVisible($id)->id;
        $this->receiveTransferId = null;
        $this->capacityWarning = '';
        $this->overrideCapacity = false;
        $this->message = '';
        $this->error = '';
    }

    public function closeTransfer(): void
    {
        $this->viewTransferId = null;
        $this->receiveTransferId = null;
    }

    public function dispatchTransfer(int $id): void
    {
        $this->message = '';
        $this->error = '';

        $transfer = $this->findVisible($id);
        abort_unless($this->canDispatch($transfer), 403);

        if ($transfer->status !== 'pending') {
            $this->error = 'Only a pending transfer can be dispatched.';

            return;
        }

        try {
            DB::transaction(function () use ($transfer) {
                $stock = app(StockService::class);

                foreach ($transfer->items as $item) {
                    $stock->change(
                        $item->batch_id, 'block', $transfer->from_block_id, -(float) $item->quantity,
                        'transfer_out', 'transfer', $transfer->id, auth()->id()
                    );
                }

                $transfer->update([
                    'status' => 'in_transit',
                    'dispatched_by' => auth()->id(),
                    'dispatched_at' => now(),
                ]);
            });
        } catch (InsufficientStockException $e) {
            $this->error = 'Cannot dispatch: the source block no longer has enough stock for every line. Check the block, or cancel this transfer and create a new one.';

            return;
        }

        $this->message = "{$transfer->transfer_no} dispatched. The stock has left the source block and is now in transit.";
    }

    public function cancelTransfer(int $id): void
    {
        $this->message = '';
        $this->error = '';

        $transfer = $this->findVisible($id);
        abort_unless($this->canDispatch($transfer), 403);

        if ($transfer->status !== 'pending') {
            $this->error = 'Only a pending transfer can be cancelled.';

            return;
        }

        $transfer->update(['status' => 'cancelled']);
        $this->message = "{$transfer->transfer_no} cancelled.";
    }

    public function startReceive(int $id): void
    {
        $this->message = '';
        $this->error = '';

        $transfer = $this->findVisible($id);
        abort_unless($this->canReceive($transfer), 403);

        if ($transfer->status !== 'in_transit') {
            $this->error = 'Only a transfer that is in transit can be received.';

            return;
        }

        $this->receiveTransferId = $transfer->id;
        $this->receiveQuantities = [];
        foreach ($transfer->items as $item) {
            $this->receiveQuantities[$item->id] = (string) (float) $item->quantity;
        }
        $this->overrideCapacity = false;
        $this->capacityWarning = '';
        $this->resetValidation();
    }

    public function cancelReceive(): void
    {
        $this->receiveTransferId = null;
        $this->capacityWarning = '';
        $this->overrideCapacity = false;
    }

    public function confirmReceive(): void
    {
        $this->message = '';
        $this->error = '';

        abort_unless($this->receiveTransferId, 422);
        $transfer = $this->findVisible($this->receiveTransferId);
        abort_unless($this->canReceive($transfer), 403);

        if ($transfer->status !== 'in_transit') {
            $this->error = 'This transfer is no longer in transit.';
            $this->receiveTransferId = null;

            return;
        }

        $this->validate(['receiveQuantities.*' => ['required', 'numeric', 'min:0']], [], ['receiveQuantities.*' => 'received quantity']);

        $transfer->load('items.batch.product');
        $hasError = false;
        $incoming = 0;

        foreach ($transfer->items as $item) {
            $received = (float) ($this->receiveQuantities[$item->id] ?? 0);

            if ($received > (float) $item->quantity + 0.0005) {
                $this->addError("receiveQuantities.{$item->id}", 'You cannot receive more than was sent ('.$this->fmt($item->quantity).').');
                $hasError = true;
            }

            $incoming += $received;
        }

        $toBlock = $transfer->to_type === 'block' ? Block::find($transfer->to_id) : null;
        $labels = $this->storageLabels();

        if ($toBlock) {
            if (! $toBlock->is_active) {
                $this->error = "Block {$toBlock->code} is inactive. Activate it first, or ask an admin.";

                return;
            }

            foreach ($transfer->items as $item) {
                $product = $item->batch->product;
                if ((float) ($this->receiveQuantities[$item->id] ?? 0) > 0 && $toBlock->storage_type !== $product->storage_type) {
                    $this->addError(
                        "receiveQuantities.{$item->id}",
                        "{$product->name} is kept in {$labels[$product->storage_type]}, but block {$toBlock->code} is {$labels[$toBlock->storage_type]}."
                    );
                    $hasError = true;
                }
            }
        }

        if ($hasError) {
            return;
        }

        if ($toBlock && $toBlock->capacity !== null) {
            $used = (float) StockLevel::where('location_type', 'block')->where('location_id', $toBlock->id)->sum('quantity');

            if ($used + $incoming > (float) $toBlock->capacity && ! $this->overrideCapacity) {
                $unit = $toBlock->capacity_unit ? ' '.$toBlock->capacity_unit : '';
                $this->capacityWarning = "Block {$toBlock->code} holds {$this->fmt($used)}{$unit} and you are adding {$this->fmt($incoming)}, but its capacity is {$this->fmt($toBlock->capacity)}{$unit}.";

                return;
            }
        }

        $this->capacityWarning = '';

        DB::transaction(function () use ($transfer) {
            $stock = app(StockService::class);

            foreach ($transfer->items as $item) {
                $received = round((float) ($this->receiveQuantities[$item->id] ?? 0), 3);

                $item->update(['received_quantity' => $received]);

                if ($received > 0) {
                    $stock->change(
                        $item->batch_id, $transfer->to_type, $transfer->to_id, $received,
                        'transfer_in', 'transfer', $transfer->id, auth()->id()
                    );
                }
            }

            $transfer->update([
                'status' => 'received',
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);
        });

        $this->receiveTransferId = null;
        $this->overrideCapacity = false;
        $this->message = "{$transfer->transfer_no} received. The stock is now at its destination.";
    }

    // ---------- Render ----------

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $transfers = $this->visibleTransfers()
            ->with(['fromBlock.godown', 'requester'])
            ->withCount('items')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(10);

        $viewTransfer = null;
        if ($this->viewTransferId) {
            $viewTransfer = $this->visibleTransfers()
                ->with(['fromBlock.godown', 'requester', 'dispatcher', 'receiver', 'items.batch.product.baseUnit'])
                ->find($this->viewTransferId);
        }

        // Readable destination for every transfer shown
        $shown = $transfers->getCollection()->when($viewTransfer, fn ($c) => $c->push($viewTransfer));
        $blockMap = Block::with('godown')->whereIn('id', $shown->where('to_type', 'block')->pluck('to_id'))->get()->keyBy('id');
        $outletMap = Outlet::whereIn('id', $shown->where('to_type', 'outlet')->pluck('to_id'))->get()->keyBy('id');

        $destinations = [];
        foreach ($shown as $t) {
            if ($t->to_type === 'block') {
                $b = $blockMap->get($t->to_id);
                $destinations[$t->id] = $b ? $b->godown->name.' · '.__('Block').' '.$b->code : '-';
            } else {
                $o = $outletMap->get($t->to_id);
                $destinations[$t->id] = $o ? __('Outlet').': '.$o->name : '-';
            }
        }

        // Form data
        $sourceBlocks = $this->fromGodownId !== ''
            ? Block::where('godown_id', (int) $this->fromGodownId)->where('is_active', true)->orderBy('code')->get()
            : collect();

        $sourceStock = $this->fromBlockId !== ''
            ? $this->availableStock((int) $this->fromBlockId)
            : collect();

        $destBlocks = ($this->toType === 'block' && $this->toGodownId !== '')
            ? Block::where('godown_id', (int) $this->toGodownId)->where('is_active', true)->orderBy('code')->get()
            : collect();

        return view('livewire.transfer-manager', [
            'transfers' => $transfers,
            'viewTransfer' => $viewTransfer,
            'destinations' => $destinations,
            'canCreate' => $this->canCreate(),
            'canDispatch' => $viewTransfer ? $this->canDispatch($viewTransfer) : false,
            'canReceive' => $viewTransfer ? $this->canReceive($viewTransfer) : false,
            'sourceGodowns' => $this->sourceGodowns(),
            'allGodowns' => Godown::where('is_active', true)->orderBy('code')->get(),
            'sourceBlocks' => $sourceBlocks,
            'sourceStock' => $sourceStock,
            'destBlocks' => $destBlocks,
            'outlets' => Outlet::where('is_active', true)->orderBy('code')->get(),
        ]);
    }
}
