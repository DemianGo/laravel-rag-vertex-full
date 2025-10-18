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
        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name'); // openai, gemini, claude
            $table->string('model_name'); // gpt-4, gemini-pro, claude-3
            $table->string('display_name'); // GPT-4, Gemini Pro, Claude 3
            $table->decimal('input_cost_per_1k', 10, 6); // Custo por 1K tokens de entrada
            $table->decimal('output_cost_per_1k', 10, 6); // Custo por 1K tokens de saída
            $table->integer('context_length')->default(4000); // Tamanho do contexto
            $table->decimal('base_markup_percentage', 5, 2)->default(0); // Margem base (%)
            $table->decimal('min_markup_percentage', 5, 2)->default(0); // Margem mínima (%)
            $table->decimal('max_markup_percentage', 5, 2)->default(100); // Margem máxima (%)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable(); // Configurações extras
            $table->timestamps();
            
            $table->index(['provider_name', 'is_active']);
            $table->unique(['provider_name', 'model_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};