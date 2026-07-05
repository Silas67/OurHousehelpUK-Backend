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
            $table->string('management_plan')->default('client-managed')->after('applicant_type');
            $table->foreignId('apartment_type_id')->nullable()->constrained('apartment_types')->nullOnDelete()->after('management_plan');
            $table->decimal('feature_cost', 10, 2)->default(0)->after('apartment_type_id');
            $table->json('cost_breakdown')->nullable()->after('pay_rate');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['apartment_type_id']);
            $table->dropColumn(['management_plan', 'apartment_type_id', 'feature_cost', 'cost_breakdown']);
        });
    }
};
