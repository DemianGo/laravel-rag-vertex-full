<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Garante extensão pgvector disponível
        DB::statement("CREATE EXTENSION IF NOT EXISTS vector");

        // Deixa embedding NULLABLE (evita falha em docs ainda sem embedding)
        try {
            DB::statement("ALTER TABLE chunks ALTER COLUMN embedding DROP NOT NULL");
        } catch (\Throwable $e) {
            // ignora se já for NULLABLE
        }

        // Índice simples para navegação por documento
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chunks_docid_ord ON chunks (document_id, ord)");
    }

    public function down(): void
    {
        // opcional: remover índice
        try { DB::statement("DROP INDEX IF EXISTS idx_chunks_docid_ord"); } catch (\Throwable $e) {}
    }
};
