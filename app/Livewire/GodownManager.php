<?php

namespace App\Livewire;

use App\Models\Block;
use App\Models\Godown;
use App\Models\StockLevel;
use Illuminate\Validation\Rule;
use Livewire\Component;

class GodownManager extends Component
{
    // Godown form
    public bool $showGodownForm = false;
    public ?int $godownId = null;
    public string $code = '';
    public string $name = '';
    public string $location = '';

    // Blocks panel
    public ?int $selectedGodownId = null;
    public bool $showBlockForm = false;
    public ?int $blockId = null;
    public string $blockCode = '';
    public string $blockName = '';
    public string $storageType = 'dry';
    public string $capacity = '';
    public string $capacityUnit = '';

    // Products-in-block panel
    public ?int $viewBlockId = null;

    public string $message = '';

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    // ---------- Godowns ----------

    public function createGodown(): void
    {
        $this->ensureAdmin();
        $this->resetGodownForm();
        $this->showGodownForm = true;
    }

    public function editGodown(int $id): void
    {
        $this->ensureAdmin();
        $godown = Godown::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->godownId = $godown->id;
        $this->code = $godown->code;
        $this->name = $godown->name;
        $this->location = (string) $godown->location;
        $this->showGodownForm = true;
    }

    public function saveGodown(): void
    {
        $this->ensureAdmin();
        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('godowns', 'code')->ignore($this->godownId)],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
        ]);
        $data['location'] = $data['location'] ?: null;

        if ($this->godownId) {
            Godown::findOrFail($this->godownId)->update($data);
            $this->message = 'Godown updated.';
        } else {
            Godown::create($data);
            $this->message = 'Godown added.';
        }

        $this->resetGodownForm(keepMessage: true);
    }

    public function cancelGodown(): void
    {
        $this->resetGodownForm();
    }

    public function toggleGodown(int $id): void
    {
        $this->ensureAdmin();
        $godown = Godown::findOrFail($id);
        $godown->update(['is_active' => ! $godown->is_active]);
        $this->message = $godown->is_active ? 'Godown activated.' : 'Godown deactivated.';
    }

    private function resetGodownForm(bool $keepMessage = false): void
    {
        $this->reset(['showGodownForm', 'godownId', 'code', 'name', 'location']);
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    // ---------- Blocks ----------

    public function selectGodown(int $id): void
    {
        $this->selectedGodownId = Godown::findOrFail($id)->id;
        $this->viewBlockId = null;
        $this->resetBlockForm();
    }

    public function closeBlocks(): void
    {
        $this->selectedGodownId = null;
        $this->viewBlockId = null;
        $this->resetBlockForm();
    }

    public function createBlock(): void
    {
        $this->ensureAdmin();
        $this->resetBlockForm();
        $this->showBlockForm = true;
    }

    public function editBlock(int $id): void
    {
        $this->ensureAdmin();
        $block = Block::where('godown_id', $this->selectedGodownId)->findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->blockId = $block->id;
        $this->blockCode = $block->code;
        $this->blockName = (string) $block->name;
        $this->storageType = $block->storage_type;
        $this->capacity = $block->capacity === null ? '' : (string) (float) $block->capacity;
        $this->capacityUnit = (string) $block->capacity_unit;
        $this->showBlockForm = true;
    }

    public function saveBlock(): void
    {
        $this->ensureAdmin();
        abort_unless($this->selectedGodownId, 422);
        $this->blockCode = strtoupper(trim($this->blockCode));

        $data = $this->validate([
            'blockCode' => [
                'required', 'string', 'max:20',
                Rule::unique('blocks', 'code')
                    ->where('godown_id', $this->selectedGodownId)
                    ->ignore($this->blockId),
            ],
            'blockName' => ['nullable', 'string', 'max:100'],
            'storageType' => ['required', Rule::in(['dry', 'cold', 'freezer'])],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacityUnit' => ['nullable', 'string', 'max:20'],
        ]);

        $fields = [
            'code' => $data['blockCode'],
            'name' => $data['blockName'] ?: null,
            'storage_type' => $data['storageType'],
            'capacity' => ($data['capacity'] ?? '') === '' ? null : $data['capacity'],
            'capacity_unit' => $data['capacityUnit'] ?: null,
        ];

        if ($this->blockId) {
            Block::where('godown_id', $this->selectedGodownId)->findOrFail($this->blockId)->update($fields);
            $this->message = 'Block updated.';
        } else {
            Block::create($fields + ['godown_id' => $this->selectedGodownId]);
            $this->message = 'Block added.';
        }

        $this->resetBlockForm(keepMessage: true);
    }

    public function cancelBlock(): void
    {
        $this->resetBlockForm();
    }

    public function toggleBlock(int $id): void
    {
        $this->ensureAdmin();
        $block = Block::where('godown_id', $this->selectedGodownId)->findOrFail($id);
        $block->update(['is_active' => ! $block->is_active]);
        $this->message = $block->is_active ? 'Block activated.' : 'Block deactivated.';
    }

    private function resetBlockForm(bool $keepMessage = false): void
    {
        $this->reset(['showBlockForm', 'blockId', 'blockCode', 'blockName', 'capacity', 'capacityUnit']);
        $this->storageType = 'dry';
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    // ---------- Products in a block ----------

    public function viewBlockStock(int $id): void
    {
        $this->viewBlockId = Block::where('godown_id', $this->selectedGodownId)->findOrFail($id)->id;
    }

    public function closeBlockStock(): void
    {
        $this->viewBlockId = null;
    }

    public function render()
    {
        $selected = $this->selectedGodownId
            ? Godown::with(['blocks' => fn ($query) => $query->orderBy('code')])->find($this->selectedGodownId)
            : null;

        $used = collect();
        $viewBlock = null;
        $stockRows = collect();

        if ($selected) {
            // Total quantity currently held in each block of this godown
            $used = StockLevel::query()
                ->where('location_type', 'block')
                ->whereIn('location_id', $selected->blocks->pluck('id'))
                ->selectRaw('location_id, SUM(quantity) as total')
                ->groupBy('location_id')
                ->pluck('total', 'location_id');

            if ($this->viewBlockId) {
                $viewBlock = $selected->blocks->firstWhere('id', $this->viewBlockId);
            }

            if ($viewBlock) {
                // Products in this block, with quantity available (all batches added together)
                $stockRows = StockLevel::query()
                    ->join('product_batches', 'product_batches.id', '=', 'stock_levels.batch_id')
                    ->join('products', 'products.id', '=', 'product_batches.product_id')
                    ->join('units', 'units.id', '=', 'products.base_unit_id')
                    ->where('stock_levels.location_type', 'block')
                    ->where('stock_levels.location_id', $viewBlock->id)
                    ->where('stock_levels.quantity', '>', 0)
                    ->groupBy('products.id', 'products.sku', 'products.name', 'units.abbreviation')
                    ->orderBy('products.name')
                    ->selectRaw(
                        'products.id, products.sku, products.name, units.abbreviation as unit, '
                        .'COUNT(stock_levels.id) as batches, SUM(stock_levels.quantity) as quantity, '
                        .'MIN(product_batches.expiry_date) as next_expiry'
                    )
                    ->get();
            }
        }

        return view('livewire.godown-manager', [
            'godowns' => Godown::withCount('blocks')->orderBy('code')->get(),
            'selected' => $selected,
            'used' => $used,
            'viewBlock' => $viewBlock,
            'stockRows' => $stockRows,
            'isAdmin' => auth()->user()?->role === 'admin',
        ]);
    }
}
