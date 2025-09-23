<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Otimizações PostgreSQL para RAG enterprise:
     * - Índices especializados para embeddings
     * - Índices compostos para multi-tenancy
     * - Índices BRIN para dados temporais
     * - Preparação para particionamento
     */
    public function up(): void
    {
        // Índice partial para embeddings não-nulos (economia de espaço)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_document_chunks_embedding_partial
            ON document_chunks USING ivfflat (embedding vector_cosine_ops)
            WHERE embedding IS NOT NULL
        ');

        // Índice composto para queries por tenant + documento
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_document_chunks_tenant_document
            ON document_chunks (tenant_slug, document_id)
            INCLUDE (chunk_index, content_preview)
        ');

        // Índice BRIN para timestamps (dados temporais grandes)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_document_chunks_created_brin
            ON document_chunks USING BRIN (created_at)
        ');

        // Índice para busca por metadata (JSONB)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_document_chunks_metadata_gin
            ON document_chunks USING GIN (metadata)
        ');

        // Índice para busca full-text no conteúdo
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_document_chunks_content_fts
            ON document_chunks USING GIN (to_tsvector(\'english\', content))
        ');

        // Índice composto para retrieval híbrido
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_document_chunks_hybrid_search
            ON document_chunks (tenant_slug, document_id)
            INCLUDE (chunk_index, content, metadata)
        ');

        // Estatísticas otimizadas para pgvector
        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding SET STATISTICS 1000');

        // Preparar particionamento por tenant (para futuro)
        DB::statement('
            CREATE TABLE IF NOT EXISTS document_chunks_partitioned (
                LIKE document_chunks INCLUDING ALL
            ) PARTITION BY HASH (tenant_slug)
        ');

        // Configurar autovacuum agressivo para tabela de chunks
        DB::statement('
            ALTER TABLE document_chunks SET (
                autovacuum_vacuum_scale_factor = 0.1,
                autovacuum_analyze_scale_factor = 0.05,
                autovacuum_vacuum_cost_limit = 1000
            )
        ');

        // Otimizar configurações de índice ivfflat
        DB::statement('
            ALTER INDEX idx_document_chunks_embedding_partial
            SET (lists = 100)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_document_chunks_embedding_partial');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_document_chunks_tenant_document');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_document_chunks_created_brin');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_document_chunks_metadata_gin');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_document_chunks_content_fts');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_document_chunks_hybrid_search');
        DB::statement('DROP TABLE IF EXISTS document_chunks_partitioned');

        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding SET STATISTICS -1');
        DB::statement('
            ALTER TABLE document_chunks RESET (
                autovacuum_vacuum_scale_factor,
                autovacuum_analyze_scale_factor,
                autovacuum_vacuum_cost_limit
            )
        ');
    }
};