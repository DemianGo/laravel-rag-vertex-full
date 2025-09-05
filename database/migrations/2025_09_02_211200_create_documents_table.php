<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_slug', 100)->index();
            $table->string('title');
            $table->string('source', 50)->default('manual');
            $table->string('uri')->nullable();
            $table->json('meta')->nullable(); // em Postgres vira jsonb
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
