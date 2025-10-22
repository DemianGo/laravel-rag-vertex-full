<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Se a tabela já existe (criada por outra migration), não faça nada.
        if (Schema::hasTable('chunks')) {
            return;
        }

        // Cria a tabela (portável para MySQL/Postgres)
        Schema::create('chunks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id')->index();
            // USE "ord" — é o nome que o seu código usa em produção
            $table->integer('ord')->default(0)->index();
            $table->longText('content');
            $table->timestamps();

            // FK (se o driver suportar)
            $table->foreign('document_id')
                  ->references('id')->on('documents')
                  ->onDelete('cascade');
        });

        // Índice simples (document_id, ord)
        try {
            DB::statement("CREATE INDEX IF NOT EXISTS chunks_document_ord_idx ON chunks (document_id, ord)");
        } catch (\Throwable $e) { /* ok */ }
    }

    public function down(): void
    {
        // Permite rollback/fresh em dev/testing
        Schema::dropIfExists('chunks');
    }
};
