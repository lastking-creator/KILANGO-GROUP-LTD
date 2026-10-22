<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    // work_date is deliberately not cast: it stays a plain Y-m-d string so lookups match exactly.
    protected $fillable = [
        'employee_id', 'work_date', 'check_in', 'check_out', 'status', 'source', 'note', 'recorded_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
