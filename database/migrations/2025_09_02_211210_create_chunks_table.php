<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->index();
            $table->text('content');
            $table->timestamps();
        });

        // Adiciona a coluna vetorial e índice quando for Postgres
        if (DB::getDriverName() === 'pgsql') {
            // Requer a extensão "vector" já instalada no banco
            DB::statement('ALTER TABLE chunks ADD COLUMN IF NOT EXISTS embedding vector(768)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_chunks_embedding ON chunks USING ivfflat (embedding vector_cosine_ops)');
        } else {
            // Fallback para dev com SQLite, etc.
            Schema::table('chunks', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
