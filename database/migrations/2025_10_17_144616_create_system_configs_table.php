<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique(); // chave única da configuração
            $table->string('config_name'); // nome legível
            $table->text('config_value'); // valor da configuração
            $table->string('config_type')->default('string'); // string, number, boolean, json
            $table->string('config_category')->default('general'); // general, ai, payment, security
            $table->text('description')->nullable(); // descrição da configuração
            $table->boolean('is_encrypted')->default(false); // se o valor deve ser criptografado
            $table->boolean('is_public')->default(false); // se pode ser acessado via API pública
            $table->timestamps();
            
            $table->index(['config_category', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};