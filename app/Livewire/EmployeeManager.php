<?php

namespace App\Livewire;

use App\Models\Employee;
use App\Models\Godown;
use App\Models\Outlet;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';

    public bool $showForm = false;
    public ?int $employeeId = null;
    public string $employeeNo = '';
    public string $fullName = '';
    public string $phone = '';
    public string $email = '';
    public string $position = '';
    public string $assignment = '';     // '', 'godown:ID' or 'outlet:ID'
    public string $deviceNo = '';
    public string $hireDate = '';

    public string $message = '';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'godown_keeper', 'outlet_manager'], true), 403);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
        $this->employeeNo = 'EMP'.str_pad((string) ((int) Employee::max('id') + 1), 3, '0', STR_PAD_LEFT);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->ensureAdmin();
        $employee = Employee::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->employeeId = $employee->id;
        $this->employeeNo = $employee->employee_no;
        $this->fullName = $employee->full_name;
        $this->phone = (string) $employee->phone;
        $this->email = (string) $employee->email;
        $this->position = (string) $employee->position;
        $this->deviceNo = (string) $employee->device_employee_no;
        $this->hireDate = $employee->hire_date ? $employee->hire_date->format('Y-m-d') : '';
        $this->assignment = $employee->godown_id
            ? 'godown:'.$employee->godown_id
            : ($employee->outlet_id ? 'outlet:'.$employee->outlet_id : '');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureAdmin();
        $this->employeeNo = strtoupper(trim($this->employeeNo));
        $this->deviceNo = trim($this->deviceNo);

        $data = $this->validate([
            'employeeNo' => ['required', 'string', 'max:30', Rule::unique('employees', 'employee_no')->ignore($this->employeeId)],
            'fullName' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'assignment' => ['nullable', 'regex:/^(godown|outlet):\d+$/'],
            'deviceNo' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'device_employee_no')->ignore($this->employeeId)],
            'hireDate' => ['nullable', 'date'],
        ]);

        $godownId = null;
        $outletId = null;
        if (! empty($data['assignment'])) {
            [$type, $id] = explode(':', $data['assignment']);
            if ($type === 'godown') {
                $godownId = Godown::findOrFail((int) $id)->id;
            } else {
                $outletId = Outlet::findOrFail((int) $id)->id;
            }
        }

        $fields = [
            'employee_no' => $data['employeeNo'],
            'device_employee_no' => $data['deviceNo'] ?: null,
            'full_name' => $data['fullName'],
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'position' => $data['position'] ?: null,
            'godown_id' => $godownId,
            'outlet_id' => $outletId,
            'hire_date' => $data['hireDate'] ?: null,
        ];

        if ($this->employeeId) {
            Employee::findOrFail($this->employeeId)->update($fields);
            $this->message = 'Employee updated.';
        } else {
            Employee::create($fields);
            $this->message = 'Employee added.';
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
        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => ! $employee->is_active]);
        $this->message = $employee->is_active ? 'Employee activated.' : 'Employee deactivated.';
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset([
            'showForm', 'employeeId', 'employeeNo', 'fullName', 'phone', 'email',
            'position', 'assignment', 'deviceNo', 'hireDate',
        ]);
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
        }
    }

    public function render()
    {
        $term = trim($this->search);

        $employees = Employee::with(['godown', 'outlet'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('full_name', 'like', "%{$term}%")
                        ->orWhere('employee_no', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('full_name')
            ->paginate(15);

        return view('livewire.employee-manager', [
            'employees' => $employees,
            'godowns' => Godown::where('is_active', true)->orderBy('code')->get(),
            'outlets' => Outlet::where('is_active', true)->orderBy('code')->get(),
            'isAdmin' => auth()->user()?->role === 'admin',
        ]);
    }
}
