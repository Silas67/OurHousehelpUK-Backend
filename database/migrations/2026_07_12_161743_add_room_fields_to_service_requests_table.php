<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('bedrooms')->default(0)->after('apartment_type_id');
            $table->unsignedTinyInteger('bathrooms')->default(0)->after('bedrooms');
            $table->unsignedTinyInteger('kitchens')->default(1)->after('bathrooms');
            $table->unsignedTinyInteger('hours_per_session')->default(0)->after('kitchens');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'bathrooms', 'kitchens', 'hours_per_session']);
        });
    }
};
