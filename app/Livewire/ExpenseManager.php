<?php

namespace App\Livewire;

use App\Models\Expense;
use App\Models\Outlet;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseManager extends Component
{
    use WithPagination;

    public const CATEGORIES = [
        'Rent', 'Salaries & wages', 'Transport', 'Electricity', 'Water', 'Internet & phone',
        'Repairs & maintenance', 'Packaging', 'Licences & fees', 'Marketing', 'Other',
    ];

    public string $from = '';
    public string $to = '';
    public string $categoryFilter = '';
    public string $outletFilter = '';

    public bool $showForm = false;
    public string $expenseDate = '';
    public string $category = 'Other';
    public string $description = '';
    public string $amount = '';
    public string $outletId = '';

    public string $message = '';

    private const ROLES = ['admin', 'outlet_manager'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    private function ensureAllowed(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOutletFilter(): void
    {
        $this->resetPage();
    }

    private function visible()
    {
        $user = auth()->user();
        $query = Expense::query();

        if ($user->role === 'admin') {
            if ($this->outletFilter === 'general') {
                $query->whereNull('outlet_id');
            } elseif ($this->outletFilter !== '') {
                $query->where('outlet_id', (int) $this->outletFilter);
            }
        } else {
            $query->where('outlet_id', $user->outlet_id ?? 0);
        }

        return $query;
    }

    public function create(): void
    {
        $this->ensureAllowed();
        $this->resetForm();
        $this->expenseDate = now()->toDateString();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureAllowed();
        $user = auth()->user();

        $data = $this->validate([
            'expenseDate' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'outletId' => ['nullable', 'integer', 'exists:outlets,id'],
        ], [], ['expenseDate' => 'date']);

        if ($user->role === 'admin') {
            $outletId = $this->outletId !== '' ? (int) $this->outletId : null;
        } else {
            abort_unless($user->outlet_id, 403);
            $outletId = (int) $user->outlet_id;
        }

        Expense::create([
            'expense_date' => $data['expenseDate'],
            'category' => $data['category'],
            'description' => $data['description'] ?: null,
            'amount' => round((float) $data['amount'], 2),
            'outlet_id' => $outletId,
            'user_id' => $user->id,
        ]);

        $this->message = 'Expense recorded.';
        $this->resetForm(keepMessage: true);
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        Expense::findOrFail($id)->delete();
        $this->message = 'Expense deleted.';
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset(['showForm', 'expenseDate', 'description', 'amount', 'outletId']);
        $this->category = 'Other';
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    public function render()
    {
        $base = $this->visible()
            ->when($this->from !== '', fn ($q) => $q->where('expense_date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->where('expense_date', '<=', $this->to))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category', $this->categoryFilter));

        $user = auth()->user();

        return view('livewire.expense-manager', [
            'expenses' => (clone $base)->with(['outlet', 'user'])->orderByDesc('expense_date')->orderByDesc('id')->paginate(15),
            'total' => (float) (clone $base)->sum('amount'),
            'categories' => self::CATEGORIES,
            'outlets' => $user->role === 'admin' ? Outlet::orderBy('code')->get() : collect(),
            'isAdmin' => $user->role === 'admin',
        ]);
    }
}
