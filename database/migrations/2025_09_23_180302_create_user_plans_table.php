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
        Schema::create('user_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('plan', ['free', 'pro', 'enterprise'])->default('free');
            $table->integer('tokens_used')->default(0);
            $table->integer('tokens_limit')->default(100);
            $table->integer('documents_used')->default(0);
            $table->integer('documents_limit')->default(1);
            $table->timestamp('last_reset')->nullable();
            $table->timestamp('plan_expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'plan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_plans');
    }
};