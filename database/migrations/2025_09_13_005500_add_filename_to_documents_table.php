<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Garante que a tabela exista (ela deve ser criada por migrations anteriores)
        if (!Schema::hasTable('documents')) {
            return;
        }

        // Adiciona a coluna filename se não existir
        if (!Schema::hasColumn('documents', 'filename')) {
            Schema::table('documents', function (Blueprint $table) {
                // string portável; evitar ->after('title') para compatibilidade
                $table->string('filename', 512)->nullable();
            });

            // Índice é opcional para o teste; se quiser, descomente abaixo:
            // try { Schema::table('documents', fn(Blueprint $t) => $t->index('filename')); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'filename')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->dropColumn('filename');
                });
            } catch (\Throwable $e) {
                // Em alguns drivers, dropar coluna pode exigir DBAL; ignore em ambientes onde não for crítico.
            }
        }
    }
};
