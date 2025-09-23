<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rag_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index(); // query, embedding, generation, error, performance
            $table->timestamp('timestamp')->index();
            $table->json('data'); // Flexible JSON data for metric-specific information
            $table->boolean('success')->default(true)->index();
            $table->string('performance_category', 20)->nullable()->index(); // fast, medium, slow, very_slow
            $table->string('tenant_id', 50)->default('default')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['type', 'timestamp']);
            $table->index(['type', 'success', 'timestamp']);
            $table->index(['tenant_id', 'type', 'timestamp']);
            $table->index(['performance_category', 'timestamp']);
        });

        // Create additional indexes for JSON fields (MySQL 5.7+/PostgreSQL)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX idx_rag_metrics_operation ON rag_metrics ((CAST(data->"$.operation" AS CHAR(50))))');
            DB::statement('CREATE INDEX idx_rag_metrics_duration ON rag_metrics ((CAST(data->"$.duration_ms" AS DECIMAL(10,2))))');
            DB::statement('CREATE INDEX idx_rag_metrics_cached ON rag_metrics ((CAST(data->"$.cached" AS UNSIGNED)))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_metrics');
    }
};
