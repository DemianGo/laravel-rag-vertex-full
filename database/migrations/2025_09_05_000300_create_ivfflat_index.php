<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->driver();
        // Só aplica em PostgreSQL (pgvector). Em MySQL, não faz nada.
        if ($driver !== 'pgsql') {
            return;
        }

        // Garante a extensão (não quebra se não tiver permissão)
        try {
            DB::statement("CREATE EXTENSION IF NOT EXISTS vector");
        } catch (\Throwable $e) {
            // ok em ambientes sem permissão
        }

        // Cria o índice IVFFLAT em embedding (vector_cosine_ops)
        try {
            DB::statement("
                CREATE INDEX IF NOT EXISTS idx_chunks_embedding_ivfflat
                ON chunks
                USING ivfflat (embedding vector_cosine_ops)
                WITH (lists = 100)
            ");
        } catch (\Throwable $e) {
            // Em casos onde a coluna ainda não é 'vector' neste ambiente, não derruba a migration de teste
        }
    }

    public function down(): void
    {
        $driver = $this->driver();
        if ($driver !== 'pgsql') {
            return;
        }

        try {
            DB::statement("DROP INDEX IF EXISTS idx_chunks_embedding_ivfflat");
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    private function driver(): ?string
    {
        try {
            return DB::connection()->getDriverName();
        } catch (\Throwable $e) {
            return null;
        }
    }
};
