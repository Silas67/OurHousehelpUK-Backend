<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['name', 'days_per_week', 'cost', 'description', 'is_active', 'sort_order'];

    protected $casts = ['cost' => 'decimal:2'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
