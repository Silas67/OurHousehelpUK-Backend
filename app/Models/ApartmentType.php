<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApartmentType extends Model
{
    protected $fillable = ['name', 'cost', 'sort_order'];

    protected $casts = ['cost' => 'decimal:2'];
}
