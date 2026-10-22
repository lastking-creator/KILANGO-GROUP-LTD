<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    protected $fillable = [
        'godown_id', 'code', 'name', 'storage_type', 'capacity', 'capacity_unit', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'decimal:3',
    ];

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'location_id')->where('location_type', 'block');
    }
}
