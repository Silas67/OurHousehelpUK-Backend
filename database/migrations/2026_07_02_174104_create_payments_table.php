<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('service_requests')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('amount_pence')->default(0);
            $table->string('currency', 3)->default('gbp');
            $table->string('status', 20)->default('pending'); // pending | paid | failed | refunded
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_client_secret')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
