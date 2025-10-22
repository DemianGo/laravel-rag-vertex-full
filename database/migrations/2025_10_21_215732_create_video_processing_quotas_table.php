<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_processing_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_slug')->unique();
            $table->integer('daily_limit')->default(10);
            $table->integer('monthly_limit')->default(100);
            $table->integer('max_duration_seconds')->default(1800);
            $table->integer('used_today')->default(0);
            $table->integer('used_this_month')->default(0);
            $table->date('last_reset_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_processing_quotas');
    }
};