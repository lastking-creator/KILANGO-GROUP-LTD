<?php

namespace App\Livewire;

use App\Models\Outlet;
use Illuminate\Validation\Rule;
use Livewire\Component;

class OutletManager extends Component
{
    public bool $showForm = false;
    public ?int $outletId = null;
    public string $code = '';
    public string $name = '';
    public string $location = '';
    public string $phone = '';

    public string $message = '';

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function create(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->ensureAdmin();
        $outlet = Outlet::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->outletId = $outlet->id;
        $this->code = $outlet->code;
        $this->name = $outlet->name;
        $this->location = (string) $outlet->location;
        $this->phone = (string) $outlet->phone;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureAdmin();
        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('outlets', 'code')->ignore($this->outletId)],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $data['location'] = $data['location'] ?: null;
        $data['phone'] = $data['phone'] ?: null;

        if ($this->outletId) {
            Outlet::findOrFail($this->outletId)->update($data);
            $this->message = 'Outlet updated.';
        } else {
            Outlet::create($data);
            $this->message = 'Outlet added.';
        }

        $this->resetForm(keepMessage: true);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $this->ensureAdmin();
        $outlet = Outlet::findOrFail($id);
        $outlet->update(['is_active' => ! $outlet->is_active]);
        $this->message = $outlet->is_active ? 'Outlet activated.' : 'Outlet deactivated.';
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset(['showForm', 'outletId', 'code', 'name', 'location', 'phone']);
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    public function render()
    {
        return view('livewire.outlet-manager', [
            'outlets' => Outlet::orderBy('code')->get(),
            'isAdmin' => auth()->user()?->role === 'admin',
        ]);
    }
}
