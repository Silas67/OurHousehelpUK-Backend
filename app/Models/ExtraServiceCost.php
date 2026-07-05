<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraServiceCost extends Model
{
    protected $fillable = ['cost'];

    protected $casts = ['cost' => 'decimal:2'];
}
