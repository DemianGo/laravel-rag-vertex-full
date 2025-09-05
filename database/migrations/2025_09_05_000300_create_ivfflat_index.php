<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Índice aproximado para busca vetorial (cosine) — idempotente
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chunks_embedding_ivfflat ON chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_chunks_embedding_ivfflat");
    }
};
