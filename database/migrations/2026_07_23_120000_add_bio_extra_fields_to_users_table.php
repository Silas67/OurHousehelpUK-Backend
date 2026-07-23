<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Staff-editable profile fields
            $table->json('languages')->nullable()->after('specialties');
            $table->json('household_types')->nullable()->after('languages');
            $table->json('availability_days')->nullable()->after('household_types');
            // Admin-set vetting outcome (number of references checked)
            $table->unsignedTinyInteger('references_checked')->nullable()->after('availability_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['languages', 'household_types', 'availability_days', 'references_checked']);
        });
    }
};
