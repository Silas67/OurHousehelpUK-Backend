<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',

        'account_type',
        'applicant_type',

        'middle_name',
        'last_name',
        'phone',
        'gender',
        'dob',
        'profile_photo_path',

        'address_line_1',
        'address_line_2',
        'city',
        'county',
        'postcode',
        'country',

        'bio',
        'years_of_experience',
        'specialties',
        'id_document_path',

        'right_to_work_status',
        'right_to_work_document_type',
        'right_to_work_checked_at',

        'ni_number',
        'dbs_check_status',
        'dbs_certificate_number',
        'dbs_check_date',

        'form_completed',
        'terms_accepted',
        'is_available',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'specialties' => 'array',
            'right_to_work_checked_at' => 'datetime',
            'dbs_check_date' => 'date',
            'form_completed' => 'boolean',
            'terms_accepted' => 'boolean',
            'is_available'   => 'boolean',
        ];
    }
}
