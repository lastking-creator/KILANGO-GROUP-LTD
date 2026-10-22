<?php

namespace App\Livewire;

use App\Models\Supplier;
use Livewire\Component;

class SupplierManager extends Component
{
    public bool $showForm = false;
    public ?int $supplierId = null;
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';

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

    public function create(): void
    {
        $this->ensureAllowed();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->ensureAllowed();
        $supplier = Supplier::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = (string) $supplier->phone;
        $this->email = (string) $supplier->email;
        $this->address = (string) $supplier->address;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureAllowed();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $fields = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'address' => $data['address'] ?: null,
        ];

        if ($this->supplierId) {
            Supplier::findOrFail($this->supplierId)->update($fields);
            $this->message = 'Supplier updated.';
        } else {
            Supplier::create($fields);
            $this->message = 'Supplier added.';
        }

        $this->resetForm(keepMessage: true);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $this->ensureAllowed();
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['is_active' => ! $supplier->is_active]);
        $this->message = $supplier->is_active ? 'Supplier activated.' : 'Supplier deactivated.';
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset(['showForm', 'supplierId', 'name', 'phone', 'email', 'address']);
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    public function render()
    {
        return view('livewire.supplier-manager', [
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
