<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['booking_id', 'client_id', 'applicant_id', 'stars', 'comment'];
}
