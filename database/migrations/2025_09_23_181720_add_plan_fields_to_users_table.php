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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('plan', ['free', 'pro', 'enterprise'])->default('free');
            $table->integer('tokens_used')->default(0);
            $table->integer('tokens_limit')->default(100);
            $table->integer('documents_used')->default(0);
            $table->integer('documents_limit')->default(1);
            $table->timestamp('plan_renewed_at')->nullable();
            $table->boolean('plan_auto_renew')->default(false);

            $table->index(['plan', 'tokens_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['plan', 'tokens_used']);
            $table->dropColumn([
                'plan',
                'tokens_used',
                'tokens_limit',
                'documents_used',
                'documents_limit',
                'plan_renewed_at',
                'plan_auto_renew'
            ]);
        });
    }
};