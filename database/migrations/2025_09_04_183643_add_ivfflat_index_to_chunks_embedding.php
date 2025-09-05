<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // garante a extensão
        DB::unprepared("CREATE EXTENSION IF NOT EXISTS vector");
        // cria índice ivfflat (bom custo/benefício; ajuste lists se crescer)
        DB::unprepared("CREATE INDEX IF NOT EXISTS chunks_embedding_ivfflat ON chunks USING ivfflat (embedding vector_l2_ops) WITH (lists = 100)");
        // ajuda o planner
        DB::unprepared("ANALYZE chunks");
    }

    public function down(): void
    {
        DB::unprepared("DROP INDEX IF EXISTS chunks_embedding_ivfflat");
    }
};
