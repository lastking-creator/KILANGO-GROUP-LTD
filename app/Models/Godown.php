<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Godown extends Model
{
    protected $fillable = ['code', 'name', 'location', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
