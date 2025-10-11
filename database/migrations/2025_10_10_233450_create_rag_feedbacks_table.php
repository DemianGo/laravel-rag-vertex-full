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
        Schema::create('rag_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->integer('rating'); // 1 = positive, -1 = negative
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Índices para performance
            $table->index('document_id');
            $table->index('rating');
            $table->index('created_at');
            
            // Foreign key (opcional)
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_feedbacks');
    }
};
