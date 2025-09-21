<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->driver();
        // Só executa em PostgreSQL. Em SQLite/MySQL, não faz nada.
        if ($driver !== 'pgsql') {
            return;
        }

        // Habilita extensão pgvector se não existir
        try {
            DB::statement("CREATE EXTENSION IF NOT EXISTS vector");
        } catch (\Throwable $e) {
            // Em ambientes sem permissão para EXTENSION, não quebrar a migration de teste
        }
    }

    public function down(): void
    {
        $driver = $this->driver();
        if ($driver !== 'pgsql') {
            return;
        }

        // Opcional: desfaz. Em produção, avalie se quer mesmo dropar a extensão.
        try {
            DB::statement("DROP EXTENSION IF EXISTS vector");
        } catch (\Throwable $e) {
            // Silencioso no rollback
        }
    }

    private function driver(): ?string
    {
        try {
            return DB::connection()->getDriverName();
        } catch (\Throwable $e) {
            return null;
        }
    }
};
