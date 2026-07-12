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
        Schema::table('users', function (Blueprint $table) {
            $table->string('dbs_certificate_path')->nullable()->after('dbs_check_date');
            $table->string('stripe_customer_id')->nullable()->after('dbs_certificate_path');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('stripe_payment_method_id')->nullable()->after('cost_breakdown');
            $table->unsignedInteger('quoted_pence')->default(0)->after('stripe_payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dbs_certificate_path', 'stripe_customer_id']);
        });
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_method_id', 'quoted_pence']);
        });
    }
};
