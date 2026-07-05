<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Account
            $table->string('account_type')->default('client')->after('email'); // client | applicant
            $table->string('applicant_type')->nullable()->after('account_type'); // live-in | live-out

            // Personal
            $table->string('middle_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('phone')->nullable()->unique()->after('last_name');
            $table->string('gender')->nullable()->after('phone');
            $table->date('dob')->nullable()->after('gender');
            $table->string('profile_photo_path')->nullable()->after('dob');

            // UK address format
            $table->string('address_line_1')->nullable()->after('profile_photo_path');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('county')->nullable()->after('city');
            $table->string('postcode')->nullable()->after('county');
            $table->string('country')->default('United Kingdom')->after('postcode');

            // Applicant profile
            $table->text('bio')->nullable()->after('country');
            $table->string('years_of_experience')->nullable()->after('bio');
            $table->json('specialties')->nullable()->after('years_of_experience');
            $table->string('id_document_path')->nullable()->after('specialties');

            // Right to Work verification (UK requirement for all employment)
            $table->string('right_to_work_status')->default('not_started')->after('id_document_path'); // not_started | pending | verified | rejected
            $table->string('right_to_work_document_type')->nullable()->after('right_to_work_status'); // passport | brp | share_code | visa
            $table->timestamp('right_to_work_checked_at')->nullable()->after('right_to_work_document_type');

            // DBS check (standard background-check process for in-home work)
            $table->string('ni_number')->nullable()->after('right_to_work_checked_at'); // National Insurance number
            $table->string('dbs_check_status')->default('not_started')->after('ni_number'); // not_started | pending | clear | flagged
            $table->string('dbs_certificate_number')->nullable()->after('dbs_check_status');
            $table->date('dbs_check_date')->nullable()->after('dbs_certificate_number');

            $table->boolean('form_completed')->default(false)->after('dbs_check_date');
            $table->boolean('terms_accepted')->default(false)->after('form_completed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_type', 'applicant_type',
                'middle_name', 'last_name', 'phone', 'gender', 'dob', 'profile_photo_path',
                'address_line_1', 'address_line_2', 'city', 'county', 'postcode', 'country',
                'bio', 'years_of_experience', 'specialties', 'id_document_path',
                'right_to_work_status', 'right_to_work_document_type', 'right_to_work_checked_at',
                'ni_number', 'dbs_check_status', 'dbs_certificate_number', 'dbs_check_date',
                'form_completed', 'terms_accepted',
            ]);
        });
    }
};
