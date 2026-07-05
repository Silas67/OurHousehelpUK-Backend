<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->json('service_types');              // ["cleaning", "cooking"] — multi-select
            $table->enum('applicant_type', ['live-in', 'live-out']);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('postcode', 10);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('duration_weeks')->default(4); // 1, 4, 8, 12
            $table->string('package_name')->nullable();   // e.g. "5 days/week"
            $table->unsignedTinyInteger('days_per_week')->nullable();
            $table->string('service_days')->nullable();   // "Monday, Wednesday, Friday"
            $table->time('working_hour_start')->nullable();
            $table->time('working_hour_end')->nullable();
            $table->string('pay_rate')->nullable();        // set by platform/admin, not client
            $table->enum('status', ['open', 'matched', 'confirmed', 'active', 'completed', 'cancelled'])->default('open');
            $table->foreignId('applicant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
