<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Half-hour sessions (e.g. 2.5h) need a decimal, not a tiny integer.
            $table->decimal('hours_per_session', 4, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('hours_per_session')->default(0)->change();
        });
    }
};
