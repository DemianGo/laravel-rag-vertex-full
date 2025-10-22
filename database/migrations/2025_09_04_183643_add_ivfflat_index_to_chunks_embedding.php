<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $driver = DB::connection()->getDriverName();
        } catch (\Throwable $e) {
            // Se não conseguir detectar, não quebra a migration
            return;
        }

        // Só aplica em PostgreSQL (pgvector)
        if ($driver !== 'pgsql') {
            // Em MySQL não há pgvector/ivfflat: nada a fazer aqui.
            return;
        }

        // Cria a extensão pgvector se não existir
        try {
            DB::statement("CREATE EXTENSION IF NOT EXISTS vector");
        } catch (\Throwable $e) {
            // Se a extensão já existir/sem permissão, não derruba a migration de teste
        }

        // Cria índice ivfflat na coluna embedding (ajuste o nome do índice se preferir)
        try {
            DB::statement("
                CREATE INDEX IF NOT EXISTS chunks_embedding_ivfflat_idx
                ON chunks
                USING ivfflat (embedding vector_cosine_ops)
                WITH (lists = 100)
            ");
        } catch (\Throwable $e) {
            // Evita quebrar testes caso o tipo da coluna não seja vector em algum ambiente
        }
    }

    public function down(): void
    {
        try {
            $driver = DB::connection()->getDriverName();
        } catch (\Throwable $e) {
            return;
        }

        if ($driver !== 'pgsql') {
            return;
        }

        try {
            DB::statement("DROP INDEX IF EXISTS chunks_embedding_ivfflat_idx");
        } catch (\Throwable $e) {
            // Silencioso no rollback
        }
    }
};
