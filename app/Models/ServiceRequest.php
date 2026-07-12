<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $fillable = [
        'client_id',
        'service_types',
        'applicant_type',
        'management_plan',
        'apartment_type_id',
        'bedrooms',
        'bathrooms',
        'kitchens',
        'hours_per_session',
        'feature_cost',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'start_date',
        'end_date',
        'duration_weeks',
        'package_name',
        'days_per_week',
        'service_days',
        'working_hour_start',
        'working_hour_end',
        'pay_rate',
        'cost_breakdown',
        'status',
        'applicant_id',
    ];

    protected $casts = [
        'service_types'  => 'array',
        'cost_breakdown' => 'array',
        'start_date'     => 'date',
        'end_date'       => 'date',
        'feature_cost'   => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function servicesSummary(): string
    {
        $names = array_map(
            fn($s) => ucfirst(str_replace('_', ' ', $s)),
            $this->service_types ?? []
        );
        return implode(', ', $names);
    }
}
