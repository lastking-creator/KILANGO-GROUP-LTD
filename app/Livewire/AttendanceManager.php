<?php

namespace App\Livewire;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\HikvisionAttendanceSync;
use App\Services\HikvisionException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AttendanceManager extends Component
{
    public string $date = '';

    public bool $showForm = false;
    public ?int $formEmployeeId = null;
    public string $status = 'present';
    public string $checkIn = '';
    public string $checkOut = '';
    public string $note = '';

    public string $message = '';
    public string $error = '';

    private const ROLES = ['admin', 'godown_keeper', 'outlet_manager'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
        $this->date = now()->toDateString();
    }

    private function ensureCanRecord(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
    }

    private function day(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay();
        } catch (\Throwable $e) {
            return now()->startOfDay();
        }
    }

    // ---------- Date navigation ----------

    public function updatedDate(): void
    {
        $this->closeForm();
    }

    public function previousDay(): void
    {
        $this->date = $this->day()->subDay()->toDateString();
        $this->closeForm();
    }

    public function nextDay(): void
    {
        $this->date = $this->day()->addDay()->toDateString();
        $this->closeForm();
    }

    public function today(): void
    {
        $this->date = now()->toDateString();
        $this->closeForm();
    }

    // ---------- Device sync ----------

    public function syncDevice(): void
    {
        $this->ensureCanRecord();
        $this->message = '';
        $this->error = '';

        try {
            $result = app(HikvisionAttendanceSync::class)->run($this->day());
        } catch (HikvisionException $e) {
            $this->error = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'Something went wrong while reading the device. Details are in storage/logs/laravel.log.';

            return;
        }

        $text = sprintf(
            'Device sync finished: %d punches read, %d new, %d updated, %d left alone (entered by hand).',
            $result['events'], $result['created'], $result['updated'], $result['skipped']
        );

        if ($result['unmatched']) {
            $text .= ' No employee has these device IDs yet: '.implode(', ', $result['unmatched']).'.';
        }

        $this->message = $text;
    }

    // ---------- Quick actions ----------

    public function markPresent(int $employeeId): void
    {
        $this->ensureCanRecord();
        $employee = Employee::where('is_active', true)->findOrFail($employeeId);
        $record = $this->findRecord($employee->id);

        $values = ['status' => 'present'];
        if ($this->day()->isToday() && ! ($record && $record->check_in)) {
            $values['check_in'] = now()->format('H:i:s');
        }

        $this->store($employee->id, $values);
        $this->message = $employee->full_name.' marked present.';
    }

    public function markAbsent(int $employeeId): void
    {
        $this->ensureCanRecord();
        $employee = Employee::where('is_active', true)->findOrFail($employeeId);

        $this->store($employee->id, ['status' => 'absent', 'check_in' => null, 'check_out' => null]);
        $this->message = $employee->full_name.' marked absent.';
    }

    public function checkOutNow(int $employeeId): void
    {
        $this->ensureCanRecord();
        $employee = Employee::where('is_active', true)->findOrFail($employeeId);

        abort_unless($this->day()->isToday(), 422);

        $this->store($employee->id, ['check_out' => now()->format('H:i:s')]);
        $this->message = $employee->full_name.' checked out.';
    }

    // ---------- Edit form ----------

    public function editRecord(int $employeeId): void
    {
        $this->ensureCanRecord();
        $employee = Employee::where('is_active', true)->findOrFail($employeeId);
        $record = $this->findRecord($employee->id);

        $this->resetValidation();
        $this->message = '';
        $this->formEmployeeId = $employee->id;
        $this->status = $record->status ?? 'present';
        $this->checkIn = $record && $record->check_in ? substr((string) $record->check_in, 0, 5) : '';
        $this->checkOut = $record && $record->check_out ? substr((string) $record->check_out, 0, 5) : '';
        $this->note = (string) ($record->note ?? '');
        $this->showForm = true;
    }

    public function saveForm(): void
    {
        $this->ensureCanRecord();
        abort_unless($this->formEmployeeId, 422);
        $employee = Employee::where('is_active', true)->findOrFail($this->formEmployeeId);

        $checkOutRules = ['nullable', 'date_format:H:i'];
        if ($this->checkIn !== '' && $this->checkOut !== '') {
            $checkOutRules[] = 'after_or_equal:checkIn';
        }

        $data = $this->validate([
            'status' => ['required', Rule::in(['present', 'late', 'absent', 'leave'])],
            'checkIn' => ['nullable', 'date_format:H:i'],
            'checkOut' => $checkOutRules,
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $worked = in_array($data['status'], ['present', 'late'], true);

        $this->store($employee->id, [
            'status' => $data['status'],
            'check_in' => $worked && $data['checkIn'] ? $data['checkIn'].':00' : null,
            'check_out' => $worked && $data['checkOut'] ? $data['checkOut'].':00' : null,
            'note' => $data['note'] ?: null,
        ]);

        $this->message = 'Attendance saved for '.$employee->full_name.'.';
        $this->closeForm();
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'formEmployeeId', 'checkIn', 'checkOut', 'note']);
        $this->status = 'present';
        $this->resetValidation();
    }

    // ---------- Helpers ----------

    private function findRecord(int $employeeId): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $employeeId)
            ->where('work_date', $this->day()->toDateString())
            ->first();
    }

    private function store(int $employeeId, array $values): void
    {
        $record = AttendanceRecord::firstOrNew([
            'employee_id' => $employeeId,
            'work_date' => $this->day()->toDateString(),
        ]);

        if (! $record->exists) {
            $record->source = 'manual';
        }

        $record->fill($values);
        $record->recorded_by = auth()->id();
        $record->save();
    }

    public function render()
    {
        $day = $this->day();

        $employees = Employee::with(['godown', 'outlet'])
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $records = AttendanceRecord::where('work_date', $day->toDateString())
            ->get()
            ->keyBy('employee_id');

        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'none' => 0];
        foreach ($employees as $employee) {
            $record = $records->get($employee->id);
            $counts[$record ? $record->status : 'none']++;
        }

        return view('livewire.attendance-manager', [
            'employees' => $employees,
            'records' => $records,
            'counts' => $counts,
            'isToday' => $day->isToday(),
            'dayLabel' => $day->format('l, d M Y'),
        ]);
    }
}
