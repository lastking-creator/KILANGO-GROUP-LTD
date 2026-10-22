<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';
    public bool $owingOnly = false;

    public bool $showForm = false;
    public ?int $customerId = null;
    public string $name = '';
    public string $phone = '';
    public string $creditLimit = '';

    public ?int $statementCustomerId = null;
    public bool $showPayment = false;
    public string $payAmount = '';
    public string $payMethod = 'cash';
    public string $payReference = '';
    public string $payNote = '';

    public string $message = '';
    public string $error = '';

    private const ROLES = ['admin', 'outlet_manager', 'cashier'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
    }

    private function canEdit(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'outlet_manager'], true);
    }

    private function ensureCanEdit(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOwingOnly(): void
    {
        $this->resetPage();
    }

    // ---------- Customer form ----------

    public function create(): void
    {
        $this->ensureCanEdit();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->ensureCanEdit();
        $customer = Customer::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->error = '';
        $this->customerId = $customer->id;
        $this->name = $customer->name;
        $this->phone = (string) $customer->phone;
        $this->creditLimit = (string) (float) $customer->credit_limit;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureCanEdit();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'creditLimit' => ['nullable', 'numeric', 'min:0'],
        ], [], ['creditLimit' => 'credit limit']);

        $fields = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'credit_limit' => is_numeric($data['creditLimit'] ?? null) ? (float) $data['creditLimit'] : 0,
        ];

        if ($this->customerId) {
            Customer::findOrFail($this->customerId)->update($fields);
            $this->message = 'Customer updated.';
        } else {
            Customer::create($fields);
            $this->message = 'Customer added.';
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
        $customer = Customer::findOrFail($id);
        $customer->update(['is_active' => ! $customer->is_active]);
        $this->message = $customer->is_active ? 'Customer activated.' : 'Customer deactivated.';
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset(['showForm', 'customerId', 'name', 'phone', 'creditLimit']);
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
            $this->error = '';
        }
    }

    // ---------- Statement and payments ----------

    public function openStatement(int $id): void
    {
        $this->statementCustomerId = Customer::findOrFail($id)->id;
        $this->showPayment = false;
        $this->message = '';
        $this->error = '';
    }

    public function closeStatement(): void
    {
        $this->statementCustomerId = null;
        $this->showPayment = false;
    }

    public function startPayment(int $id): void
    {
        $this->statementCustomerId = Customer::findOrFail($id)->id;
        $this->reset(['payAmount', 'payReference', 'payNote']);
        $this->payMethod = 'cash';
        $this->resetValidation();
        $this->message = '';
        $this->error = '';
        $this->showPayment = true;
    }

    public function cancelPayment(): void
    {
        $this->showPayment = false;
        $this->resetValidation();
    }

    public function savePayment(): void
    {
        $this->message = '';
        $this->error = '';
        abort_unless($this->statementCustomerId, 422);

        $customer = Customer::findOrFail($this->statementCustomerId);
        $owes = $customer->outstanding();

        $this->validate([
            'payAmount' => ['required', 'numeric', 'gt:0'],
            'payMethod' => ['required', 'in:cash,mobile_money,card'],
            'payReference' => ['nullable', 'string', 'max:80'],
            'payNote' => ['nullable', 'string', 'max:255'],
        ], [], ['payAmount' => 'amount']);

        $amount = round((float) $this->payAmount, 2);

        if ($owes <= 0.004) {
            $this->error = "{$customer->name} does not owe anything.";

            return;
        }

        if ($amount > $owes + 0.005) {
            $this->addError('payAmount', 'That is more than the customer owes ('.number_format($owes, 2).').');

            return;
        }

        $user = auth()->user();
        $shift = Shift::where('user_id', $user->id)->whereNull('closed_at')->latest('id')->first();

        if ($this->payMethod === 'cash' && ! $shift && $user->role !== 'admin') {
            $this->error = 'Open a shift on the Sales tab first, so this cash is counted in your drawer.';

            return;
        }

        DB::transaction(function () use ($customer, $amount, $shift, $user) {
            $payment = Payment::create([
                'sale_id' => null,
                'customer_id' => $customer->id,
                'shift_id' => $shift?->id,
                'method' => $this->payMethod,
                'amount' => $amount,
                'reference' => trim($this->payReference) ?: null,
                'user_id' => $user->id,
            ]);

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'entry_type' => 'credit',
                'amount' => $amount,
                'payment_id' => $payment->id,
                'note' => trim($this->payNote) ?: 'Payment received',
            ]);
        });

        $this->showPayment = false;
        $this->message = 'Payment of '.number_format($amount, 2)." recorded for {$customer->name}.";
    }

    // ---------- Render ----------

    public function render()
    {
        $balances = CustomerLedger::query()
            ->selectRaw("customer_id, SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END) as bal")
            ->groupBy('customer_id')
            ->pluck('bal', 'customer_id');

        $term = trim($this->search);
        $owingIds = $balances->filter(fn ($b) => (float) $b > 0.004)->keys();

        $customers = Customer::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->owingOnly, fn ($q) => $q->whereIn('id', $owingIds))
            ->orderBy('name')
            ->paginate(15);

        $statementCustomer = null;
        $entries = collect();
        $statementBalance = 0.0;

        if ($this->statementCustomerId) {
            $statementCustomer = Customer::find($this->statementCustomerId);

            if ($statementCustomer) {
                $running = 0.0;
                $entries = CustomerLedger::with(['sale', 'payment'])
                    ->where('customer_id', $statementCustomer->id)
                    ->orderBy('id')
                    ->get()
                    ->map(function ($entry) use (&$running) {
                        $running += $entry->entry_type === 'debit' ? (float) $entry->amount : -(float) $entry->amount;
                        $entry->running = round($running, 2);

                        return $entry;
                    })
                    ->reverse()
                    ->values();
                $statementBalance = round($running, 2);
            }
        }

        return view('livewire.customer-manager', [
            'customers' => $customers,
            'balances' => $balances,
            'owingTotal' => (float) $balances->filter(fn ($b) => (float) $b > 0)->sum(),
            'owingCount' => $owingIds->count(),
            'statementCustomer' => $statementCustomer,
            'entries' => $entries,
            'statementBalance' => $statementBalance,
            'canEdit' => $this->canEdit(),
        ]);
    }
}
