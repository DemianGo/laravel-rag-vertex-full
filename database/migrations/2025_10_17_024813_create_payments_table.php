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
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->nullable(); // ID do Mercado Pago
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('payment_method')->nullable(); // credit_card, pix, boleto, etc
            $table->string('gateway')->default('mercadopago');
            $table->json('gateway_data')->nullable(); // Dados retornados pelo gateway
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['external_id', 'gateway']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
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
