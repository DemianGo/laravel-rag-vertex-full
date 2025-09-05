<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cria a tabela se não existir
        if (!Schema::hasTable('chunks')) {
            Schema::create('chunks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('document_id');
                $table->integer('ord')->default(0);
                $table->text('content');
                $table->timestamps();

                $table->index('document_id');
                $table->foreign('document_id')
                      ->references('id')->on('documents')
                      ->onDelete('cascade');
            });

            // coluna pgvector(768) para text-embedding-004
            DB::statement("ALTER TABLE chunks ADD COLUMN embedding vector(768)");
        } else {
            // Já existe: garantir colunas mínimas
            Schema::table('chunks', function (Blueprint $table) {
                if (!Schema::hasColumn('chunks', 'document_id')) {
                    $table->unsignedBigInteger('document_id')->nullable()->index();
                }
                if (!Schema::hasColumn('chunks', 'ord')) {
                    $table->integer('ord')->default(0);
                }
                if (!Schema::hasColumn('chunks', 'content')) {
                    $table->text('content')->nullable();
                }
                if (!Schema::hasColumn('chunks', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('chunks', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            // Garante a coluna embedding vector(768) se não existir
            $exists = DB::selectOne("
                SELECT 1
                FROM information_schema.columns
                WHERE table_name = 'chunks' AND column_name = 'embedding'
            ");
            if (!$exists) {
                DB::statement("ALTER TABLE chunks ADD COLUMN embedding vector(768)");
            }
        }
    }

    public function down(): void
    {
        // Evita apagar dados existentes em rollback.
        // Para rollback destrutivo, use: Schema::dropIfExists('chunks');
    }
};
