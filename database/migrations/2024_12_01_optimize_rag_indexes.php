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
     * - Índices especializados para embeddings na tabela chunks
     * - Índices compostos para multi-tenancy
     * - Índices BRIN para dados temporais
     * - Configurações otimizadas para pgvector
     */
    public function up(): void
    {
        try {
            // Verificar se extensão pgvector está instalada
            $result = DB::select("SELECT * FROM pg_extension WHERE extname = 'vector'");
            if (empty($result)) {
                throw new Exception('pgvector extension is not installed. Please install it first.');
            }

            // Verificar se as tabelas existem
            if (!Schema::hasTable('chunks')) {
                throw new Exception('Table chunks does not exist');
            }

            if (!Schema::hasTable('documents')) {
                throw new Exception('Table documents does not exist');
            }

            // 1. Índice IVFFLAT para embeddings na tabela chunks (economia de espaço com partial)
            $this->createIndexIfNotExists(
                'idx_chunks_embedding_ivfflat',
                'CREATE INDEX idx_chunks_embedding_ivfflat
                 ON chunks USING ivfflat (embedding vector_cosine_ops)
                 WHERE embedding IS NOT NULL'
            );

            // 2. Índice composto para queries por documento na tabela chunks
            $this->createIndexIfNotExists(
                'idx_chunks_document_ord',
                'CREATE INDEX idx_chunks_document_ord
                 ON chunks (document_id, ord)'
            );

            // 3. Índice BRIN para timestamps (dados temporais grandes)
            $this->createIndexIfNotExists(
                'idx_chunks_created_brin',
                'CREATE INDEX idx_chunks_created_brin
                 ON chunks USING BRIN (created_at)'
            );

            // 4. Índice GIN para metadata JSONB na tabela chunks
            if (Schema::hasColumn('chunks', 'meta')) {
                $this->createIndexIfNotExists(
                    'idx_chunks_meta_gin',
                    'CREATE INDEX idx_chunks_meta_gin
                     ON chunks USING GIN (meta)'
                );
            }

            // 5. Índice para busca full-text no conteúdo
            $this->createIndexIfNotExists(
                'idx_chunks_content_fts',
                'CREATE INDEX idx_chunks_content_fts
                 ON chunks USING GIN (to_tsvector(\'english\', content))'
            );

            // 6. Índice composto para multi-tenancy na tabela documents
            $this->createIndexIfNotExists(
                'idx_documents_tenant_title',
                'CREATE INDEX idx_documents_tenant_title
                 ON documents (tenant_slug, title)'
            );

            // 7. Índice GIN para metadata na tabela documents
            if (Schema::hasColumn('documents', 'meta')) {
                $this->createIndexIfNotExists(
                    'idx_documents_meta_gin',
                    'CREATE INDEX idx_documents_meta_gin
                     ON documents USING GIN (meta)'
                );
            }

            // 8. Índice para timestamps na tabela documents
            $this->createIndexIfNotExists(
                'idx_documents_created_desc',
                'CREATE INDEX idx_documents_created_desc
                 ON documents (created_at DESC)'
            );

            // 9. Índice para tenant_slug na tabela documents (queries frequentes)
            $this->createIndexIfNotExists(
                'idx_documents_tenant_slug',
                'CREATE INDEX idx_documents_tenant_slug
                 ON documents (tenant_slug)'
            );

            // Configurações de performance
            $this->optimizeTableSettings();

        } catch (Exception $e) {
            // Log erro mas não falha a migration
            \Log::error('RAG Index Optimization Warning: ' . $e->getMessage());

            // Se for um erro crítico (tabelas não existem), re-throw
            if (strpos($e->getMessage(), 'does not exist') !== false) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Remover índices criados (na ordem inversa)
            $indexes = [
                'idx_documents_tenant_slug',
                'idx_documents_created_desc',
                'idx_documents_meta_gin',
                'idx_documents_tenant_title',
                'idx_chunks_content_fts',
                'idx_chunks_meta_gin',
                'idx_chunks_created_brin',
                'idx_chunks_document_ord',
                'idx_chunks_embedding_ivfflat',
            ];

            foreach ($indexes as $index) {
                $this->dropIndexIfExists($index);
            }

            // Resetar configurações de tabela
            $this->resetTableSettings();

        } catch (Exception $e) {
            \Log::warning('RAG Index Rollback Warning: ' . $e->getMessage());
        }
    }

    /**
     * Criar índice apenas se não existir
     */
    private function createIndexIfNotExists(string $indexName, string $sql): void
    {
        try {
            $exists = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE indexname = ? AND schemaname = current_schema()
            ", [$indexName]);

            if (empty($exists)) {
                DB::statement($sql);
                \Log::info("Created index: $indexName");
            } else {
                \Log::info("Index already exists: $indexName");
            }
        } catch (Exception $e) {
            \Log::warning("Failed to create index $indexName: " . $e->getMessage());
        }
    }

    /**
     * Remover índice apenas se existir
     */
    private function dropIndexIfExists(string $indexName): void
    {
        try {
            $exists = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE indexname = ? AND schemaname = current_schema()
            ", [$indexName]);

            if (!empty($exists)) {
                DB::statement("DROP INDEX IF EXISTS $indexName");
                \Log::info("Dropped index: $indexName");
            }
        } catch (Exception $e) {
            \Log::warning("Failed to drop index $indexName: " . $e->getMessage());
        }
    }

    /**
     * Otimizar configurações das tabelas
     */
    private function optimizeTableSettings(): void
    {
        try {
            // Otimizações para a tabela chunks (grande volume)
            if (Schema::hasTable('chunks')) {
                DB::statement("
                    ALTER TABLE chunks SET (
                        autovacuum_vacuum_scale_factor = 0.1,
                        autovacuum_analyze_scale_factor = 0.05,
                        autovacuum_vacuum_cost_limit = 1000,
                        fillfactor = 90
                    )
                ");

                // Aumentar estatísticas para a coluna embedding se existir
                if (Schema::hasColumn('chunks', 'embedding')) {
                    DB::statement('ALTER TABLE chunks ALTER COLUMN embedding SET STATISTICS 1000');
                }

                \Log::info('Optimized chunks table settings');
            }

            // Otimizações para a tabela documents
            if (Schema::hasTable('documents')) {
                DB::statement("
                    ALTER TABLE documents SET (
                        autovacuum_vacuum_scale_factor = 0.2,
                        autovacuum_analyze_scale_factor = 0.1
                    )
                ");

                \Log::info('Optimized documents table settings');
            }

        } catch (Exception $e) {
            \Log::warning('Failed to optimize table settings: ' . $e->getMessage());
        }
    }

    /**
     * Resetar configurações das tabelas
     */
    private function resetTableSettings(): void
    {
        try {
            if (Schema::hasTable('chunks')) {
                DB::statement("
                    ALTER TABLE chunks RESET (
                        autovacuum_vacuum_scale_factor,
                        autovacuum_analyze_scale_factor,
                        autovacuum_vacuum_cost_limit,
                        fillfactor
                    )
                ");

                if (Schema::hasColumn('chunks', 'embedding')) {
                    DB::statement('ALTER TABLE chunks ALTER COLUMN embedding SET STATISTICS -1');
                }
            }

            if (Schema::hasTable('documents')) {
                DB::statement("
                    ALTER TABLE documents RESET (
                        autovacuum_vacuum_scale_factor,
                        autovacuum_analyze_scale_factor
                    )
                ");
            }

        } catch (Exception $e) {
            \Log::warning('Failed to reset table settings: ' . $e->getMessage());
        }
    }
};