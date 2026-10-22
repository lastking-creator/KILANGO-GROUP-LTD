<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    // expense_date is kept as a plain Y-m-d string so date filters match exactly.
    protected $fillable = ['expense_date', 'category', 'description', 'amount', 'outlet_id', 'user_id'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
