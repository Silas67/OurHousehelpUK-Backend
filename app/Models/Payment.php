<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id', 'client_id', 'amount_pence', 'currency',
        'status', 'stripe_payment_intent_id', 'stripe_client_secret', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function booking()
    {
        return $this->belongsTo(ServiceRequest::class, 'booking_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function getAmountFormattedAttribute(): string
    {
        return '£' . number_format($this->amount_pence / 100, 2);
    }
}
