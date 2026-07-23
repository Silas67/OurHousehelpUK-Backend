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
        'languages',
        'household_types',
        'availability_days',
        'references_checked',
        'id_document_path',
        'cv_path',
        'application_status',

        'right_to_work_status',
        'right_to_work_document_type',
        'right_to_work_checked_at',

        'ni_number',
        'dbs_check_status',
        'dbs_certificate_number',
        'dbs_check_date',
        'dbs_certificate_path',
        'stripe_customer_id',

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
            'languages' => 'array',
            'household_types' => 'array',
            'availability_days' => 'array',
            'right_to_work_checked_at' => 'datetime',
            'dbs_check_date' => 'date',
            'form_completed' => 'boolean',
            'terms_accepted' => 'boolean',
            'is_available'   => 'boolean',
        ];
    }

    /**
     * "Profile complete" is derived from the fields staff actually fill in,
     * rather than the static form_completed column — nothing ever set that
     * column, and deriving it here means it can't drift out of sync with
     * what the profile screen collects.
     */
    public function missingProfileFields(): array
    {
        if ($this->account_type !== 'applicant') {
            return [];
        }

        $missing = [];
        if (empty($this->bio)) $missing[] = 'Bio';
        if ($this->years_of_experience === null) $missing[] = 'Years of Experience';
        if (empty($this->specialties)) $missing[] = 'Specialties';
        if (empty($this->applicant_type)) $missing[] = 'Position Type';

        return $missing;
    }

    public function hasCompleteProfile(): bool
    {
        return empty($this->missingProfileFields());
    }

    /**
     * Whether this worker can be shown to clients / matched to jobs. Under
     * the company-managed model this platform — not the client — carries
     * the statutory liability for right to work and DBS, so both must be
     * cleared first.
     */
    public function isVettedForPlacement(): bool
    {
        return $this->right_to_work_status === 'verified'
            && $this->dbs_check_status === 'clear';
    }
}
