<?php

namespace App\Livewire;

use App\Models\Godown;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UserManager extends Component
{
    use WithPagination;

    private const ROLES = ['admin', 'godown_keeper', 'outlet_manager', 'cashier'];

    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = 'active';

    public bool $showForm = false;
    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $role = 'cashier';
    public string $godownId = '';
    public string $outletId = '';
    public string $password = '';

    public string $message = '';
    public string $error = '';

    public function mount(): void
    {
        $this->ensureAdmin();
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    private function otherActiveAdmins(?int $exceptId): int
    {
        return User::where('role', 'admin')->where('is_active', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->count();
    }

    // ---------- Form ----------

    public function create(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
        $this->showForm = true;
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(10, true, true, false, false);
    }

    public function edit(int $id): void
    {
        $this->ensureAdmin();
        $user = User::findOrFail($id);

        $this->resetValidation();
        $this->message = '';
        $this->error = '';
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->role = $user->role ?: 'cashier';
        $this->godownId = $user->godown_id ? (string) $user->godown_id : '';
        $this->outletId = $user->outlet_id ? (string) $user->outlet_id : '';
        $this->password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureAdmin();
        $this->message = '';
        $this->error = '';
        $this->email = strtolower(trim($this->email));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::ROLES)],
            'godownId' => ['nullable', 'integer', 'exists:godowns,id'],
            'outletId' => ['nullable', 'integer', 'exists:outlets,id'],
            'password' => $this->userId
                ? ['nullable', 'string', 'min:8', 'max:100']
                : ['required', 'string', 'min:8', 'max:100'],
        ], [], ['godownId' => 'godown', 'outletId' => 'outlet']);

        $role = $data['role'];

        // Where does this person work?
        $godownId = null;
        $outletId = null;

        if (in_array($role, ['outlet_manager', 'cashier'], true)) {
            if (empty($data['outletId'])) {
                $this->addError('outletId', 'Choose the outlet this person works at.');

                return;
            }
            $outletId = (int) $data['outletId'];
        } elseif ($role === 'godown_keeper') {
            $godownId = ! empty($data['godownId']) ? (int) $data['godownId'] : null;
        }

        // Do not lock the business out of its own system
        if ($this->userId) {
            $existing = User::findOrFail($this->userId);

            if ($existing->id === auth()->id() && $role !== 'admin') {
                $this->addError('role', 'You cannot remove your own admin role.');

                return;
            }

            if ($existing->role === 'admin' && $role !== 'admin' && $this->otherActiveAdmins($existing->id) < 1) {
                $this->addError('role', 'There must always be at least one active admin.');

                return;
            }
        }

        $user = $this->userId ? User::findOrFail($this->userId) : new User();

        $fields = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'role' => $role,
            'godown_id' => $godownId,
            'outlet_id' => $outletId,
        ];

        if (! $this->userId) {
            // Accounts made by the admin are ready to use: no email verification step
            $fields['email_verified_at'] = now();
            $fields['is_active'] = true;
        }

        if (! empty($data['password'])) {
            $fields['password'] = Hash::make($data['password']);
        }

        $user->forceFill($fields)->save();

        $this->message = $this->userId
            ? 'User updated.'.(! empty($data['password']) ? ' The password was changed.' : '')
            : "User {$user->name} created. Give them their email and the password you set.";

        $this->resetForm(keepMessage: true);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $this->ensureAdmin();
        $this->message = '';
        $this->error = '';

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->error = 'You cannot deactivate your own account.';

            return;
        }

        if ($user->is_active && $user->role === 'admin' && $this->otherActiveAdmins($user->id) < 1) {
            $this->error = 'There must always be at least one active admin.';

            return;
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        $this->message = $user->is_active ? "{$user->name} can log in again." : "{$user->name} can no longer log in.";
    }

    private function resetForm(bool $keepMessage = false): void
    {
        $this->reset(['showForm', 'userId', 'name', 'email', 'phone', 'godownId', 'outletId', 'password']);
        $this->role = 'cashier';
        $this->resetValidation();
        if (! $keepMessage) {
            $this->message = '';
            $this->error = '';
        }
    }

    public function render()
    {
        $term = trim($this->search);

        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($this->roleFilter !== '', fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.user-manager', [
            'users' => $users,
            'godowns' => Godown::where('is_active', true)->orderBy('code')->get(),
            'outlets' => Outlet::where('is_active', true)->orderBy('code')->get(),
            'godownNames' => Godown::pluck('name', 'id'),
            'outletNames' => Outlet::pluck('name', 'id'),
        ]);
    }
}
