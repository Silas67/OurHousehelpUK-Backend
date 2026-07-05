<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseService extends Model
{
    protected $table = 'house_services';

    protected $fillable = ['service_name', 'slug', 'service_type', 'base_cost', 'is_active', 'sort_order'];

    protected $casts = ['base_cost' => 'decimal:2', 'is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
