<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['sale_id', 'customer_id', 'shift_id', 'method', 'amount', 'reference', 'user_id'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
