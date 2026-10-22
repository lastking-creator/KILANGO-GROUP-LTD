<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'credit_limit', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function ledger(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }

    /** What the customer owes right now. */
    public function outstanding(): float
    {
        $debit = (float) $this->ledger()->where('entry_type', 'debit')->sum('amount');
        $credit = (float) $this->ledger()->where('entry_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }
}
