<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // cria a extensão 'vector' se ainda não existir
        DB::statement("CREATE EXTENSION IF NOT EXISTS vector");
    }

    public function down(): void
    {
        // Não derrubamos a extensão para evitar impacto em outros schemas.
        // Se você realmente quiser, remova o comentário:
        // DB::statement("DROP EXTENSION IF EXISTS vector");
    }
};
