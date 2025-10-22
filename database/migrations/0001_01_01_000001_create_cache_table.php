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

        // Cria a tabela apenas se ainda não existir (portável para MySQL/PG)
        if (!Schema::hasTable('chunks')) {
            Schema::create('chunks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('document_id')->index();
                $table->integer('ord')->default(0)->index();
                $table->longText('content');

                // Placeholder portável; em PG outras migrations podem trocar para vector(dim)
                $table->text('embedding')->nullable();

                $table->timestamps();
            });
        }

        // Índice simples (document_id, ord) — portável
        try {
            DB::statement("CREATE INDEX IF NOT EXISTS chunks_document_ord_idx ON chunks (document_id, ord)");
        } catch (\Throwable $e) {
            // Alguns sistemas antigos não suportam IF NOT EXISTS em CREATE INDEX — ignoramos silenciosamente
        }

        // Índice IVFFLAT só em Postgres (pgvector)
        if ($driver === 'pgsql') {
            try { DB::statement("CREATE EXTENSION IF NOT EXISTS vector"); } catch (\Throwable $e) {}
            try {
                DB::statement("
                    CREATE INDEX IF NOT EXISTS idx_chunks_embedding
                    ON chunks
                    USING ivfflat (embedding vector_cosine_ops)
                    WITH (lists = 100)
                ");
            } catch (\Throwable $e) {
                // Se a coluna ainda não é vector neste ambiente, não quebrar testes
            }
        }
    }

    public function down(): void
    {
        // Não derrubamos a tabela aqui para não afetar outros ambientes.
        // Se precisar: Schema::dropIfExists('chunks');
    }

    private function driver(): ?string
    {
        try { return DB::connection()->getDriverName(); } catch (\Throwable $e) { return null; }
    }
};
