<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->driver();

        // Garante que a tabela existe antes de qualquer ajuste
        if (!Schema::hasTable('chunks')) {
            return;
        }

        // Tornar campos opcionais/índices sem quebrar drivers
        try {
            // Em muitos drivers, alterar colunas exige doctrine/dbal; evitamos mudanças destrutivas aqui.
            // Caso precise algo específico, faça em migrations separadas por driver.
            if (!Schema::hasColumn('chunks', 'embedding')) {
                Schema::table('chunks', function (Blueprint $table) {
                    $table->text('embedding')->nullable();
                });
            }
        } catch (\Throwable $e) {}

        // Índice simples (document_id, ord)
        try {
            DB::statement("CREATE INDEX IF NOT EXISTS chunks_document_ord_idx ON chunks (document_id, ord)");
        } catch (\Throwable $e) {
            // ok
        }

        // Em Postgres, tentar garantir pgvector + IVFFLAT
        if ($driver === 'pgsql') {
            try { DB::statement("CREATE EXTENSION IF NOT EXISTS vector"); } catch (\Throwable $e) {}
            try {
                DB::statement("
                    CREATE INDEX IF NOT EXISTS idx_chunks_embedding_ivfflat
                    ON chunks
                    USING ivfflat (embedding vector_cosine_ops)
                    WITH (lists = 100)
                ");
            } catch (\Throwable $e) {
                // ok
            }
        }
    }

    public function down(): void
    {
        // Nada destrutivo no rollback para não afetar dev/test.
    }

    private function driver(): ?string
    {
        try { return DB::connection()->getDriverName(); } catch (\Throwable $e) { return null; }
    }
};
