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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payment_id')->unique(); // Ödeme sağlayıcısından gelen ID
            $table->string('payment_method'); // credit_card, paypal, apple_pay, google_pay, etc.
            $table->string('payment_provider'); // stripe, paypal, iyzico, etc.
            $table->decimal('amount', 10, 2); // Ödeme tutarı
            $table->string('currency', 3)->default('TRY'); // Para birimi
            $table->string('status')->default('pending'); // pending, completed, failed, refunded, cancelled
            $table->string('transaction_id')->nullable(); // İşlem ID'si
            $table->json('payment_data')->nullable(); // Ödeme sağlayıcısından gelen ek veriler
            $table->json('metadata')->nullable(); // Ek meta veriler
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
