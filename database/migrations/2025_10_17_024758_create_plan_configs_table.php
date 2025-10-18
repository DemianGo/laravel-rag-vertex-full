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
        Schema::create('plan_configs', function (Blueprint $table) {
            $table->id();
            $table->string('plan_name'); // free, pro, enterprise
            $table->string('display_name'); // Free Plan, Pro Plan, etc
            $table->decimal('price_monthly', 8, 2)->default(0);
            $table->decimal('price_yearly', 8, 2)->default(0);
            $table->integer('tokens_limit')->default(100);
            $table->integer('documents_limit')->default(1);
            $table->json('features')->nullable(); // Array de features
            $table->decimal('margin_percentage', 5, 2)->default(30.00);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique('plan_name');
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_configs');
    }
};
