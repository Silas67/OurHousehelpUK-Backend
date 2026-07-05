<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagementPlan extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'platform_markup', 'is_active'];

    protected $casts = ['platform_markup' => 'decimal:2', 'is_active' => 'boolean'];
}
