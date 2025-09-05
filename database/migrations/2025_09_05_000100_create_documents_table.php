<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se a tabela não existir, cria completa
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title', 512);
                $table->string('source', 128)->default('api:text');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
            return;
        }

        // Se já existir (seu caso), adiciona somente o que faltar
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'title')) {
                $table->string('title', 512)->nullable();
            }
            if (!Schema::hasColumn('documents', 'source')) {
                $table->string('source', 128)->default('api:text');
            }
            if (!Schema::hasColumn('documents', 'metadata')) {
                $table->json('metadata')->nullable();
            }
            if (!Schema::hasColumn('documents', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('documents', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Não drope a tabela em rollback para não perder dados preexistentes.
        // Se quiser permitir rollback destrutivo, troque por: Schema::dropIfExists('documents');
    }
};
